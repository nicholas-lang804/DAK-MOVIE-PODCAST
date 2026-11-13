(function () {
  const box = document.getElementById('livekitPlayer');
  const stage = document.getElementById('livePlayerStage');
  const prejoin = document.getElementById('livePrejoin');
  const joinButton = document.getElementById('joinLiveRoom');
  const status = document.getElementById('livekitViewerStatus');
  const joinStatus = document.getElementById('liveJoinStatus');
  const retryButton = document.getElementById('retryLiveRoom');
  const feed = document.getElementById('liveChatFeed');
  const chatForm = document.getElementById('liveChatForm');
  const actionsForm = document.getElementById('liveActionsForm');
  const inviteDialog = document.getElementById('liveSpeakerInvite');
  const inviteTitle = document.getElementById('liveSpeakerInviteTitle');
  const inviteMessage = document.getElementById('liveSpeakerInviteMessage');
  const acceptInviteButton = document.getElementById('acceptLiveSpeakerInvite');
  const declineInviteButton = document.getElementById('declineLiveSpeakerInvite');
  const closeSpeakerErrorButton = document.getElementById('closeLiveSpeakerError');
  const inviteActions = inviteDialog?.querySelector('div');
  const pushToTalkPanel = document.getElementById('livePushToTalk');
  const pushToTalkButton = document.getElementById('livePushToTalkButton');
  const talkDuration = document.getElementById('liveTalkDuration');
  const leaveStageButton = document.getElementById('leaveLiveStage');
  const layoutButtons = document.querySelectorAll('[data-live-layout]');
  const callBar = document.getElementById('liveCallBar');
  const micToggle = document.getElementById('liveMicToggle');
  const camToggle = document.getElementById('liveCamToggle');
  const enableSoundButton = document.getElementById('liveEnableSound');
  const callNote = document.getElementById('liveCallNote');
  const roster = document.getElementById('liveRoster');
  if (!box || !joinButton) return;

  if (!window.LivekitClient) {
    const message = 'The live player did not load. Check your connection, then reload this page.';
    if (joinStatus) joinStatus.textContent = message;
    joinButton.disabled = true;
    const error = document.createElement('p');
    error.className = 'live-join-error';
    error.textContent = message;
    prejoin?.appendChild(error);
    return;
  }

  const liveId = box.dataset.liveId;
  const csrf = document.getElementById('liveCsrfToken')?.value || '';
  let room = null;
  let speakerTrack = null;
  let activeInvitation = null;
  let acceptedSpeakerRequestId = null;
  let invitationPoll = null;
  let layout = 'gallery';
  let selectedVideo = null;
  let spotlightIdentity = null;
  let talkStartedAt = 0;
  let talkTimer = null;
  let talkToggleBusy = false;
  let livekitHost = '';
  let micOn = false;
  let camOn = false;
  let micBusy = false;
  let camBusy = false;
  let lastMicAllowed = null;
  let lastCamAllowed = null;
  let requestedWhileBlocked = { audio: false, video: false };
  let localCameraElement = null;
  let speakingIdentities = new Set();
  let rosterTimer = null;

  /* Device & quality preferences (Devices panel in the call bar). */
  const devicePrefs = { video: '', audio: '', quality: 'sd' };
  const devicePanel = document.getElementById('liveDevicePanel');
  const deviceBtn = document.getElementById('liveDeviceSettingsBtn');
  const camSelect = document.getElementById('liveCameraSelect');
  const micSelect = document.getElementById('liveMicSelect');
  const qualitySelect = document.getElementById('liveVideoQuality');

  function facingFromLabel(label) {
    const l = String(label || '').toLowerCase();
    if (/back|rear|environment/.test(l)) return 'environment';
    if (/front|user|face/.test(l)) return 'user';
    return '';
  }

  function qualityCapture() {
    if (devicePrefs.quality === 'hd') return { resolution: { width: 1280, height: 720, frameRate: 30 } };
    if (devicePrefs.quality === 'data') return { resolution: { width: 320, height: 180, frameRate: 15 } };
    return { resolution: { width: 640, height: 360, frameRate: 24 } };
  }

  /** Fill the device dropdowns. Labels only appear after camera/mic permission, so re-run after media is enabled. */
  async function fillDeviceSelects() {
    if (!camSelect || !micSelect || !navigator.mediaDevices?.enumerateDevices) return;
    try {
      const devices = await navigator.mediaDevices.enumerateDevices();
      const fill = (sel, kind, emptyLabel) => {
        if (!sel) return;
        const prev = sel.value;
        sel.textContent = '';
        const d = document.createElement('option'); d.value = ''; d.textContent = emptyLabel; sel.appendChild(d);
        let i = 0;
        devices.filter((x) => x.kind === kind).forEach((dev) => {
          i++;
          const o = document.createElement('option'); o.value = dev.deviceId;
          const facing = kind === 'videoinput' ? facingFromLabel(dev.label) : '';
          const tag = facing === 'environment' ? ' · Back camera' : (facing === 'user' ? ' · Front camera' : '');
          o.textContent = (dev.label || (kind === 'videoinput' ? 'Camera ' + i : 'Microphone ' + i)) + tag;
          sel.appendChild(o);
        });
        if (prev && Array.prototype.some.call(sel.options, (op) => op.value === prev)) sel.value = prev;
      };
      fill(camSelect, 'videoinput', 'Default camera');
      fill(micSelect, 'audioinput', 'Default microphone');
    } catch (e) { /* enumeration unavailable */ }
  }

  async function applyDevice(kind, deviceId) {
    if (!room || room.state !== 'connected' || typeof room.switchActiveDevice !== 'function') return;
    try {
      if (kind === 'videoinput' && !camOn) return;   // nothing published yet
      if (kind === 'audioinput' && !micOn) return;
      await room.switchActiveDevice(kind, deviceId || undefined);
      fillDeviceSelects();
    } catch (e) {
      console.warn('Could not switch device:', e);
      setCallNote('Could not switch ' + (kind === 'videoinput' ? 'camera' : 'microphone') + ': ' + (e.message || 'device unavailable'), true);
    }
  }

  async function applyQuality() {
    if (!room || !camOn) return;
    const pub = room.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera);
    const track = pub && pub.track;
    if (track && typeof track.restart === 'function') {
      try { await track.restart(qualityCapture()); setCallNote('Video quality set to ' + qualitySelect.options[qualitySelect.selectedIndex].text + '.', false); }
      catch (e) { console.warn('Quality change failed:', e); }
    }
  }

  deviceBtn?.addEventListener('click', function () {
    if (!devicePanel) return;
    devicePanel.hidden = !devicePanel.hidden;
    deviceBtn.setAttribute('aria-expanded', devicePanel.hidden ? 'false' : 'true');
    if (!devicePanel.hidden) fillDeviceSelects();
  });
  camSelect?.addEventListener('change', function () { devicePrefs.video = camSelect.value; applyDevice('videoinput', camSelect.value); });
  micSelect?.addEventListener('change', function () { devicePrefs.audio = micSelect.value; applyDevice('audioinput', micSelect.value); });
  qualitySelect?.addEventListener('change', function () { devicePrefs.quality = qualitySelect.value; applyQuality(); });

  function updateFeaturedVideo(preferred) {
    const videos = Array.from(box.querySelectorAll('video.live-remote-video'));
    videos.forEach((video) => video.classList.remove('is-featured'));
    if (preferred && videos.includes(preferred)) selectedVideo = preferred;
    if (spotlightIdentity) {
      selectedVideo = videos.find((video) => video.dataset.liveParticipantIdentity === spotlightIdentity) || null;
    } else if (!videos.includes(selectedVideo)) selectedVideo = null;
    const featured = selectedVideo || videos.find((video) => video.dataset.liveSource === 'screen_share') || videos[0];
    selectedVideo = featured || null;
    featured?.classList.add('is-featured');
  }

  function setAudienceSpotlight(message) {
    if (message.layout === 'speaker' && (typeof message.identity !== 'string' || !message.identity)) return;
    spotlightIdentity = message.layout === 'speaker' ? message.identity : null;
    if (!spotlightIdentity) selectedVideo = null;
    setLayout(message.layout === 'speaker' ? 'speaker' : 'gallery');
  }

  function setLayout(nextLayout) {
    layout = nextLayout;
    box.classList.toggle('is-speaker-view', layout === 'speaker');
    box.classList.toggle('is-gallery-view', layout === 'gallery');
    layoutButtons.forEach((button) => {
      button.setAttribute('aria-pressed', button.dataset.liveLayout === layout ? 'true' : 'false');
    });
    updateFeaturedVideo();
  }

  function setStatus(message, state) {
    if (status) {
      status.textContent = message;
      status.dataset.state = state || message.toLowerCase();
    }
    if (joinStatus) joinStatus.textContent = message;
  }

  function connectionErrorMessage(error) {
    const message = error?.message || 'Could not connect to the live room.';
    if (/signal|websocket|timed?\s*out/i.test(message)) {
      return 'Could not reach LiveKit signaling' +
        (livekitHost ? ' at ' + livekitHost : '') +
        '. Check that this network allows secure WebSockets (WSS) over port 443, then tap Try again. If it still fails, test on a mobile hotspot.';
    }
    return message;
  }

  function appendMessage(message, name) {
    if (!feed) return;
    const row = document.createElement('div');
    row.className = 'live-message-item';
    const fallback = document.createElement('span');
    fallback.className = 'live-avatar-fallback';
    fallback.textContent = (name || 'V').charAt(0).toUpperCase();
    const content = document.createElement('p');
    const sender = document.createElement('strong');
    const text = document.createElement('span');
    sender.textContent = name || 'Viewer';
    text.textContent = message;
    content.append(sender, text);
    row.append(fallback, content);
    feed.appendChild(row);
    feed.scrollTop = feed.scrollHeight;
  }

  function decode(payload) {
    try {
      return JSON.parse(new TextDecoder().decode(payload));
    } catch (error) {
      return null;
    }
  }

  async function postForm(form, extra) {
    const data = new FormData(form);
    Object.entries(extra || {}).forEach(function (entry) {
      data.set(entry[0], entry[1]);
    });
    const response = await fetch(form.action, {
      method: 'POST',
      body: data,
      credentials: 'same-origin',
      headers: { 'X-CSRF-Token': csrf }
    });
    if (!response.ok) throw new Error('The live-room action could not be saved.');
  }

  async function publishMessage(payload, topic) {
    await room.localParticipant.publishData(
      new TextEncoder().encode(JSON.stringify(payload)),
      { reliable: true, topic }
    );
  }

  async function invitationRequest(action, requestId) {
    const body = new URLSearchParams({
      live_id: liveId,
      request_id: String(requestId),
      action,
      csrf_token: csrf
    });
    const response = await fetch('/TMZ%20PODCAST/api/live-room-invitation.php', {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'The speaker invitation could not be updated.');
    return data;
  }

  function showNextInvitation(invitations) {
    if (!inviteDialog || activeInvitation || !invitations.length) return;
    activeInvitation = invitations[0];
    if (inviteActions) inviteActions.hidden = false;
    if (acceptInviteButton) acceptInviteButton.hidden = false;
    if (declineInviteButton) declineInviteButton.hidden = false;
    if (closeSpeakerErrorButton) closeSpeakerErrorButton.hidden = true;
    const isVideo = activeInvitation.request_type === 'video';
    if (inviteTitle) inviteTitle.textContent = isVideo ? 'You’ve been invited to turn on your camera' : 'You’ve been invited to speak';
    acceptInviteButton.textContent = isVideo ? 'Allow camera and join' : 'Allow microphone';
    inviteMessage.textContent = isVideo
      ? 'After you agree, your browser will open the camera and send your video to the host right away.'
      : 'After you agree, allow microphone access. Your mic starts muted; tap Start talking when you are ready.';
    inviteDialog.hidden = false;
    acceptInviteButton.focus();
  }

  async function pollInvitations() {
    if (!room || room.state !== 'connected') return;
    try {
      const response = await fetch('/TMZ%20PODCAST/api/live-room-invitation.php?live_id=' + encodeURIComponent(liveId), {
        credentials: 'same-origin',
        cache: 'no-store'
      });
      if (!response.ok) return;
      const data = await response.json();
      showNextInvitation(data.invitations || []);
    } catch (error) {
      console.warn('Could not check for a live speaking invitation:', error);
    }
  }

  async function stopSpeakerTrack() {
    const track = speakerTrack;
    speakerTrack = null;
    if (!track) return;
    try {
      await room.localParticipant.unpublishTrack(track, true);
    } catch (error) {
      console.warn('Could not unpublish the local speaking track:', error);
    }
    track.detach().forEach((element) => element.remove());
    track.stop();
    window.tmzLiveSpeakerTrack = null;
    window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: false } }));
    if (talkTimer) window.clearInterval(talkTimer);
    talkTimer = null;
    talkStartedAt = 0;
    talkToggleBusy = false;
    if (talkDuration) {
      talkDuration.hidden = true;
      talkDuration.textContent = '00:00';
    }
    pushToTalkPanel.hidden = true;
    pushToTalkButton.hidden = false;
    pushToTalkButton.classList.remove('is-recording');
    pushToTalkButton.setAttribute('aria-pressed', 'false');
    pushToTalkButton.textContent = 'Start talking';
    acceptedSpeakerRequestId = null;
  }

  async function respondToInvitation(accept) {
    if (!activeInvitation) return;
    const invitation = activeInvitation;
    acceptInviteButton.disabled = true;
    declineInviteButton.disabled = true;
    let serverAccepted = false;
    let setupStage = 'checking the speaker invitation';
    try {
      if (!accept) {
        await invitationRequest('decline', invitation.id);
        inviteDialog.hidden = true;
        activeInvitation = null;
        return;
      }

      await invitationRequest('accept', invitation.id);
      serverAccepted = true;
      if (invitation.request_type === 'video' && camOn) await setCamera(false);
      if (invitation.request_type !== 'video' && micOn) await setMic(false);
      const isVideo = invitation.request_type === 'video';
      setupStage = isVideo ? 'opening your camera' : 'opening your microphone';
      speakerTrack = isVideo
        ? await LivekitClient.createLocalVideoTrack({ deviceId: devicePrefs.video || undefined, facingMode: devicePrefs.video ? undefined : 'user' })
        : await LivekitClient.createLocalAudioTrack({
          deviceId: devicePrefs.audio || undefined,
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        });
      if (!isVideo) await speakerTrack.mute();
      setupStage = isVideo ? 'sending your camera to the live room' : 'publishing your microphone to the live room';
      await room.localParticipant.publishTrack(speakerTrack);
      if (!isVideo) window.tmzLiveSpeakerTrack = speakerTrack;
      if (isVideo) {
        const element = speakerTrack.attach();
        element.autoplay = true;
        element.playsInline = true;
        element.muted = true;
        element.classList.add('live-remote-video', 'live-local-video');
        element.dataset.liveParticipantIdentity = room.localParticipant.identity;
        box.appendChild(element);
      } else {
        pushToTalkButton.hidden = false;
        pushToTalkButton.focus();
      }
      acceptedSpeakerRequestId = invitation.id;
      pushToTalkPanel.hidden = false;
      pushToTalkButton.hidden = isVideo;
      inviteDialog.hidden = true;
      activeInvitation = null;
      setStatus(isVideo ? 'CAMERA LIVE · SENDING TO HOST' : 'MIC READY · TAP TO TALK', 'ready');
    } catch (error) {
      console.error('Speaker setup failed while ' + setupStage + ':', error);
      if (serverAccepted) {
        try {
          await invitationRequest('revoke', invitation.id);
        } catch (revokeError) {
          console.error('Could not revoke permission after device setup failed:', revokeError);
        }
        activeInvitation = null;
      }
      await stopSpeakerTrack();
      const isVideo = invitation.request_type === 'video';
      const device = isVideo ? 'camera' : 'microphone';
      let guidance = error.message || 'Check your browser permissions and try again.';
      if (error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError') {
        guidance = 'Browser access to the ' + device + ' was denied. Allow it in the address-bar site settings, then request to speak again.';
      } else if (error.name === 'NotFoundError' || error.name === 'DevicesNotFoundError') {
        guidance = 'No ' + device + ' was found. Connect or enable a ' + device + ', then request to speak again.';
      } else if (error.name === 'NotReadableError' || error.name === 'TrackStartError') {
        guidance = 'The ' + device + ' is busy or unavailable. Close other apps or tabs using it, then request to speak again.';
      } else if (error.name === 'OverconstrainedError' || error.name === 'ConstraintNotSatisfiedError') {
        guidance = 'The selected ' + device + ' does not support a requested setting. Choose another device or reset its browser settings.';
      } else if (error.message) {
        guidance = error.message;
      }
      inviteMessage.textContent = 'Could not finish ' + setupStage + '. ' + guidance;
      if (acceptInviteButton) acceptInviteButton.hidden = true;
      if (declineInviteButton) declineInviteButton.hidden = true;
      if (closeSpeakerErrorButton) closeSpeakerErrorButton.hidden = false;
      inviteDialog.hidden = false;
      setStatus((isVideo ? 'CAMERA' : 'MICROPHONE') + ' ERROR: ' + guidance, 'error');
    } finally {
      acceptInviteButton.disabled = false;
      declineInviteButton.disabled = false;
    }
  }

  function updateTalkDuration() {
    if (!talkDuration || !talkStartedAt) return;
    const seconds = Math.floor((Date.now() - talkStartedAt) / 1000);
    talkDuration.textContent = String(Math.floor(seconds / 60)).padStart(2, '0') + ':' +
      String(seconds % 60).padStart(2, '0');
  }

  async function setPushToTalk(enabled) {
    if (!speakerTrack || speakerTrack.kind !== 'audio' || talkToggleBusy) return;
    talkToggleBusy = true;
    pushToTalkButton.disabled = true;
    try {
      if (enabled) await speakerTrack.unmute();
      else await speakerTrack.mute();
      window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled } }));
      pushToTalkButton.setAttribute('aria-pressed', enabled ? 'true' : 'false');
      pushToTalkButton.classList.toggle('is-recording', enabled);
      pushToTalkButton.textContent = enabled ? 'Live mic on · tap to stop' : 'Start talking';
      if (enabled) {
        talkStartedAt = Date.now();
        if (talkDuration) talkDuration.hidden = false;
        updateTalkDuration();
        if (talkTimer) window.clearInterval(talkTimer);
        talkTimer = window.setInterval(updateTalkDuration, 1000);
        setStatus('Your live microphone is on. Tap the button again to mute.', 'ready');
      } else {
        if (talkTimer) window.clearInterval(talkTimer);
        talkTimer = null;
        talkStartedAt = 0;
        if (talkDuration) {
          talkDuration.hidden = true;
          talkDuration.textContent = '00:00';
        }
        setStatus('Your microphone is muted.', 'ready');
      }
    } catch (error) {
      console.error('Could not change live microphone state:', error);
      setStatus(error.message || 'Could not change microphone state.', 'error');
    } finally {
      talkToggleBusy = false;
      pushToTalkButton.disabled = false;
    }
  }

  function hasPublishSource(sources, source) {
    if (!Array.isArray(sources)) return false;
    const sourceValues = {
      [LivekitClient.Track.Source.Microphone]: 2,
      [LivekitClient.Track.Source.Camera]: 1
    };
    const sourceName = String(source).toUpperCase();
    return sources.some((value) => value === source ||
      value === sourceValues[source] ||
      String(value).toUpperCase() === sourceName);
  }

  /* ---------- Open talk: unmute to talk, switch to camera, host can mute/block ---------- */

  function localCanPublish(source) {
    const permissions = room?.localParticipant?.permissions;
    if (!permissions) return true; // not known yet: let the server decide
    if (!permissions.canPublish) return false;
    const list = permissions.canPublishSources;
    if (!Array.isArray(list) || list.length === 0) return true;
    return hasPublishSource(list, source);
  }

  function setCallNote(message, warn) {
    if (!callNote) return;
    callNote.textContent = message || '';
    callNote.dataset.tone = warn ? 'warn' : 'info';
  }

  function paintToggle(button, isOn, allowed, labels) {
    if (!button) return;
    const state = !allowed ? 'blocked' : (isOn ? 'on' : 'off');
    button.dataset.state = state;
    button.setAttribute('aria-pressed', isOn ? 'true' : 'false');
    const label = button.querySelector('.live-call-label');
    const icon = button.querySelector('.live-call-icon');
    if (label) label.textContent = labels.text[state];
    if (icon) icon.textContent = labels.icon[state];
  }

  function renderCallControls() {
    const connected = Boolean(room && room.state === 'connected');
    if (callBar) callBar.hidden = !connected;
    if (!connected) return;
    const micAllowed = localCanPublish(LivekitClient.Track.Source.Microphone);
    const camAllowed = localCanPublish(LivekitClient.Track.Source.Camera);
    paintToggle(micToggle, micOn, micAllowed, {
      text: { off: 'Unmute', on: 'Mute', blocked: 'Muted by host' },
      icon: { off: '🎤', on: '🎤', blocked: '🔇' }
    });
    paintToggle(camToggle, camOn, camAllowed, {
      text: { off: 'Start video', on: 'Stop video', blocked: 'Video off (host)' },
      icon: { off: '📷', on: '📷', blocked: '🚫' }
    });
  }

  function mediaFailureMessage(error, device) {
    const name = error?.name || '';
    if (name === 'NotAllowedError' || name === 'PermissionDeniedError') {
      return 'Your browser blocked the ' + device + '. Allow it in the address-bar site settings, then tap again.';
    }
    if (name === 'NotFoundError' || name === 'DevicesNotFoundError') {
      return 'No ' + device + ' was found. Connect or enable one, then tap again.';
    }
    if (name === 'NotReadableError' || name === 'TrackStartError') {
      return 'The ' + device + ' is busy in another app or tab. Close it, then tap again.';
    }
    if (name === 'OverconstrainedError' || name === 'ConstraintNotSatisfiedError') {
      return 'This ' + device + ' does not support the requested settings. Pick another device in your browser settings.';
    }
    if (name === 'SecureContextError') return error.message;
    return error?.message || ('Could not start the ' + device + '.');
  }

  async function dropLocalSource(source) {
    const publication = room?.localParticipant?.getTrackPublication(source);
    const track = publication?.track;
    if (!track) return;
    try {
      await room.localParticipant.unpublishTrack(track, true); // stops the device too, so the browser's mic/camera light goes off
    } catch (error) {
      console.warn('Could not unpublish the local ' + source + ' track:', error);
      try { track.stop(); } catch (stopError) { /* already stopped */ }
    }
  }

  function showLocalCamera(track) {
    hideLocalCamera();
    const element = track.attach();
    element.autoplay = true;
    element.playsInline = true;
    element.muted = true;
    element.controls = false;
    element.classList.add('live-remote-video', 'live-local-video');
    element.dataset.liveSource = 'camera';
    element.dataset.liveParticipantIdentity = room.localParticipant.identity;
    box.appendChild(element);
    localCameraElement = element;
    syncStagePlaceholder();
    updateFeaturedVideo();
  }

  function hideLocalCamera(track) {
    if (track) track.detach().forEach(function (element) { element.remove(); });
    if (localCameraElement) {
      localCameraElement.remove();
      localCameraElement = null;
    }
    syncStagePlaceholder();
    updateFeaturedVideo();
  }

  function syncStagePlaceholder() {
    const hasVideo = Boolean(box.querySelector('video'));
    const existing = box.querySelector('.stream-placeholder');
    if (hasVideo) {
      existing?.remove();
      return;
    }
    if (!existing && room && room.state === 'connected') {
      const placeholder = document.createElement('div');
      placeholder.className = 'stream-placeholder';
      placeholder.textContent = 'Nobody has a camera on right now. You can still hear everyone who is talking.';
      box.appendChild(placeholder);
    }
  }

  async function handleBlocked(kind) {
    const device = kind === 'video' ? 'camera' : 'microphone';
    const message = 'The host has turned your ' + device + ' off. You can use it again as soon as the host allows you.';
    if (actionsForm && room && !requestedWhileBlocked[kind]) {
      try {
        await postForm(actionsForm, { request_speaker: kind });
        requestedWhileBlocked[kind] = true;
        setCallNote(message + ' Your request was sent to the host.', true);
        return;
      } catch (error) {
        console.warn('Could not send the speaking request:', error);
      }
    }
    setCallNote(message, true);
  }

  async function setMic(enabled) {
    if (!room || room.state !== 'connected' || micBusy) return;
    const source = LivekitClient.Track.Source.Microphone;
    if (enabled && !localCanPublish(source)) {
      renderCallControls();
      await handleBlocked('audio');
      return;
    }
    micBusy = true;
    if (micToggle) micToggle.disabled = true;
    try {
      if (enabled) {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
          const secureError = new Error('Your microphone needs a secure (HTTPS) connection to this site.');
          secureError.name = 'SecureContextError';
          throw secureError;
        }
        if (speakerTrack && speakerTrack.kind === 'audio') await stopSpeakerTrack();
        await room.localParticipant.setMicrophoneEnabled(true, {
          deviceId: devicePrefs.audio || undefined,
          echoCancellation: true,
          noiseSuppression: true,
          autoGainControl: true
        });
        micOn = true;
        fillDeviceSelects();
        window.tmzLiveSpeakerTrack = room.localParticipant.getTrackPublication(source)?.track || null;
        window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: true } }));
        setCallNote('Your microphone is live. Everyone in the room can hear you. Headphones prevent echo.', false);
      } else {
        await dropLocalSource(source);
        micOn = false;
        window.tmzLiveSpeakerTrack = null;
        window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: false } }));
        setCallNote('You are muted.', false);
      }
    } catch (error) {
      console.error('Could not change the microphone:', error);
      await dropLocalSource(source);
      micOn = false;
      if (!localCanPublish(source)) await handleBlocked('audio');
      else setCallNote(mediaFailureMessage(error, 'microphone'), true);
    } finally {
      micBusy = false;
      if (micToggle) micToggle.disabled = false;
      renderCallControls();
      scheduleRoster();
    }
  }

  async function setCamera(enabled) {
    if (!room || room.state !== 'connected' || camBusy) return;
    const source = LivekitClient.Track.Source.Camera;
    if (enabled && !localCanPublish(source)) {
      renderCallControls();
      await handleBlocked('video');
      return;
    }
    camBusy = true;
    if (camToggle) camToggle.disabled = true;
    try {
      if (enabled) {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
          const secureError = new Error('Your camera needs a secure (HTTPS) connection to this site.');
          secureError.name = 'SecureContextError';
          throw secureError;
        }
        const camFacing = devicePrefs.video ? facingFromLabel(camSelect?.selectedOptions?.[0]?.textContent) : 'user';
        await room.localParticipant.setCameraEnabled(true, {
          deviceId: devicePrefs.video || undefined,
          resolution: qualityCapture().resolution,
          facingMode: camFacing || 'user'
        });
        camOn = true;
        fillDeviceSelects();
        const track = room.localParticipant.getTrackPublication(source)?.track;
        if (track) showLocalCamera(track);
        setCallNote('Your camera is on. Everyone in the room can see you.', false);
      } else {
        const track = room.localParticipant.getTrackPublication(source)?.track;
        await dropLocalSource(source);
        camOn = false;
        hideLocalCamera(track);
        setCallNote('Your camera is off. You can still talk.', false);
      }
    } catch (error) {
      console.error('Could not change the camera:', error);
      const track = room.localParticipant.getTrackPublication(source)?.track;
      await dropLocalSource(source);
      camOn = false;
      hideLocalCamera(track);
      if (!localCanPublish(source)) await handleBlocked('video');
      else setCallNote(mediaFailureMessage(error, 'camera'), true);
    } finally {
      camBusy = false;
      if (camToggle) camToggle.disabled = false;
      renderCallControls();
      scheduleRoster();
    }
  }

  /** The host changed what this person may do. Stop anything now forbidden and say so; say so again when allowed back. */
  async function handleLocalPermissionChange() {
    if (!room || room.state !== 'connected') return;
    const mic = localCanPublish(LivekitClient.Track.Source.Microphone);
    const cam = localCanPublish(LivekitClient.Track.Source.Camera);
    if (!mic && micOn) {
      await dropLocalSource(LivekitClient.Track.Source.Microphone);
      micOn = false;
      window.tmzLiveSpeakerTrack = null;
      window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: false } }));
    }
    if (!cam && camOn) {
      const track = room.localParticipant.getTrackPublication(LivekitClient.Track.Source.Camera)?.track;
      await dropLocalSource(LivekitClient.Track.Source.Camera);
      camOn = false;
      hideLocalCamera(track);
    }
    if (lastMicAllowed === true && !mic) {
      setCallNote('The host muted you. You cannot talk until the host allows you again.', true);
    } else if (lastCamAllowed === true && !cam) {
      setCallNote('The host turned your camera off. You cannot use it until the host allows you again.', true);
    } else if ((lastMicAllowed === false && mic) || (lastCamAllowed === false && cam)) {
      requestedWhileBlocked = { audio: false, video: false };
      setCallNote('The host allowed you to talk again. Tap ' + (mic ? 'Unmute' : 'Start video') + ' when you are ready.', false);
    }
    lastMicAllowed = mic;
    lastCamAllowed = cam;
    renderCallControls();
    scheduleRoster();
  }

  function participantLabel(participant) {
    if (participant.isLocal) return 'You';
    if (String(participant.identity || '').indexOf('host_') === 0) return 'Host';
    return participant.name || 'Guest';
  }

  function applySpeakingState() {
    box.querySelectorAll('video.live-remote-video').forEach(function (video) {
      video.classList.toggle('is-speaking', speakingIdentities.has(video.dataset.liveParticipantIdentity));
    });
    roster?.querySelectorAll('[data-identity]').forEach(function (chip) {
      chip.classList.toggle('is-speaking', speakingIdentities.has(chip.dataset.identity));
    });
  }

  function scheduleRoster() {
    if (rosterTimer) return;
    rosterTimer = window.setTimeout(function () {
      rosterTimer = null;
      renderRoster();
    }, 150);
  }

  function renderRoster() {
    if (!roster) return;
    if (!room || room.state !== 'connected') {
      roster.replaceChildren();
      return;
    }
    const remote = Array.from(room.remoteParticipants.values());
    const people = [room.localParticipant].concat(remote);
    const shown = people.filter(function (participant) {
      return participant.isLocal || String(participant.identity || '').indexOf('host_') === 0 ||
        participant.isMicrophoneEnabled || participant.isCameraEnabled;
    });
    const chips = shown.map(function (participant) {
      const chip = document.createElement('span');
      chip.className = 'live-roster-chip';
      chip.dataset.identity = participant.identity;
      const mic = participant.isLocal ? micOn : participant.isMicrophoneEnabled;
      const cam = participant.isLocal ? camOn : participant.isCameraEnabled;
      chip.textContent = (mic ? '🎤 ' : '🔇 ') + participantLabel(participant) + (cam ? ' 📷' : '');
      return chip;
    });
    const watching = people.length - shown.length;
    if (watching > 0) {
      const more = document.createElement('span');
      more.className = 'live-roster-more';
      more.textContent = '+' + watching + ' listening';
      chips.push(more);
    }
    roster.replaceChildren.apply(roster, chips);
    applySpeakingState();
  }

  function resetCallState() {
    micOn = false;
    camOn = false;
    micBusy = false;
    camBusy = false;
    lastMicAllowed = null;
    lastCamAllowed = null;
    requestedWhileBlocked = { audio: false, video: false };
    speakingIdentities = new Set();
    hideLocalCamera();
    window.tmzLiveSpeakerTrack = null;
    window.tmzLiveRoom = null;
    window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: false } }));
    if (callBar) callBar.hidden = true;
    if (roster) roster.replaceChildren();
    setCallNote('', false);
  }

  async function join() {
    if (room && room.state !== 'disconnected') return;
    joinButton.disabled = true;
    if (retryButton) retryButton.hidden = true;
    setStatus('Connecting to the live room… If the network is slow, LiveKit will retry automatically.', 'connecting');
    const body = new URLSearchParams({ live_id: liveId, role: 'viewer', csrf_token: csrf });
    try {
      const response = await fetch('/TMZ%20PODCAST/api/livekit-token.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      const data = await response.json();
      if (!response.ok) throw new Error(data.error || 'Unable to join this broadcast.');
      try {
        livekitHost = new URL(data.url).host;
      } catch (error) {
        livekitHost = '';
      }

      room = new LivekitClient.Room({ adaptiveStream: true, dynacast: true });
      room.on(LivekitClient.RoomEvent.TrackSubscribed, function (track, publication, participant) {
        const element = track.attach();
        element.autoplay = true;
        element.playsInline = true;
        const source = publication?.source || track.source;
        if (track.kind === LivekitClient.Track.Kind.Video) {
          element.controls = true;
          element.classList.add('live-remote-video');
          element.dataset.liveSource = source || '';
          element.dataset.liveParticipantIdentity = participant?.identity || '';
          element.addEventListener('click', function () { updateFeaturedVideo(element); });
        }
        box.appendChild(element);
        updateFeaturedVideo(source === LivekitClient.Track.Source.ScreenShare ? element : undefined);
        syncStagePlaceholder();
        applySpeakingState();
        scheduleRoster();
        if (source === LivekitClient.Track.Source.ScreenShare) {
          box.dispatchEvent(new CustomEvent('tmz-screen-share', { detail: { active: true, element } }));
        }
      });
      room.on(LivekitClient.RoomEvent.TrackUnsubscribed, function (track, publication) {
        const source = publication?.source || track.source;
        track.detach().forEach(function (element) { element.remove(); });
        updateFeaturedVideo();
        syncStagePlaceholder();
        scheduleRoster();
        if (source === LivekitClient.Track.Source.ScreenShare) {
          box.dispatchEvent(new CustomEvent('tmz-screen-share', { detail: { active: false } }));
        }
      });
      room.on(LivekitClient.RoomEvent.DataReceived, function (payload, participant, kind, topic) {
        const message = decode(payload);
        if (!message) return;
        window.dispatchEvent(new CustomEvent('tmz-live-data', {
          detail: { payload, participant, kind, topic }
        }));
        if (message.type === 'chat' && typeof message.message === 'string') {
          appendMessage(message.message, participant?.name || participant?.identity || 'Viewer');
        }
        if (message.type === 'reaction') {
          box.dispatchEvent(new CustomEvent('tmz-live-reaction', { detail: { reaction: message.reaction } }));
        }
        if (message.type === 'live_spotlight' && participant?.identity?.startsWith('host_')) {
          setAudienceSpotlight(message);
        }
      });
      room.on(LivekitClient.RoomEvent.Disconnected, function () {
        setStatus('Connection ended. Tap Reconnect to try again.', 'error');
        if (retryButton) retryButton.hidden = false;
        if (invitationPoll) {
          window.clearInterval(invitationPoll);
          invitationPoll = null;
        }
        stopSpeakerTrack();
        resetCallState();
      });
      room.on(LivekitClient.RoomEvent.Reconnecting, function () {
        setStatus('Connection interrupted. Reconnecting…', 'connecting');
      });
      room.on(LivekitClient.RoomEvent.Reconnected, function () {
        setStatus('LIVE');
        if (retryButton) retryButton.hidden = true;
        if (!invitationPoll) invitationPoll = window.setInterval(pollInvitations, 1000);
      });
      room.on(LivekitClient.RoomEvent.ParticipantPermissionsChanged, function (previousPermissions, participant) {
        if (participant?.identity !== room.localParticipant.identity) return;
        if (speakerTrack) {
          const source = speakerTrack.kind === 'audio'
            ? LivekitClient.Track.Source.Microphone
            : LivekitClient.Track.Source.Camera;
          if (!hasPublishSource(participant.permissions?.canPublishSources, source)) {
            stopSpeakerTrack();
            setStatus('Your speaking permission was revoked by the host.', 'error');
          }
        }
        handleLocalPermissionChange();
      });
      room.on(LivekitClient.RoomEvent.LocalTrackUnpublished, function (publication) {
        const source = publication?.source;
        if (source === LivekitClient.Track.Source.Microphone && micOn) {
          micOn = false;
          window.tmzLiveSpeakerTrack = null;
          window.dispatchEvent(new CustomEvent('tmz-push-to-talk', { detail: { enabled: false } }));
        }
        if (source === LivekitClient.Track.Source.Camera && camOn) {
          camOn = false;
          hideLocalCamera(publication.track);
        }
        renderCallControls();
        scheduleRoster();
      });
      room.on(LivekitClient.RoomEvent.ActiveSpeakersChanged, function (speakers) {
        speakingIdentities = new Set((speakers || []).map(function (speaker) { return speaker.identity; }));
        applySpeakingState();
      });
      [
        LivekitClient.RoomEvent.ParticipantConnected,
        LivekitClient.RoomEvent.ParticipantDisconnected,
        LivekitClient.RoomEvent.TrackPublished,
        LivekitClient.RoomEvent.TrackUnpublished,
        LivekitClient.RoomEvent.TrackMuted,
        LivekitClient.RoomEvent.TrackUnmuted,
        LivekitClient.RoomEvent.LocalTrackPublished
      ].forEach(function (eventName) { room.on(eventName, scheduleRoster); });
      room.on(LivekitClient.RoomEvent.AudioPlaybackStatusChanged, function () {
        if (enableSoundButton) enableSoundButton.hidden = room.canPlaybackAudio;
      });

      await room.connect(data.url, data.token, {
        autoSubscribe: true,
        maxRetries: 4,
        websocketTimeout: 20000,
        peerConnectionTimeout: 20000
      });
      stage.hidden = false;
      prejoin.hidden = true;
      setStatus('LIVE');
      window.tmzLiveRoom = room;
      lastMicAllowed = localCanPublish(LivekitClient.Track.Source.Microphone);
      lastCamAllowed = localCanPublish(LivekitClient.Track.Source.Camera);
      requestedWhileBlocked = { audio: false, video: false };
      renderCallControls();
      renderRoster();
      if (!lastMicAllowed) setCallNote('The host has turned microphones off right now. You can talk again when the host allows it.', true);
      else setCallNote('Tap Unmute to talk, or Start video to switch to camera.', false);
      try {
        await room.startAudio();
      } catch (audioError) {
        console.warn('Browser needs a tap before it plays room audio:', audioError);
      }
      if (enableSoundButton) enableSoundButton.hidden = room.canPlaybackAudio;
      if (retryButton) retryButton.hidden = true;
      invitationPoll = window.setInterval(pollInvitations, 1000);
      pollInvitations();
      box.querySelector('.stream-placeholder')?.remove();
      if (!box.querySelector('video, audio')) {
        const placeholder = document.createElement('div');
        placeholder.className = 'stream-placeholder';
        placeholder.textContent = 'Connected — waiting for the host to publish.';
        box.appendChild(placeholder);
      }
    } catch (error) {
      room?.disconnect();
      room = null;
      setStatus('ERROR', 'error');
      joinButton.disabled = false;
      const message = document.createElement('p');
      message.className = 'live-join-error';
      message.textContent = connectionErrorMessage(error);
      prejoin.querySelector('.live-join-error')?.remove();
      prejoin.appendChild(message);
      joinButton.textContent = 'Try again';
      setStatus(message.textContent, 'error');
    }
  }

  joinButton.addEventListener('click', join);
  retryButton?.addEventListener('click', async function () {
    retryButton.disabled = true;
    if (room) {
      await room.disconnect();
      room = null;
    }
    stage.hidden = true;
    prejoin.hidden = false;
    const previousError = prejoin.querySelector('.live-join-error');
    previousError?.remove();
    joinButton.disabled = false;
    retryButton.hidden = true;
    retryButton.disabled = false;
    join();
  });
  acceptInviteButton?.addEventListener('click', () => respondToInvitation(true));
  declineInviteButton?.addEventListener('click', () => respondToInvitation(false));
  closeSpeakerErrorButton?.addEventListener('click', () => {
    inviteDialog.hidden = true;
    if (acceptInviteButton) acceptInviteButton.hidden = false;
    if (declineInviteButton) declineInviteButton.hidden = false;
    closeSpeakerErrorButton.hidden = true;
  });
  micToggle?.addEventListener('click', () => setMic(!micOn));
  camToggle?.addEventListener('click', () => setCamera(!camOn));
  enableSoundButton?.addEventListener('click', async () => {
    try {
      await room?.startAudio();
    } catch (error) {
      console.warn('Could not start room audio:', error);
    }
    enableSoundButton.hidden = Boolean(room?.canPlaybackAudio);
  });
  pushToTalkButton?.addEventListener('click', async () => {
    await setPushToTalk(speakerTrack?.isMuted !== false);
  });
  leaveStageButton?.addEventListener('click', async () => {
    if (!speakerTrack || !acceptedSpeakerRequestId) return;
    try {
      await invitationRequest('revoke', acceptedSpeakerRequestId);
      await stopSpeakerTrack();
      activeInvitation = null;
      setStatus('You left the speaker role.', 'ready');
    } catch (error) {
      setStatus(error.message, 'error');
    }
  });
  layoutButtons.forEach((button) => {
    button.addEventListener('click', () => setLayout(button.dataset.liveLayout));
  });
  setLayout(layout);

  chatForm?.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!room) {
      setStatus('Join the live room before posting.', 'error');
      return;
    }
    const input = document.getElementById('liveChatInput');
    const message = input.value.trim();
    if (!message) return;
    const button = chatForm.querySelector('button');
    button.disabled = true;
    try {
      await publishMessage({ type: 'chat', message }, 'chat');
      await postForm(chatForm, { comment: message });
      appendMessage(message, room.localParticipant.name || room.localParticipant.identity);
      input.value = '';
    } catch (error) {
      setStatus(error.message || 'Chat could not be sent.', 'error');
    } finally {
      button.disabled = false;
    }
  });

  actionsForm?.addEventListener('click', async function (event) {
    const reaction = event.target.closest('[data-reaction]')?.dataset.reaction;
    const speakerRequest = event.target.closest('[data-speaker-request]')?.dataset.speakerRequest;
    if (!reaction && !speakerRequest) return;
    if (!room) {
      setStatus('Join the live room before interacting.', 'error');
      return;
    }
    const button = event.target.closest('button');
    button.disabled = true;
    try {
      if (reaction) {
        await publishMessage({ type: 'reaction', reaction }, 'reaction');
        await postForm(actionsForm, { reaction });
      } else {
        await postForm(actionsForm, { request_speaker: speakerRequest });
        setStatus('Your request was sent to the host.', 'ready');
      }
    } catch (error) {
      setStatus(error.message || 'That action could not be sent.', 'error');
    } finally {
      button.disabled = false;
    }
  });
})();
