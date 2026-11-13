(function () {
  const assetBase = '/TMZ%20PODCAST/assets/js/';
  const equalizerFrequencies = [60, 120, 250, 500, 1000, 2000, 4000, 8000, 12000];
  const equalizerLabels = ['60 Hz', '120 Hz', '250 Hz', '500 Hz', '1 kHz', '2 kHz', '4 kHz', '8 kHz', '12 kHz'];
  const equalizerPresets = {
    natural: [0, 0, 0, 0, 0, 0, 0, 0, 0],
    podcast: [2, 1, -1, -1, 1, 2, 2, 1, 0],
    warm: [3, 2, 1, 0, -1, -1, 0, 0, -1],
    bright: [0, -1, -1, 0, 0, 1, 2, 3, 2],
    phone: [-3, -2, -1, 1, 2, 2, 1, -1, -3],
    deep: [3, 2, 1, 0, 0, -1, -1, -2, -2]
  };
  const noiseStrengths = { off: 0, low: 0.3, medium: 0.58, high: 0.88 };

  function byId(id) {
    return document.getElementById(id);
  }

  function setText(id, text) {
    const element = byId(id);
    if (element) element.textContent = text;
  }

  function setFeatureStatus(message, state) {
    const element = byId('audioFeatureStatus');
    if (!element) return;
    element.textContent = message;
    element.dataset.state = state || 'info';
  }

  class AudioPipeline {
    constructor(inputTrack) {
      this.inputTrack = inputTrack || null;
      this.context = null;
      this.destination = null;
      this.worklet = null;
      this.outputTrack = null;
      this.sender = null;
      this.rnnoiseAvailable = false;
      this.workletAvailable = false;
      this.equalizer = [];
      this.settings = {
        browserProcessing: true,
        noiseLevel: 'medium',
        voiceIsolation: false,
        gateEnabled: true,
        gateThreshold: 0.0018,
        highPassEnabled: true,
        eqEnabled: true,
        deEsserEnabled: true,
        dePlosiveEnabled: true,
        autoLevelEnabled: true,
        compressorEnabled: true,
        limiterEnabled: true,
        windHumEnabled: true,
        humFrequency: 50,
        clarity: 0,
        warmth: 0,
        reverb: 0,
        dryRoom: true,
        musicMode: false,
        eqPreset: 'podcast',
        eqGains: equalizerPresets.podcast.slice()
      };
      this.buildEqualizerControls();
      this.bindControls();
      this.applyProfile('podcast');
    }

    async start() {
      if (!this.context) {
        try {
          this.context = new AudioContext({ sampleRate: 48000, latencyHint: 'interactive' });
        } catch (error) {
          console.warn('Could not request 48 kHz audio; using the browser default rate.', error);
          this.context = new AudioContext({ latencyHint: 'interactive' });
        }
      }

      if (this.context.state === 'suspended') await this.context.resume();
      if (!this.inputTrack || this.inputTrack.readyState !== 'live') {
        throw new Error('The microphone track is unavailable or has ended.');
      }
      this.source = this.context.createMediaStreamSource(new MediaStream([this.inputTrack]));

      if (this.context.audioWorklet && window.AudioWorkletNode) {
        try {
          await this.context.audioWorklet.addModule(assetBase + 'audio-pipeline-worklet.js');
          this.worklet = new AudioWorkletNode(this.context, 'tmz-audio-processor', {
            numberOfInputs: 1,
            numberOfOutputs: 1,
            outputChannelCount: [1]
          });
          this.worklet.port.onmessage = (event) => this.handleWorkletMessage(event.data || {});
          this.workletAvailable = true;
        } catch (error) {
          console.error('AudioWorklet could not be loaded:', error);
        }
      }
      if (!this.workletAvailable) {
        setFeatureStatus('AudioWorklet unavailable: RNNoise and noise gate are off; browser audio filters remain active.', 'warning');
        ['noiseSuppressionLevel', 'voiceIsolation', 'noiseGateEnabled', 'calibrateNoiseGate'].forEach((id) => {
          const control = byId(id);
          if (control) control.disabled = true;
        });
      }

      this.createGraph();
      this.destination = this.context.createMediaStreamDestination();
      this.destination.channelCount = this.settings.musicMode ? 2 : 1;
      this.outputGain.connect(this.destination);
      this.reverb.connect(this.reverbGain);
      this.reverbGain.connect(this.destination);
      this.outputTrack = this.destination.stream.getAudioTracks()[0];

      await this.applyBrowserProcessing();
      this.applySettings();

      const notices = [];
      if (!this.workletAvailable) notices.push('AudioWorklet unavailable: RNNoise and noise gate are off; browser audio filters remain active.');
      else if (this.context.sampleRate !== 48000) notices.push('RNNoise unavailable at this sample rate; browser cleanup and filters remain active.');
      if (!this.inputTrack.applyConstraints) notices.push('Browser microphone-processing controls are unavailable.');
      setFeatureStatus(notices.join(' '), notices.length ? 'warning' : 'success');

      return this.outputTrack;
    }

    createGraph() {
      this.highPass = this.context.createBiquadFilter();
      this.highPass.type = 'highpass';
      this.highPass.frequency.value = 80;
      this.highPass.Q.value = 0.707;

      this.notch50 = this.createNotch(50);
      this.notch100 = this.createNotch(100);
      this.equalizer = equalizerFrequencies.map((frequency) => {
        const filter = this.context.createBiquadFilter();
        filter.type = 'peaking';
        filter.frequency.value = frequency;
        filter.Q.value = 1;
        return filter;
      });

      this.warmthFilter = this.context.createBiquadFilter();
      this.warmthFilter.type = 'lowshelf';
      this.warmthFilter.frequency.value = 180;
      this.clarityFilter = this.context.createBiquadFilter();
      this.clarityFilter.type = 'highshelf';
      this.clarityFilter.frequency.value = 3500;

      this.mix = this.context.createGain();
      this.deEsserFilter = this.context.createBiquadFilter();
      this.deEsserFilter.type = 'bandpass';
      this.deEsserFilter.frequency.value = 5200;
      this.deEsserFilter.Q.value = 0.8;
      this.deEsserCompressor = this.context.createDynamicsCompressor();
      this.deEsserCompressor.threshold.value = -28;
      this.deEsserCompressor.knee.value = 8;
      this.deEsserCompressor.ratio.value = 8;
      this.deEsserCompressor.attack.value = 0.003;
      this.deEsserCompressor.release.value = 0.12;
      this.deEsserGain = this.context.createGain();
      this.deEsserGain.gain.value = -0.5;

      this.autoLevel = this.context.createDynamicsCompressor();
      this.autoLevel.threshold.value = -26;
      this.autoLevel.knee.value = 18;
      this.autoLevel.ratio.value = 1.8;
      this.autoLevel.attack.value = 0.08;
      this.autoLevel.release.value = 0.25;
      this.compressor = this.context.createDynamicsCompressor();
      this.compressor.threshold.value = -18;
      this.compressor.knee.value = 16;
      this.compressor.ratio.value = 3;
      this.compressor.attack.value = 0.006;
      this.compressor.release.value = 0.16;
      this.limiter = this.context.createDynamicsCompressor();
      this.limiter.threshold.value = -2;
      this.limiter.knee.value = 0;
      this.limiter.ratio.value = 20;
      this.limiter.attack.value = 0.003;
      this.limiter.release.value = 0.12;
      this.outputGain = this.context.createGain();
      this.outputGain.gain.value = 1;

      this.reverb = this.context.createConvolver();
      this.reverb.buffer = this.createReverbImpulse(0.55);
      this.reverbGain = this.context.createGain();
      this.reverbGain.gain.value = 0;

      let chain = this.worklet || this.source;
      const serialNodes = [this.highPass, this.notch50, this.notch100, ...this.equalizer, this.warmthFilter, this.clarityFilter, this.mix];
      serialNodes.forEach((node) => {
        chain.connect(node);
        chain = node;
      });

      this.clarityFilter.connect(this.deEsserFilter);
      this.deEsserFilter.connect(this.deEsserCompressor);
      this.deEsserCompressor.connect(this.deEsserGain);
      this.deEsserGain.connect(this.mix);

      chain.connect(this.autoLevel);
      this.autoLevel.connect(this.compressor);
      this.compressor.connect(this.limiter);
      this.limiter.connect(this.outputGain);
      this.limiter.connect(this.reverb);

      if (this.worklet) this.source.connect(this.worklet);
    }

    createNotch(frequency) {
      const filter = this.context.createBiquadFilter();
      filter.type = 'notch';
      filter.frequency.value = frequency;
      filter.Q.value = 22;
      return filter;
    }

    createReverbImpulse(duration) {
      const length = Math.max(1, Math.floor(this.context.sampleRate * duration));
      const impulse = this.context.createBuffer(2, length, this.context.sampleRate);
      for (let channel = 0; channel < impulse.numberOfChannels; channel++) {
        const samples = impulse.getChannelData(channel);
        for (let i = 0; i < length; i++) {
          const decay = Math.pow(1 - i / length, 3.2);
          samples[i] = (Math.random() * 2 - 1) * decay;
        }
      }
      return impulse;
    }

    buildEqualizerControls() {
      const container = byId('audioEqualizerBands');
      if (!container) return;
      equalizerLabels.forEach((label, index) => {
        const wrapper = document.createElement('label');
        wrapper.className = 'audio-eq-band';
        const title = document.createElement('span');
        title.textContent = label;
        const input = document.createElement('input');
        input.type = 'range';
        input.min = '-9';
        input.max = '9';
        input.step = '1';
        input.value = '0';
        input.dataset.eqIndex = String(index);
        input.setAttribute('aria-label', label + ' equalizer gain');
        input.addEventListener('input', () => {
          this.settings.eqPreset = 'custom';
          this.settings.eqGains[index] = Number(input.value);
          const filter = this.equalizer[index];
          if (filter && this.context) filter.gain.setTargetAtTime(Number(input.value), this.context.currentTime, 0.02);
          const preset = byId('audioEqPreset');
          if (preset) preset.value = 'custom';
          setText('audioPresetLabel', 'Custom EQ');
        });
        wrapper.append(title, input);
        container.appendChild(wrapper);
      });
    }

    bindControls() {
      const bindToggle = (id, key) => {
        const input = byId(id);
        if (!input) return;
        input.addEventListener('change', async () => {
          this.settings[key] = input.checked;
          if (key === 'voiceIsolation' && input.checked && this.settings.noiseLevel === 'off') {
            this.settings.noiseLevel = 'medium';
            const noise = byId('noiseSuppressionLevel');
            if (noise) noise.value = 'medium';
          }
          this.applySettings();
          if (key === 'browserProcessing' || key === 'musicMode') await this.applyBrowserProcessing();
        });
      };

      bindToggle('browserProcessing', 'browserProcessing');
      bindToggle('voiceIsolation', 'voiceIsolation');
      bindToggle('noiseGateEnabled', 'gateEnabled');
      bindToggle('highPassEnabled', 'highPassEnabled');
      bindToggle('equalizerEnabled', 'eqEnabled');
      bindToggle('deEsserEnabled', 'deEsserEnabled');
      bindToggle('dePlosiveEnabled', 'dePlosiveEnabled');
      bindToggle('autoLevelEnabled', 'autoLevelEnabled');
      bindToggle('compressorEnabled', 'compressorEnabled');
      bindToggle('limiterEnabled', 'limiterEnabled');
      bindToggle('windHumEnabled', 'windHumEnabled');
      bindToggle('dryRoom', 'dryRoom');
      bindToggle('musicMode', 'musicMode');

      byId('noiseSuppressionLevel')?.addEventListener('change', (event) => {
        this.settings.noiseLevel = event.currentTarget.value;
        this.applySettings();
      });
      byId('audioEqPreset')?.addEventListener('change', (event) => this.applyProfile(event.currentTarget.value));
      byId('humFrequency')?.addEventListener('change', (event) => {
        this.settings.humFrequency = Number(event.currentTarget.value);
        this.applySettings();
      });

      [['audioGateThreshold', 'gateThreshold'], ['audioClarity', 'clarity'], ['audioWarmth', 'warmth'], ['audioReverb', 'reverb']].forEach(([id, key]) => {
        byId(id)?.addEventListener('input', (event) => {
          const value = Number(event.currentTarget.value);
          this.settings[key] = key === 'gateThreshold' ? Math.pow(10, value / 20) : value;
          setText(id + 'Value', key === 'gateThreshold' ? value + ' dB' : value + (key === 'reverb' ? '%' : ' dB'));
          this.applySettings();
        });
      });

      byId('calibrateNoiseGate')?.addEventListener('click', () => {
        if (!this.worklet) {
          setFeatureStatus('Noise-gate calibration requires AudioWorklet support.', 'warning');
          return;
        }
        setText('noiseGateCalibrationStatus', 'Stay quiet… 3 seconds');
        this.worklet.port.postMessage({ type: 'calibrate' });
      });

      document.querySelectorAll('[data-audio-preset]').forEach((button) => {
        button.addEventListener('click', () => this.applyProfile(button.dataset.audioPreset));
      });
    }

    applyProfile(profile) {
      const profiles = {
        studio: { eq: 'natural', noise: 'medium', isolation: false, clarity: 1, warmth: 1, reverb: 8, dry: false, gate: true },
        podcast: { eq: 'podcast', noise: 'medium', isolation: false, clarity: 1, warmth: 0, reverb: 0, dry: true, gate: true },
        interview: { eq: 'natural', noise: 'low', isolation: false, clarity: 0, warmth: 0, reverb: 0, dry: true, gate: true },
        'noisy-room': { eq: 'phone', noise: 'high', isolation: true, clarity: 1, warmth: 0, reverb: 0, dry: true, gate: true },
        music: { eq: 'natural', noise: 'low', isolation: false, clarity: 0, warmth: 0, reverb: 0, dry: true, gate: false, music: true }
      };
      if (profile === 'natural' || profile === 'podcast-eq' || profile === 'warm' || profile === 'bright' || profile === 'phone' || profile === 'deep') {
        this.settings.eqPreset = profile === 'podcast-eq' ? 'podcast' : profile;
        this.settings.eqGains = equalizerPresets[this.settings.eqPreset].slice();
        this.syncEqualizerControls();
        this.applySettings();
        return;
      }
      if (profile === 'custom') {
        this.settings.eqPreset = 'custom';
        this.applySettings();
        return;
      }
      const preset = profiles[profile];
      if (!preset) return;
      this.settings.eqPreset = preset.eq;
      this.settings.eqGains = equalizerPresets[preset.eq].slice();
      this.settings.noiseLevel = preset.noise;
      this.settings.voiceIsolation = preset.isolation;
      this.settings.clarity = preset.clarity;
      this.settings.warmth = preset.warmth;
      this.settings.reverb = preset.reverb;
      this.settings.dryRoom = preset.dry;
      this.settings.gateEnabled = preset.gate;
      this.settings.musicMode = Boolean(preset.music);
      this.syncControls();
      this.applySettings();
      this.applyBrowserProcessing();
    }

    syncControls() {
      const values = {
        browserProcessing: this.settings.browserProcessing,
        voiceIsolation: this.settings.voiceIsolation,
        noiseGateEnabled: this.settings.gateEnabled,
        highPassEnabled: this.settings.highPassEnabled,
        equalizerEnabled: this.settings.eqEnabled,
        deEsserEnabled: this.settings.deEsserEnabled,
        dePlosiveEnabled: this.settings.dePlosiveEnabled,
        autoLevelEnabled: this.settings.autoLevelEnabled,
        compressorEnabled: this.settings.compressorEnabled,
        limiterEnabled: this.settings.limiterEnabled,
        windHumEnabled: this.settings.windHumEnabled,
        dryRoom: this.settings.dryRoom,
        musicMode: this.settings.musicMode
      };
      Object.entries(values).forEach(([id, checked]) => {
        const input = byId(id);
        if (input) input.checked = checked;
      });
      const noise = byId('noiseSuppressionLevel');
      if (noise) noise.value = this.settings.noiseLevel;
      const eq = byId('audioEqPreset');
      if (eq) eq.value = this.settings.eqPreset;
      const reverb = byId('audioReverb');
      if (reverb) reverb.value = String(this.settings.reverb);
      const clarity = byId('audioClarity');
      if (clarity) clarity.value = String(this.settings.clarity);
      const warmth = byId('audioWarmth');
      if (warmth) warmth.value = String(this.settings.warmth);
      const gateThreshold = byId('audioGateThreshold');
      if (gateThreshold) gateThreshold.value = String(Math.round(20 * Math.log10(this.settings.gateThreshold)));
      setText('audioGateThresholdValue', Math.round(20 * Math.log10(this.settings.gateThreshold)) + ' dB');
      setText('audioClarityValue', this.settings.clarity + ' dB');
      setText('audioWarmthValue', this.settings.warmth + ' dB');
      setText('audioReverbValue', this.settings.reverb + '%');
      this.syncEqualizerControls();
    }

    syncEqualizerControls() {
      document.querySelectorAll('[data-eq-index]').forEach((input) => {
        const index = Number(input.dataset.eqIndex);
        input.value = String(this.settings.eqGains[index] || 0);
      });
    }

    async applyBrowserProcessing() {
      if (!this.inputTrack) return;
      if (!this.inputTrack.applyConstraints) {
        setText('browserProcessingStatus', 'Browser microphone controls unavailable');
        return;
      }
      const enabled = this.settings.browserProcessing && !this.settings.musicMode;
      const constraints = {
        echoCancellation: enabled,
        noiseSuppression: enabled,
        autoGainControl: enabled,
        channelCount: { ideal: this.settings.musicMode ? 2 : 1 }
      };
      try {
        await this.inputTrack.applyConstraints(constraints);
        const applied = this.inputTrack.getSettings ? this.inputTrack.getSettings() : {};
        const echoStatus = !enabled
          ? 'echo cancellation off'
          : applied.echoCancellation === true
            ? 'echo cancellation active'
            : applied.echoCancellation === false
              ? 'echo cancellation unavailable'
              : 'echo cancellation requested';
        setText('browserProcessingStatus', (enabled ? 'Browser mic cleanup on' : 'Browser mic cleanup off') + ' · ' + echoStatus);
      } catch (error) {
        console.warn('Could not update browser microphone constraints:', error);
        setText('browserProcessingStatus', 'Browser did not accept all mic settings');
      }
    }

    applySettings() {
      if (!this.context) return;
      const now = this.context.currentTime;
      const ramp = (param, value) => param.setTargetAtTime(value, now, 0.025);
      let noiseStrength = this.settings.musicMode ? Math.min(noiseStrengths[this.settings.noiseLevel], 0.3) : noiseStrengths[this.settings.noiseLevel];
      if (this.settings.voiceIsolation && !this.settings.musicMode) {
        noiseStrength = Math.max(noiseStrength, noiseStrengths.medium);
      }

      if (this.worklet) {
        this.worklet.port.postMessage({
          type: 'configure',
          noiseStrength,
          voiceIsolation: this.settings.voiceIsolation && !this.settings.musicMode,
          gateEnabled: this.settings.gateEnabled && !this.settings.musicMode,
          gateThreshold: this.settings.gateThreshold
        });
      }
      this.highPass.frequency.setTargetAtTime(
        this.settings.highPassEnabled
          ? (this.settings.windHumEnabled && this.settings.dePlosiveEnabled ? 110 :
            this.settings.windHumEnabled ? 120 :
              this.settings.dePlosiveEnabled ? 100 : 80)
          : 10,
        now,
        0.025
      );
      ramp(this.highPass.gain, 0);
      const hum = this.settings.humFrequency;
      ramp(this.notch50.frequency, hum);
      ramp(this.notch100.frequency, hum * 2);
      this.notch50.Q.value = this.settings.windHumEnabled ? 22 : 0.0001;
      this.notch100.Q.value = this.settings.windHumEnabled ? 18 : 0.0001;
      this.equalizer.forEach((filter, index) => ramp(filter.gain, this.settings.eqEnabled ? this.settings.eqGains[index] || 0 : 0));
      ramp(this.warmthFilter.gain, this.settings.warmth);
      ramp(this.clarityFilter.gain, this.settings.clarity);
      ramp(this.deEsserGain.gain, this.settings.deEsserEnabled ? -0.5 : 0);
      ramp(this.autoLevel.threshold, this.settings.autoLevelEnabled ? -26 : 0);
      ramp(this.autoLevel.ratio, this.settings.autoLevelEnabled ? 1.8 : 1);
      ramp(this.compressor.threshold, this.settings.compressorEnabled ? -18 : 0);
      ramp(this.compressor.ratio, this.settings.compressorEnabled ? 3 : 1);
      ramp(this.limiter.threshold, this.settings.limiterEnabled ? -2 : 0);
      ramp(this.limiter.ratio, this.settings.limiterEnabled ? 20 : 1);
      ramp(this.reverbGain.gain, this.settings.dryRoom || this.settings.musicMode ? 0 : this.settings.reverb / 100);
      this.destination.channelCount = this.settings.musicMode ? 2 : 1;
      this.setSenderBitrate();
      setText('audioPresetLabel', this.settings.musicMode ? 'Music Mode' : this.settings.eqPreset === 'custom' ? 'Custom EQ' : this.settings.eqPreset);
    }

    handleWorkletMessage(message) {
      if (message.type === 'feature' && message.feature === 'rnnoise') {
        this.rnnoiseAvailable = Boolean(message.available);
        if (!message.available) {
          setFeatureStatus(message.reason + ' Browser cleanup and audio filters remain available.', 'warning');
          ['voiceIsolation', 'noiseSuppressionLevel'].forEach((id) => {
            const control = byId(id);
            if (control) control.disabled = true;
          });
        } else {
          setFeatureStatus('AudioWorklet and open-source RNNoise ready. Processing stays in your browser.', 'success');
          this.applySettings();
        }
      }
      if (message.type === 'meter') {
        const level = Math.min(100, Math.round(message.rms * 700));
        const fill = byId('audioMeterFill');
        const pipelineFill = byId('audioPipelineMeterFill');
        if (fill) fill.style.width = level + '%';
        if (pipelineFill) pipelineFill.style.width = level + '%';
        setText('audioPipelineMeterLabel', message.gateOpen ? 'VOICE' : 'GATE CLOSED');
        setText('audioVadValue', typeof message.voiceProbability === 'number' ? Math.round(message.voiceProbability * 100) + '%' : '—');
      }
      if (message.type === 'calibration') {
        if (message.progress >= 1) {
          this.settings.gateThreshold = message.threshold;
          const slider = byId('audioGateThreshold');
          if (slider) slider.value = String(Math.round(20 * Math.log10(message.threshold)));
          setText('noiseGateCalibrationStatus', 'Calibrated');
        } else {
          setText('noiseGateCalibrationStatus', Math.ceil((1 - message.progress) * 3) + ' seconds…');
        }
        this.applySettings();
      }
    }

    async setSenderBitrate(sender) {
      if (sender) this.sender = sender;
      if (!this.sender?.getParameters || !this.sender?.setParameters) return;
      const parameters = this.sender.getParameters();
      if (!parameters.encodings || !parameters.encodings.length) parameters.encodings = [{}];
      const maxBitrate = this.settings.musicMode ? 128000 : 64000;
      if (parameters.encodings.every((encoding) => encoding.maxBitrate === maxBitrate)) return;
      parameters.encodings.forEach((encoding) => { encoding.maxBitrate = maxBitrate; });
      try {
        await this.sender.setParameters(parameters);
      } catch (error) {
        console.warn('Could not change microphone sender bitrate:', error);
      }
    }

    async stop() {
      if (this.worklet) this.worklet.port.close();
      if (this.context && this.context.state !== 'closed') await this.context.close();
      if (this.inputTrack && this.inputTrack.readyState === 'live') this.inputTrack.stop();
    }
  }

  window.TMZAudioPipeline = AudioPipeline;

  const devices = navigator.mediaDevices;
  if (!devices || !window.LivekitClient) return;

  const nativeGetUserMedia = devices.getUserMedia.bind(devices);
  devices.getUserMedia = function (constraints) {
    return nativeGetUserMedia(constraints).then((stream) => {
      if (constraints && constraints.audio) window.tmzLiveMicrophoneStream = stream;
      return stream;
    });
  };

  const nativeCreateAudioTrack = LivekitClient.createLocalAudioTrack.bind(LivekitClient);
  const createMicrophoneTrack = (mediaTrack, audioContext) => {
    const localTrack = new LivekitClient.LocalAudioTrack(mediaTrack, {}, true, audioContext);
    localTrack.source = LivekitClient.Track.Source.Microphone;
    return localTrack;
  };
  LivekitClient.createLocalAudioTrack = async function (options) {
    const inputTrack = window.tmzLiveMicrophoneStream?.getAudioTracks()[0];
    if (!inputTrack) return nativeCreateAudioTrack(options);

    const pipeline = window.tmzAudioPipeline || new AudioPipeline();
    pipeline.inputTrack = inputTrack;
    window.tmzAudioPipeline = pipeline;
    try {
      const outputTrack = await pipeline.start();
      return createMicrophoneTrack(outputTrack, pipeline.context);
    } catch (error) {
      console.error('Audio processing setup failed; publishing the microphone without effects:', error);
      setFeatureStatus('Audio processing setup failed. Using the microphone with browser processing only.', 'error');
      if (pipeline.context && pipeline.context.state !== 'closed') await pipeline.context.close();
      pipeline.context = null;
      pipeline.inputTrack = null;
      return createMicrophoneTrack(inputTrack);
    }
  };

  const publishTrack = LivekitClient.LocalParticipant.prototype.publishTrack;
  LivekitClient.LocalParticipant.prototype.publishTrack = function (track, options) {
    return publishTrack.call(this, track, options).then((publication) => {
      if (track.kind === 'audio') window.tmzAudioPipeline?.setSenderBitrate(track.sender);
      return publication;
    });
  };

  if (document.getElementById('audioEqualizerBands')) window.tmzAudioPipeline = new AudioPipeline();
  byId('cameraButton')?.addEventListener('click', () => {
    const AudioContextClass = window.AudioContext;
    if (!AudioContextClass || !window.tmzAudioPipeline || window.tmzAudioPipeline.context) return;
    try {
      window.tmzAudioPipeline.context = new AudioContextClass({ sampleRate: 48000, latencyHint: 'interactive' });
      window.tmzAudioPipeline.context.resume().catch((error) => {
        console.warn('Could not activate audio processing from the studio button:', error);
      });
    } catch (error) {
      console.warn('Could not activate audio processing from the studio button:', error);
    }
  }, { capture: true });
  window.addEventListener('pagehide', () => window.tmzAudioPipeline?.stop());
})();
