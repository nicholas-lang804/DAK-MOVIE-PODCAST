(function () {
  const preview = document.getElementById('cameraPreview');
  const fullscreen = document.getElementById('previewFullscreen');
  const share = document.getElementById('screenShare');
  const duration = document.getElementById('studioDuration');
  const viewers = document.getElementById('liveViewerCount');
  const health = document.getElementById('studioHealth');
  let startedAt = null;
  let screenSharing = false;
  let cameraPreviewStream = null;
  let boundRoom = null;
  let analyser = null;
  let meterData = null;

  function restoreCameraPreview() {
    screenSharing = false;
    if (share) {
      share.textContent = '📺 Share screen';
      share.classList.remove('active');
      share.setAttribute('aria-pressed', 'false');
    }
    if (preview && cameraPreviewStream) preview.srcObject = cameraPreviewStream;
  }

  fullscreen?.addEventListener('click', function () {
    if (preview?.requestFullscreen) preview.requestFullscreen();
  });

  share?.addEventListener('click', async function () {
    const room = window.tmzLiveRoom;
    if (!room) {
      alert('Connect the live studio before sharing your screen.');
      return;
    }
    if (!window.isSecureContext || !navigator.mediaDevices?.getDisplayMedia) {
      alert('Screen sharing requires a supported browser and a secure page. Use HTTPS or open the studio on the XAMPP computer at http://localhost.');
      return;
    }

    share.disabled = true;
    try {
      const enable = !screenSharing;
      if (enable) {
        if (preview?.srcObject) cameraPreviewStream = preview.srcObject;
        if (boundRoom !== room) {
          room.on(LivekitClient.RoomEvent.LocalTrackUnpublished, function (publication) {
            if (publication.source === LivekitClient.Track.Source.ScreenShare) restoreCameraPreview();
          });
          boundRoom = room;
        }
      }
      await room.localParticipant.setScreenShareEnabled(enable, { audio: true });
      screenSharing = enable;
      share.textContent = enable ? '⏹ Stop screen share' : '📺 Share screen';
      share.classList.toggle('active', enable);
      share.setAttribute('aria-pressed', enable ? 'true' : 'false');

      if (enable) {
        const publication = room.localParticipant.getTrackPublication(LivekitClient.Track.Source.ScreenShare);
        const mediaTrack = publication?.track?.mediaStreamTrack;
        if (mediaTrack) preview.srcObject = new MediaStream([mediaTrack]);
      } else restoreCameraPreview();
    } catch (error) {
      console.error('Screen sharing failed:', error);
      alert(error?.message || 'Screen sharing was cancelled or could not be started.');
    } finally {
      share.disabled = false;
    }
  });

  window.addEventListener('tmz-live-started', function () {
    startedAt = Date.now();
  });

  window.setInterval(function () {
    const room = window.tmzLiveRoom;
    if (!room) return;
    if (!startedAt) startedAt = Date.now();
    const seconds = Math.floor((Date.now() - startedAt) / 1000);
    const h = String(Math.floor(seconds / 3600)).padStart(2, '0');
    const m = String(Math.floor((seconds % 3600) / 60)).padStart(2, '0');
    const s = String(seconds % 60).padStart(2, '0');
    if (duration) duration.textContent = h + ':' + m + ':' + s;
    if (viewers) viewers.textContent = String(room.remoteParticipants?.size || 0);
    if (health) health.textContent = 'LIVE';
    if (!analyser && preview?.srcObject) {
      try { const audio = new AudioContext(); analyser = audio.createAnalyser(); analyser.fftSize = 64; audio.createMediaStreamSource(preview.srcObject).connect(analyser); meterData = new Uint8Array(analyser.frequencyBinCount); } catch (error) { analyser = null; }
    }
    if (analyser && meterData) { analyser.getByteFrequencyData(meterData); const level = Math.min(100, Math.round(meterData.reduce((a, b) => a + b, 0) / meterData.length / 255 * 180)); const fill = document.getElementById('audioMeterFill'); if (fill) fill.style.width = level + '%'; }
  }, 1000);
})();
