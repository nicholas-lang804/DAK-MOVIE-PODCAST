(function () {
  const studio = document.querySelector('.live-studio');
  const liveId = Number(studio?.dataset.liveId || 0);
  const scheduled = studio?.dataset.liveStatus === 'scheduled';
  const status = document.getElementById('livekitStatus');
  const preview = document.getElementById('cameraPreview');
  const quality = document.getElementById('connectionQuality');
  const cameraButton = document.getElementById('cameraButton');
  const remoteGrid = document.getElementById('liveAdminVideoGrid');
  const clearSpotlightButton = document.getElementById('clearLiveSpotlight');
  const csrf = document.querySelector('#liveStudioCsrf input[name="csrf_token"]')?.value || '';
  let room = null;
  let tracks = [];
  let localStream = null;
  const remoteTiles = new Map();
  let spotlightIdentity = null;

  function facingFromLabel(label) {
    const l = String(label || '').toLowerCase();
    if (/back|rear|environment/.test(l)) return 'environment';
    if (/front|user|face/.test(l)) return 'user';
    return '';
  }

  /** After mic/camera permission is granted, device labels become readable — relabel the selects with Front/Back hints. */
  async function refreshDeviceLabels() {
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const fill = (id, kind) => {
        const sel = document.getElementById(id);
        if (!sel) return;
        const previous = sel.value;
        const first = sel.querySelector('option');
        const keep = first ? first.value : '';
        sel.textContent = '';
        if (keep !== undefined) { const o = document.createElement('option'); o.value = keep; o.textContent = kind === 'videoinput' ? 'Default camera' : 'Default microphone'; sel.appendChild(o); }
        devices.filter((d) => d.kind === kind).forEach((d, i) => {
          const o = document.createElement('option'); o.value = d.deviceId;
          const facing = kind === 'videoinput' ? facingFromLabel(d.label) : '';
          const tag = facing === 'environment' ? ' · Back camera' : (facing === 'user' ? ' · Front camera' : '');
          o.textContent = (d.label || (kind === 'videoinput' ? 'Camera ' + (i + 1) : 'Microphone ' + (i + 1))) + tag;
          sel.appendChild(o);
        });
        if (previous && Array.prototype.some.call(sel.options, (op) => op.value === previous)) sel.value = previous;
      };
      fill('cameraSelect', 'videoinput');
      fill('microphoneSelect', 'audioinput');
    } catch (e) { /* labels stay generic */ }
  }

  function participantTile(participant) {
    if (!remoteGrid) return null;
    const identity = participant.identity;
    let tile = remoteTiles.get(identity);
    if (tile) return tile;

    if (remoteTiles.size === 0) remoteGrid.replaceChildren();
    tile = document.createElement('article');
    tile.className = 'live-admin-video-tile';
    tile.dataset.participantIdentity = identity;

    const media = document.createElement('div');
    media.className = 'live-admin-video-media';
    const empty = document.createElement('span');
    empty.className = 'live-admin-video-empty';
    empty.textContent = 'Camera is off';
    media.appendChild(empty);

    const footer = document.createElement('div');
    footer.className = 'live-admin-video-footer';
    const name = document.createElement('strong');
    name.textContent = participant.name || identity;
    const actions = document.createElement('div');
    actions.className = 'live-participant-actions';
    const fullscreen = document.createElement('button');
    fullscreen.type = 'button';
    fullscreen.className = 'btn btn-outline';
    fullscreen.textContent = 'Full-screen view';
    fullscreen.addEventListener('click', async () => {
      const video = media.querySelector('video.live-admin-remote-video');
      try {
        if (video?.requestFullscreen) await video.requestFullscreen();
        else if (tile.requestFullscreen) await tile.requestFullscreen();
        else setStatus('Full screen is unavailable in this browser.');
      } catch (error) {
        console.error('Could not open participant video full screen:', error);
        setStatus(error.message || 'Could not open participant video full screen.');
      }
    });
    const spotlight = document.createElement('button');
    spotlight.type = 'button';
    spotlight.className = 'btn btn-primary';
    spotlight.textContent = 'Spotlight for viewers';
    spotlight.addEventListener('click', () => publishSpotlight(identity, participant.name || identity));
    const manage = document.createElement('button');
    manage.type = 'button';
    manage.className = 'btn btn-outline';
    manage.textContent = 'Manage access';
    manage.addEventListener('click', () => {
      const card = Array.from(document.querySelectorAll('.live-participant-card'))
        .find((item) => item.dataset.participantIdentity === identity);
      if (!card) {
        setStatus('Participant controls are loading. Try again in a moment.');
        return;
      }
      card.scrollIntoView({ behavior: 'smooth', block: 'center' });
      card.classList.add('is-video-control-target');
      window.setTimeout(() => card.classList.remove('is-video-control-target'), 1800);
    });
    actions.append(fullscreen, spotlight, manage);
    footer.append(name, actions);
    tile.append(media, footer);
    remoteGrid.appendChild(tile);
    remoteTiles.set(identity, tile);
    return tile;
  }

  function removeParticipantTile(identity) {
    const tile = remoteTiles.get(identity);
    if (!tile) return;
    tile.remove();
    remoteTiles.delete(identity);
    if (spotlightIdentity === identity) publishSpotlight(null, '');
    if (remoteGrid && remoteTiles.size === 0) {
      const empty = document.createElement('p');
      empty.className = 'form-help';
      empty.textContent = 'Waiting for participants to publish audio or video.';
      remoteGrid.replaceChildren(empty);
    }
  }

  async function publishSpotlight(identity, name, destinationIdentities) {
    if (!room || room.state !== 'connected') {
      setStatus('Connect the studio before changing the audience layout.');
      return;
    }
    try {
      const publishOptions = { reliable: true, topic: 'live-spotlight' };
      if (destinationIdentities) publishOptions.destinationIdentities = destinationIdentities;
      await room.localParticipant.publishData(
        new TextEncoder().encode(JSON.stringify({
          type: 'live_spotlight',
          identity,
          layout: identity ? 'speaker' : 'gallery'
        })),
        publishOptions
      );
      spotlightIdentity = identity;
      remoteGrid?.querySelectorAll('.live-admin-video-tile').forEach((tile) => {
        const spotlightButton = tile.querySelector('button');
        const isSpotlighted = tile.dataset.participantIdentity === spotlightIdentity;
        spotlightButton.textContent = isSpotlighted ? 'Spotlighted for viewers' : 'Spotlight for viewers';
        spotlightButton.setAttribute('aria-pressed', isSpotlighted ? 'true' : 'false');
      });
      if (identity) setStatus(name + ' is now spotlighted for viewers. Viewers can switch back to gallery.');
      else setStatus('Viewers can now choose their own gallery or speaker layout.');
    } catch (error) {
      console.error('Could not update the audience video layout:', error);
      setStatus(error.message || 'Could not update the audience video layout.');
    }
  }

  function attachRemoteTrack(track, publication, participant) {
    const tile = participantTile(participant);
    if (!tile) return;
    const media = tile.querySelector('.live-admin-video-media');
    const trackSid = publication?.trackSid || track.sid || '';
    if (trackSid && media.querySelector('[data-live-track-sid="' + trackSid + '"]')) return;
    const empty = media.querySelector('.live-admin-video-empty');
    if (empty && track.kind === LivekitClient.Track.Kind.Video) empty.remove();
    const element = track.attach();
    element.autoplay = true;
    element.playsInline = true;
    if (track.kind === LivekitClient.Track.Kind.Video) {
      element.muted = true;
      element.classList.add('live-admin-remote-video');
      element.dataset.liveParticipantIdentity = participant.identity;
      element.dataset.liveSource = publication?.source || track.source || '';
      element.dataset.liveTrackSid = trackSid;
    } else {
      element.classList.add('live-admin-remote-audio');
      element.dataset.liveTrackSid = trackSid;
    }
    media.appendChild(element);
  }

  function detachRemoteTrack(track, participant) {
    track.detach().forEach((element) => element.remove());
    const tile = remoteTiles.get(participant.identity);
    if (!tile) return;
    const media = tile.querySelector('.live-admin-video-media');
    if (!media.querySelector('video.live-admin-remote-video')) {
      const empty = document.createElement('span');
      empty.className = 'live-admin-video-empty';
      empty.textContent = 'Camera is off';
      media.prepend(empty);
    }
  }

  function setStatus(message) {
    if (!status) return;
    status.textContent = message;
    status.dataset.state = message.toLowerCase();
  }

  function stopLocalStream() {
    localStream?.getTracks().forEach((track) => track.stop());
    localStream = null;
    if (preview) {
      preview.srcObject = null;
      preview.hidden = true;
    }
  }

  const QUALITY_PRESETS = {
    hd: { width: 1280, height: 720, frameRate: 30 },
    fullhd: { width: 1920, height: 1080, frameRate: 30 },
    sd: { width: 854, height: 480, frameRate: 30 },
    data: { width: 640, height: 360, frameRate: 24 }
  };
  function qualityPreset() {
    return QUALITY_PRESETS[document.getElementById('videoQuality')?.value] || QUALITY_PRESETS.hd;
  }
  function videoConstraints(cameraId) {
    const q = qualityPreset();
    return {
      deviceId: cameraId ? { exact: cameraId } : undefined,
      width: { ideal: q.width },
      height: { ideal: q.height },
      frameRate: { ideal: q.frameRate, max: q.frameRate }
    };
  }

  async function refreshPreviewStream() {
    if (!preview) return;
    try {
      const camId = document.getElementById('cameraSelect')?.value || '';
      const s = await navigator.mediaDevices.getUserMedia({ video: videoConstraints(camId), audio: false });
      preview.srcObject = s;
      preview.hidden = false;
    } catch (e) { console.warn('Preview refresh failed:', e); }
  }

  async function start() {
    if (!liveId) {
      window.alert('Prepare a live room first.');
      return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
      setStatus('HTTPS REQUIRED');
      window.alert('Camera and microphone access requires HTTPS on another computer. Use localhost on the XAMPP computer or configure HTTPS for the LAN address.');
      return;
    }

    cameraButton.disabled = true;
    setStatus('CONNECTING');
    const cameraId = document.getElementById('cameraSelect')?.value;
    const audioId = document.getElementById('microphoneSelect')?.value;

    try {
      localStream = await navigator.mediaDevices.getUserMedia({
        video: videoConstraints(cameraId),
        audio: {
          deviceId: audioId ? { exact: audioId } : undefined,
          channelCount: 1,
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        }
      });
      preview.srcObject = localStream;
      preview.hidden = false;
      refreshDeviceLabels();

      const body = new URLSearchParams({
        live_id: String(liveId),
        role: 'publisher',
        csrf_token: csrf
      });
      const response = await fetch('/TMZ%20PODCAST/api/livekit-token.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Could not create the broadcast token.');

      room = new LivekitClient.Room({
        adaptiveStream: true,
        dynacast: true,
        videoCaptureDefaults: {
          resolution: qualityPreset(),
          deviceId: cameraId || undefined
        },
        publishDefaults: {
          simulcast: true,
          videoCodec: 'vp8',
          degradationPreference: 'balanced',
          videoEncoding: { maxBitrate: 1500000, maxFramerate: 30 },
          videoSimulcastLayers: [
            { width: 640, height: 360, encoding: { maxBitrate: 500000, maxFramerate: 20 } },
            { width: 320, height: 180, encoding: { maxBitrate: 150000, maxFramerate: 15 } }
          ]
        }
      });
      room.on(LivekitClient.RoomEvent.Disconnected, () => setStatus('DISCONNECTED'));
      room.on(LivekitClient.RoomEvent.Reconnecting, () => setStatus('RECONNECTING'));
      room.on(LivekitClient.RoomEvent.Reconnected, () => setStatus('LIVE'));
      room.on(LivekitClient.RoomEvent.ConnectionQualityChanged, (value) => {
        if (quality) quality.textContent = 'Quality: ' + String(value);
      });
      room.on(LivekitClient.RoomEvent.ParticipantConnected, (participant) => {
        if (spotlightIdentity) publishSpotlight(spotlightIdentity, '', [participant.identity]);
      });
      room.on(LivekitClient.RoomEvent.ParticipantDisconnected, (participant) => removeParticipantTile(participant.identity));
      room.on(LivekitClient.RoomEvent.TrackSubscribed, attachRemoteTrack);
      room.on(LivekitClient.RoomEvent.TrackUnsubscribed, detachRemoteTrack);
      room.on(LivekitClient.RoomEvent.DataReceived, (payload, participant, kind, topic) => {
        window.dispatchEvent(new CustomEvent('tmz-live-data', {
          detail: { payload, participant, kind, topic }
        }));
      });

      await room.connect(data.url, data.token, {
        autoSubscribe: true,
        maxRetries: 4,
        websocketTimeout: 20000,
        peerConnectionTimeout: 20000
      });
      window.tmzLiveRoom = room;
      room.startAudio().catch((audioError) => console.warn('Room audio needs a tap to start:', audioError));
      room.remoteParticipants.forEach((participant) => {
        participant.trackPublications?.forEach((publication) => {
          if (publication.track && publication.isSubscribed) {
            attachRemoteTrack(publication.track, publication, participant);
          }
        });
      });
      await publishSpotlight(null, '');
      const selectedCamLabel = document.getElementById('cameraSelect')?.selectedOptions?.[0]?.textContent || '';
      const camFacing = facingFromLabel(selectedCamLabel);
      tracks = [
        await LivekitClient.createLocalAudioTrack({
          deviceId: audioId || undefined,
          echoCancellation: true,
          noiseSuppression: true
        }),
        await LivekitClient.createLocalVideoTrack({
          deviceId: cameraId || undefined,
          resolution: qualityPreset(),
          facingMode: cameraId ? (camFacing || undefined) : 'user'
        })
      ];
      for (const track of tracks) {
        if (track.kind === 'video') {
          await room.localParticipant.publishTrack(track, {
            simulcast: true,
            videoCodec: 'vp8',
            degradationPreference: 'balanced',
            videoEncoding: { maxBitrate: 1500000, maxFramerate: 30 },
            videoSimulcastLayers: [
              { width: 640, height: 360, encoding: { maxBitrate: 500000, maxFramerate: 20 } },
              { width: 320, height: 180, encoding: { maxBitrate: 150000, maxFramerate: 15 } }
            ]
          });
        } else {
          await room.localParticipant.publishTrack(track, { red: true, stopMicTrackOnMute: false });
        }
      }
      setStatus('LIVE');
      cameraButton.textContent = 'Connected';
      window.dispatchEvent(new Event('tmz-live-started'));
    } catch (error) {
      console.error('Live Studio connection failed:', error);
      setStatus('ERROR');
      room?.disconnect();
      room = null;
      window.tmzLiveRoom = null;
      stopLocalStream();
      window.alert(error.message);
    } finally {
      cameraButton.disabled = false;
    }
  }

  cameraButton?.addEventListener('click', start);

  /* Live device & quality switching — applies instantly while broadcasting. */
  async function switchDevice(kind, deviceId) {
    if (!room || room.state !== 'connected' || typeof room.switchActiveDevice !== 'function' || !deviceId) return;
    try {
      await room.switchActiveDevice(kind, deviceId);
      if (kind === 'videoinput') refreshPreviewStream();
      else refreshDeviceLabels();
      setStatus('LIVE');
    } catch (e) {
      console.warn('Device switch failed:', e);
      setStatus('ERROR');
      window.setTimeout(() => setStatus(room?.state === 'connected' ? 'LIVE' : 'OFFLINE'), 1500);
    }
  }
  document.getElementById('cameraSelect')?.addEventListener('change', (e) => switchDevice('videoinput', e.target.value));
  document.getElementById('microphoneSelect')?.addEventListener('change', (e) => switchDevice('audioinput', e.target.value));
  document.getElementById('videoQuality')?.addEventListener('change', async () => {
    if (!room || room.state !== 'connected') return;
    const pub = room.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera);
    const track = pub && pub.track;
    if (track && typeof track.restart === 'function') {
      try { await track.restart(qualityPreset()); setStatus('LIVE'); }
      catch (e) { console.warn('Quality change failed:', e); }
    }
  });
  clearSpotlightButton?.addEventListener('click', () => publishSpotlight(null, ''));
  document.getElementById('muteMic')?.addEventListener('click', (event) => {
    const track = tracks.find((item) => item.kind === 'audio');
    if (!track) return;
    if (track.isMuted) track.unmute();
    else track.mute();
    event.currentTarget.textContent = track.isMuted ? 'Unmute microphone' : 'Mute microphone';
  });
  document.getElementById('toggleCamera')?.addEventListener('click', (event) => {
    const track = tracks.find((item) => item.kind === 'video');
    if (!track) return;
    if (track.isMuted) track.unmute();
    else track.mute();
    event.currentTarget.textContent = track.isMuted ? 'Turn camera on' : 'Turn camera off';
  });
  document.getElementById('liveAccess')?.addEventListener('change', (event) => {
    document.getElementById('livePrice').hidden = event.target.value !== 'paid';
  });
  window.addEventListener('beforeunload', () => {
    if (room) room.disconnect();
  });
  if (scheduled) setStatus('READY');
})();
