(function () {
  'use strict';

  const adminPanel = document.getElementById('adminLiveRecordings');
  const publicPanel = document.getElementById('publicLiveRecordings');
  const panel = adminPanel || publicPanel;
  if (!panel) return;

  const liveId = panel.dataset.liveId;
  const api = panel.dataset.api || '/TMZ%20PODCAST/api/live-recordings.php';
  const list = panel.querySelector('.live-recordings-list');
  const status = panel.querySelector('.live-recordings-status');
  const startVideo = document.getElementById('startVideoRecording');
  const startAudio = document.getElementById('startAudioRecording');
  const audioFormat = document.getElementById('liveAudioRecordingFormat');
  const stopButton = document.getElementById('stopLiveRecording');
  const liveNotice = document.getElementById('liveRecordingNotice');
  const csrf = document.querySelector('#liveStudioCsrf input[name="csrf_token"]')?.value || '';
  let activeRecordingId = null;
  let requestPending = false;

  function formatDuration(milliseconds) {
    const total = Math.floor(Math.max(0, Number(milliseconds) || 0) / 1000);
    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;
    return [hours, minutes, seconds].map(value => String(value).padStart(2, '0')).join(':');
  }

  function formatBytes(bytes) {
    const value = Number(bytes) || 0;
    if (value < 1024) return value + ' B';
    const units = ['KB', 'MB', 'GB', 'TB'];
    let size = value / 1024;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
      size /= 1024;
      unit += 1;
    }
    return size.toFixed(1) + ' ' + units[unit];
  }

  function renderRecordings(recordings) {
    list.replaceChildren();
    if (!recordings.length) {
      const empty = document.createElement('p');
      empty.className = 'form-help';
      empty.textContent = 'No recordings have been made for this show yet.';
      list.appendChild(empty);
      return;
    }

    recordings.forEach(recording => {
      const row = document.createElement('article');
      row.className = 'live-recording-row';
      const detail = document.createElement('div');
      const title = document.createElement('strong');
      title.textContent = (recording.media_type === 'video' ? 'Video' : 'Audio') + ' · ' + String(recording.format).toUpperCase();
      const meta = document.createElement('span');
      meta.textContent = String(recording.status).toUpperCase() + ' · ' + formatDuration(recording.duration_ms) +
        (Number(recording.file_size_bytes) > 0 ? ' · ' + formatBytes(recording.file_size_bytes) : '');
      detail.append(title, meta);
      row.appendChild(detail);

      if (recording.status === 'complete') {
        const download = document.createElement('a');
        download.className = 'btn btn-outline';
        download.textContent = 'Download';
        download.href = api + '?action=download&live_id=' + encodeURIComponent(liveId) +
          '&recording_id=' + encodeURIComponent(recording.id);
        row.appendChild(download);
        if (adminPanel) {
          const remove = document.createElement('button');
          remove.className = 'btn btn-danger';
          remove.type = 'button';
          remove.textContent = 'Delete';
          remove.dataset.recordingDelete = recording.id;
          row.appendChild(remove);
        }
      }
      list.appendChild(row);
    });
  }

  async function loadRecordings() {
    const response = await fetch(api + '?live_id=' + encodeURIComponent(liveId), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Could not load recordings.');
    renderRecordings(Array.isArray(data.recordings) ? data.recordings : []);
    activeRecordingId = data.active_recording_id || null;
    if (liveNotice) {
      liveNotice.hidden = !activeRecordingId;
      liveNotice.textContent = activeRecordingId ? '● This live show is being recorded.' : '';
    }
    if (stopButton) stopButton.disabled = requestPending || !activeRecordingId;
    if (startVideo) startVideo.disabled = requestPending || !data.can_record || Boolean(activeRecordingId);
    if (startAudio) startAudio.disabled = requestPending || !data.can_record || Boolean(activeRecordingId);
    if (status && adminPanel) {
      status.textContent = activeRecordingId
        ? 'Recording is in progress. Stop it to finalize the downloadable file.'
        : (data.can_record
          ? 'Ready to record. Viewers are informed about recording before joining.'
          : (data.recording_setup_error || 'Recording can only be started while this show is live.'));
    }
    if (status && publicPanel) {
      status.textContent = activeRecordingId ? 'This live show is currently being recorded.' : '';
    }
  }

  async function sendAction(action, values) {
    if (!csrf) throw new Error('Refresh the page to obtain a valid security token.');
    const body = new URLSearchParams({ live_id: liveId, action, csrf_token: csrf, ...values });
    const response = await fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', Accept: 'application/json' },
      body
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Recording action failed.');
    return data;
  }

  async function runAction(action, values) {
    if (requestPending) return;
    requestPending = true;
    if (status) status.textContent = action === 'stop'
      ? 'Stopping and finalizing recording…'
      : (action === 'delete' ? 'Deleting recording…' : 'Starting recording…');
    if (startVideo) startVideo.disabled = true;
    if (startAudio) startAudio.disabled = true;
    if (stopButton) stopButton.disabled = true;
    try {
      await sendAction(action, values);
      await loadRecordings();
    } catch (error) {
      if (status) status.textContent = error.message || 'Recording action failed.';
    } finally {
      requestPending = false;
      try {
        await loadRecordings();
      } catch (error) {
        if (status) status.textContent = error.message || 'Could not refresh recording status.';
      }
    }
  }

  startVideo?.addEventListener('click', () => runAction('start', { media_type: 'video', format: 'mp4' }));
  startAudio?.addEventListener('click', () => runAction('start', { media_type: 'audio', format: audioFormat?.value || 'mp3' }));
  stopButton?.addEventListener('click', () => {
    if (activeRecordingId) runAction('stop', { recording_id: String(activeRecordingId) });
  });
  list?.addEventListener('click', event => {
    const button = event.target.closest('[data-recording-delete]');
    if (!button || !window.confirm('Permanently delete this recording? This cannot be undone.')) return;
    runAction('delete', { recording_id: button.dataset.recordingDelete });
  });

  loadRecordings().catch(error => {
    if (status) status.textContent = error.message || 'Could not load recordings.';
    else if (list) list.textContent = error.message || 'Could not load recordings.';
  });
  window.setInterval(() => {
    loadRecordings().catch(error => {
      if (status) status.textContent = error.message || 'Could not refresh recording status.';
    });
  }, 8000);
})();
