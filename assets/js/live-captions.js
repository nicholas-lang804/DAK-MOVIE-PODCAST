(function () {
  const liveId = document.getElementById('adminLiveCaptions')?.dataset.liveId ||
    document.getElementById('liveCaptionPanel')?.dataset.liveId;
  if (!liveId) return;

  const adminLines = document.getElementById('adminLiveCaptionLines');
  const viewerLines = document.getElementById('liveCaptionLines');
  const lines = adminLines || viewerLines;
  const adminStatus = document.getElementById('adminCaptionStatus');
  const speakerStatus = document.getElementById('speakerCaptionStatus');
  const startButton = document.getElementById('toggleAdminCaptions') ||
    document.getElementById('toggleSpeakerCaptions');
  const languageSelect = document.getElementById('adminCaptionLanguage');
  const csrf = document.querySelector('#liveStudioCsrf input[name="csrf_token"]')?.value ||
    document.getElementById('liveCsrfToken')?.value || '';
  const captionPanel = document.getElementById('liveCaptionPanel');
  const player = document.getElementById('livekitPlayer');
  const captionToggle = document.getElementById('liveCaptionsEnabled');
  const sizeSelect = document.getElementById('liveCaptionSize');
  const contrastToggle = document.getElementById('liveCaptionContrast');
  const positionSelect = document.getElementById('liveCaptionPosition');
  const searchForm = document.getElementById('adminTranscriptSearch') ||
    document.getElementById('liveTranscriptSearch');
  const searchInput = document.getElementById('adminTranscriptQuery') ||
    document.getElementById('liveTranscriptQuery');
  const transcriptResults = document.getElementById('adminTranscriptResults') ||
    document.getElementById('liveTranscriptResults');
  const isAdmin = Boolean(adminLines);
  const recognitionConstructor = window.SpeechRecognition || window.webkitSpeechRecognition;
  let recognition = null;
  let recognitionEnabled = false;
  let interimNode = null;
  const captionLines = [];
  const seenCaptionKeys = new Set();
  const speakerColors = ['#9fc2ff', '#ffd18b', '#a8e6bf', '#e4b2ff', '#ffadb6'];

  function speakerColor(name) {
    let hash = 0;
    for (let index = 0; index < name.length; index += 1) {
      hash = (hash * 31 + name.charCodeAt(index)) | 0;
    }
    return speakerColors[Math.abs(hash) % speakerColors.length];
  }

  function setStatus(message) {
    if (adminStatus) adminStatus.textContent = message;
    if (speakerStatus) speakerStatus.textContent = message;
  }

  function showCaption(text, speaker, isFinal, key) {
    if (!lines || !text.trim()) return;
    if (key && seenCaptionKeys.has(key)) return;
    if (isFinal && key) seenCaptionKeys.add(key);
    if (!isFinal && interimNode) interimNode.remove();
    const row = document.createElement('p');
    row.className = 'live-caption-line' + (isFinal ? '' : ' is-interim');
    const name = document.createElement('strong');
    name.textContent = speaker || 'Speaker';
    name.style.setProperty('--speaker-color', speakerColor(name.textContent));
    const content = document.createElement('span');
    content.textContent = text;
    row.append(name, content);
    if (isFinal) {
      interimNode?.remove();
      interimNode = null;
      captionLines.push(row);
      if (captionLines.length > 2) captionLines.shift().remove();
      lines.querySelector('.live-caption-placeholder')?.remove();
      lines.appendChild(row);
    } else {
      interimNode = row;
      lines.appendChild(row);
    }
    lines.scrollTop = lines.scrollHeight;
  }

  function decode(payload) {
    try {
      return JSON.parse(new TextDecoder().decode(payload));
    } catch (error) {
      return null;
    }
  }

  function currentLanguage() {
    return languageSelect?.value || 'en-US';
  }

  function persistCaption(text, language) {
    const room = window.tmzLiveRoom;
    if (!room || room.state !== 'connected') return Promise.reject(new Error('Connect to the live room before starting captions.'));
    const payload = {
      type: 'caption',
      text,
      language,
      final: true,
      captionKey: room.localParticipant.identity + ':' + Date.now() + ':' + Math.random().toString(36).slice(2, 8)
    };
    const packet = new TextEncoder().encode(JSON.stringify(payload));
    return room.localParticipant.publishData(packet, { reliable: true, topic: 'captions' })
      .then(async function () {
        const body = new URLSearchParams({
          live_id: liveId,
          text,
          language,
          csrf_token: csrf
        });
        const response = await fetch('/TMZ%20PODCAST/api/live-captions.php', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body
        });
        const result = await response.json();
        if (!response.ok) throw new Error(result.error || 'Caption could not be saved to the transcript.');
        showCaption(text, result.line?.speaker_name || room.localParticipant.name || 'Speaker', true, payload.captionKey);
      });
  }

  function broadcastInterim(text, language) {
    const room = window.tmzLiveRoom;
    if (!room || room.state !== 'connected') return;
    const payload = new TextEncoder().encode(JSON.stringify({ type: 'caption', text, language, final: false }));
    room.localParticipant.publishData(payload, { reliable: false, topic: 'captions' }).catch(function (error) {
      console.warn('Interim caption could not be sent:', error);
    });
  }

  function startRecognition() {
    if (!recognitionConstructor) {
      setStatus('This browser does not support live speech recognition.');
      return;
    }
    if (!window.tmzLiveRoom || window.tmzLiveRoom.state !== 'connected') {
      setStatus('Connect to the live room before starting captions.');
      return;
    }
    if (speakerStatus && (!window.tmzLiveSpeakerTrack || window.tmzLiveSpeakerTrack.isMuted)) {
      setStatus('Hold the push-to-talk button before starting speaker captions.');
      return;
    }
    recognition = new recognitionConstructor();
    recognition.continuous = true;
    recognition.interimResults = true;
    recognition.lang = currentLanguage();
    recognition.onresult = function (event) {
      let interim = '';
      const finalSegments = [];
      for (let index = event.resultIndex; index < event.results.length; index += 1) {
        const result = event.results[index];
        const text = result[0]?.transcript?.trim() || '';
        if (!text) continue;
        if (result.isFinal) finalSegments.push(text);
        else interim += text;
      }
      if (interim) {
        showCaption(interim, window.tmzLiveRoom.localParticipant.name || 'Speaker', false);
        broadcastInterim(interim, recognition.lang);
      }
      if (finalSegments.length) {
        persistCaption(finalSegments.join(' '), recognition.lang).catch(function (error) {
          setStatus(error.message);
        });
      }
    };
    recognition.onerror = function (event) {
      setStatus(event.error === 'not-allowed'
        ? 'Microphone access is required for captions.'
        : 'Caption recognition stopped: ' + event.error);
      recognitionEnabled = false;
      updateButton();
    };
    recognition.onend = function () {
      if (!recognitionEnabled) return;
      window.setTimeout(function () {
        if (recognitionEnabled && window.tmzLiveRoom?.state === 'connected') {
          try { recognition.start(); } catch (error) { console.warn('Caption recognition restart failed:', error); }
        }
      }, 250);
    };
    try {
      recognition.start();
      recognitionEnabled = true;
      updateButton();
      setStatus('Captions are listening on this device.');
    } catch (error) {
      recognitionEnabled = false;
      setStatus(error.message || 'Could not start browser speech recognition.');
    }
  }

  function stopRecognition() {
    recognitionEnabled = false;
    if (recognition) recognition.stop();
    recognition = null;
    interimNode?.remove();
    interimNode = null;
    updateButton();
    setStatus('Captions off');
  }

  function updateButton() {
    if (!startButton) return;
    startButton.textContent = recognitionEnabled ? 'Stop live captions' : 'Start live captions';
    startButton.setAttribute('aria-pressed', recognitionEnabled ? 'true' : 'false');
  }

  async function loadTranscript(query) {
    if (!transcriptResults) return;
    transcriptResults.textContent = 'Loading transcript…';
    const params = new URLSearchParams({ live_id: liveId });
    if (query) params.set('q', query);
    try {
      const response = await fetch('/TMZ%20PODCAST/api/live-captions.php?' + params.toString(), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Could not load transcript.');
      transcriptResults.replaceChildren();
      if (!data.lines.length) {
        transcriptResults.textContent = 'No transcript lines found yet.';
        return;
      }
      data.lines.forEach(function (line) {
        const item = document.createElement('article');
        item.className = 'live-transcript-line';
        const stamp = document.createElement('time');
        stamp.textContent = formatClock(line.start_time_ms);
        const speaker = document.createElement('strong');
        speaker.textContent = line.speaker_name;
        if (data.can_correct) {
          const form = document.createElement('form');
          form.className = 'live-transcript-correction';
          const input = document.createElement('input');
          input.type = 'text';
          input.maxLength = 1200;
          input.required = true;
          input.value = line.caption_text;
          input.setAttribute('aria-label', 'Correct caption line');
          const save = document.createElement('button');
          save.type = 'submit';
          save.className = 'btn btn-outline';
          save.textContent = 'Save correction';
          form.append(input, save);
          form.addEventListener('submit', async function (event) {
            event.preventDefault();
            save.disabled = true;
            try {
              const body = new URLSearchParams({
                live_id: liveId,
                caption_id: String(line.id),
                text: input.value.trim(),
                csrf_token: csrf
              });
              const update = await fetch('/TMZ%20PODCAST/api/live-captions.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body
              });
              const result = await update.json();
              if (!update.ok) throw new Error(result.error || 'Caption correction could not be saved.');
              setStatus('Transcript correction saved.');
            } catch (error) {
              setStatus(error.message);
            } finally {
              save.disabled = false;
            }
          });
          item.append(stamp, speaker, form);
        } else {
          const text = document.createElement('p');
          text.textContent = line.caption_text;
          item.append(stamp, speaker, text);
        }
        transcriptResults.appendChild(item);
      });
    } catch (error) {
      transcriptResults.textContent = error.message;
    }
  }

  function formatClock(milliseconds) {
    const total = Math.floor(Math.max(0, Number(milliseconds) || 0) / 1000);
    return String(Math.floor(total / 3600)).padStart(2, '0') + ':' +
      String(Math.floor((total % 3600) / 60)).padStart(2, '0') + ':' +
      String(total % 60).padStart(2, '0');
  }

  window.addEventListener('tmz-live-data', function (event) {
    const data = decode(event.detail.payload);
    if (!data || data.type !== 'caption' || typeof data.text !== 'string' || data.text.length > 1200) return;
    const sources = event.detail.participant?.permissions?.canPublishSources;
    const microphoneSource = LivekitClient.Track.Source.Microphone;
    if (!Array.isArray(sources) || !sources.some((source) =>
      source === microphoneSource || source === 2 || String(source).toUpperCase() === 'MICROPHONE')) return;
    showCaption(
      data.text,
      event.detail.participant?.name || event.detail.participant?.identity || 'Speaker',
      data.final === true,
      data.captionKey
    );
  });

  startButton?.addEventListener('click', function () {
    if (recognitionEnabled) stopRecognition();
    else startRecognition();
  });
  window.addEventListener('tmz-push-to-talk', function (event) {
    if (speakerStatus && !event.detail?.enabled && recognitionEnabled) stopRecognition();
  });

  searchForm?.addEventListener('submit', function (event) {
    event.preventDefault();
    loadTranscript(searchInput?.value.trim() || '');
  });

  captionToggle?.addEventListener('change', function () {
    if (captionPanel) captionPanel.classList.toggle('captions-hidden', !captionToggle.checked);
  });
  sizeSelect?.addEventListener('change', function () {
    viewerLines?.setAttribute('data-size', sizeSelect.value);
  });
  contrastToggle?.addEventListener('change', function () {
    viewerLines?.classList.toggle('is-high-contrast', contrastToggle.checked);
  });
  positionSelect?.addEventListener('change', function () {
    if (!viewerLines || !captionPanel || !player) return;
    const overlay = positionSelect.value === 'overlay';
    player.classList.toggle('has-caption-overlay', overlay);
    if (overlay) player.appendChild(viewerLines);
    else captionPanel.insertBefore(viewerLines, captionPanel.querySelector('.live-caption-note'));
    viewerLines.classList.toggle('is-overlay-caption', overlay);
  });

  window.addEventListener('tmz-live-started', function () {
    if (adminLines && recognitionEnabled && recognition) {
      try { recognition.stop(); } catch (error) { console.warn('Could not restart captions after connection:', error); }
    }
  });
  window.addEventListener('beforeunload', stopRecognition);

  if (transcriptResults) loadTranscript('');
  if (adminLines && !recognitionConstructor) setStatus('Live speech recognition is unavailable in this browser.');
})();
