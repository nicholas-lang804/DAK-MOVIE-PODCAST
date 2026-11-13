import createRNNoiseModule from './vendor/rnnoise-sync.js';

class TMZAudioProcessor extends AudioWorkletProcessor {
  constructor() {
    super();
    this.enabled = true;
    this.noiseStrength = 0.55;
    this.voiceIsolation = false;
    this.gateEnabled = true;
    this.gateThreshold = 0.0018;
    this.gateGain = 0;
    this.frameSize = 480;
    this.frameIndex = 0;
    this.inputFrame = new Float32Array(this.frameSize);
    this.outputFrame = new Float32Array(this.frameSize);
    this.rnnoise = null;
    this.rnnoiseState = 0;
    this.rnnoiseInput = 0;
    this.rnnoiseOutput = 0;
    this.samplesToReport = Math.round(sampleRate / 10);
    this.reportSamples = 0;
    this.reportEnergy = 0;
    this.calibratingSamples = 0;
    this.calibrationEnergy = 0;
    this.calibrationCount = 0;

    this.port.onmessage = (event) => this.handleMessage(event.data || {});
    this.loadRNNoise();
  }

  async loadRNNoise() {
    try {
      if (sampleRate !== 48000) {
        this.port.postMessage({ type: 'feature', feature: 'rnnoise', available: false, reason: 'RNNoise requires a 48 kHz audio context.' });
        return;
      }
      const module = await createRNNoiseModule();
      await module.ready;
      module._rnnoise_init();
      this.rnnoise = module;
      this.rnnoiseState = module._rnnoise_create(0);
      this.rnnoiseInput = module._malloc(this.frameSize * Float32Array.BYTES_PER_ELEMENT);
      this.rnnoiseOutput = module._malloc(this.frameSize * Float32Array.BYTES_PER_ELEMENT);
      if (!this.rnnoiseState || !this.rnnoiseInput || !this.rnnoiseOutput) throw new Error('RNNoise could not allocate its audio buffers.');
      this.port.postMessage({ type: 'feature', feature: 'rnnoise', available: true });
    } catch (error) {
      this.port.postMessage({ type: 'feature', feature: 'rnnoise', available: false, reason: error.message || 'RNNoise could not be initialized.' });
    }
  }

  handleMessage(message) {
    if (message.type === 'configure') {
      if (typeof message.noiseStrength === 'number') this.noiseStrength = Math.max(0, Math.min(1, message.noiseStrength));
      if (typeof message.voiceIsolation === 'boolean') this.voiceIsolation = message.voiceIsolation;
      if (typeof message.gateEnabled === 'boolean') this.gateEnabled = message.gateEnabled;
      if (typeof message.gateThreshold === 'number') this.gateThreshold = Math.max(0.0001, Math.min(0.2, message.gateThreshold));
    }
    if (message.type === 'calibrate') {
      this.calibratingSamples = sampleRate * 3;
      this.calibrationEnergy = 0;
      this.calibrationCount = 0;
      this.port.postMessage({ type: 'calibration', progress: 0 });
    }
  }

  processRNNoise() {
    const module = this.rnnoise;
    if (!module || !this.rnnoiseState || this.noiseStrength <= 0) {
      this.outputFrame.set(this.inputFrame);
      return 0;
    }

    const input = module.HEAPF32.subarray(this.rnnoiseInput >> 2, (this.rnnoiseInput >> 2) + this.frameSize);
    const output = module.HEAPF32.subarray(this.rnnoiseOutput >> 2, (this.rnnoiseOutput >> 2) + this.frameSize);
    for (let i = 0; i < this.frameSize; i++) input[i] = this.inputFrame[i] * 32768;
    const voiceProbability = module._rnnoise_process_frame(this.rnnoiseState, this.rnnoiseOutput, this.rnnoiseInput);
    for (let i = 0; i < this.frameSize; i++) {
      const clean = output[i] / 32768;
      this.outputFrame[i] = this.inputFrame[i] * (1 - this.noiseStrength) + clean * this.noiseStrength;
    }
    return voiceProbability;
  }

  process(inputs, outputs) {
    const input = inputs[0];
    const output = outputs[0];
    if (!output || !output[0]) return true;

    const inputChannel = input && input[0];
    const outputChannel = output[0];
    if (!inputChannel) {
      output.forEach((channel) => channel.fill(0));
      return true;
    }

    let blockEnergy = 0;
    for (let i = 0; i < inputChannel.length; i++) {
      const sample = inputChannel[i];
      blockEnergy += sample * sample;
      this.reportEnergy += sample * sample;
      this.reportSamples++;

      if (this.calibratingSamples > 0) {
        this.calibrationEnergy += sample * sample;
        this.calibrationCount++;
        this.calibratingSamples--;
        if (this.calibratingSamples === 0) {
          const noiseRms = Math.sqrt(this.calibrationEnergy / Math.max(1, this.calibrationCount));
          this.gateThreshold = Math.max(0.00035, Math.min(0.08, noiseRms * 2.4));
          this.port.postMessage({ type: 'calibration', progress: 1, threshold: this.gateThreshold });
        }
      }

      this.inputFrame[this.frameIndex] = sample;
      outputChannel[i] = this.outputFrame[this.frameIndex];
      this.frameIndex++;
      if (this.frameIndex === this.frameSize) {
        const voiceProbability = this.processRNNoise();
        this.frameIndex = 0;
        this.lastVoiceProbability = voiceProbability;
      }
    }

    const rms = Math.sqrt(blockEnergy / Math.max(1, inputChannel.length));
    const isVoice = rms >= this.gateThreshold &&
      (!this.voiceIsolation || this.lastVoiceProbability === undefined || this.lastVoiceProbability >= 0.22);
    const targetGain = !this.gateEnabled || isVoice ? 1 : 0;
    const smoothing = targetGain > this.gateGain ? 0.002 : 0.00025;
    let gain = this.gateGain;
    for (let i = 0; i < outputChannel.length; i++) {
      gain += (targetGain - gain) * smoothing;
      outputChannel[i] *= gain;
    }
    this.gateGain = gain;

    for (let channel = 1; channel < output.length; channel++) {
      output[channel].set(outputChannel);
    }

    if (this.reportSamples >= this.samplesToReport) {
      this.port.postMessage({
        type: 'meter',
        rms: Math.sqrt(this.reportEnergy / this.reportSamples),
        voiceProbability: this.lastVoiceProbability ?? null,
        gateOpen: targetGain === 1
      });
      this.reportSamples = 0;
      this.reportEnergy = 0;
    }

    if (this.calibratingSamples > 0 && this.calibrationCount % Math.round(sampleRate / 4) < inputChannel.length) {
      this.port.postMessage({ type: 'calibration', progress: 1 - this.calibratingSamples / (sampleRate * 3) });
    }
    return true;
  }
}

registerProcessor('tmz-audio-processor', TMZAudioProcessor);
