(function () {
  'use strict';
  const API = '/media-upload.php';
  const CHUNK = 8388608; // 8 MB — must match UploadSession::CHUNK_SIZE
  const stateKey = 'tmz_uploads_v1';

  const root = document.getElementById('resumableUpload') || document.querySelector('[data-ru-upload]');
  if (!root) return;
  const fileInput = root.querySelector('input[type=file]');
  const list = root.querySelector('.ru-list');
  const warn = root.querySelector('.ru-warnings');

  const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent) || (navigator.maxTouchPoints > 1 && innerWidth < 900);
  if (isMobile && warn) {
    warn.hidden = false;
    warn.innerHTML = '📱 <b>Mobile upload:</b> keep this screen open and the phone unlocked — mobile browsers pause uploads when backgrounded. On cellular data, a feature film can use several GB.';
  }

  function loadState() { try { return JSON.parse(localStorage.getItem(stateKey) || '{}'); } catch (e) { return {}; } }
  function saveState(s) { try { localStorage.setItem(stateKey, JSON.stringify(s)); } catch (e) {} }

  function el(html) { const t = document.createElement('template'); t.innerHTML = html.trim(); return t.content.firstChild; }
  function fmtBytes(b) { if (b < 1048576) return (b / 1024).toFixed(0) + ' KB'; if (b < 1073741824) return (b / 1048576).toFixed(1) + ' MB'; return (b / 1073741824).toFixed(2) + ' GB'; }
  function fmtSpeed(bps) { return bps > 1048576 ? (bps / 1048576).toFixed(1) + ' MB/s' : Math.max(0, bps / 1024).toFixed(0) + ' KB/s'; }

  async function api(action, body, headers) {
    const res = await fetch(API + '?action=' + action, {
      method: 'POST',
      headers: Object.assign({ 'Content-Type': 'application/json' }, headers || {}),
      body: body instanceof FormData || body instanceof Blob ? body : JSON.stringify(body),
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
    return data;
  }

  function renderJob(job) {
    const row = el(`<div class="ru-job" data-id="${job.uploadId}">
      <div class="ru-job-head"><strong class="ru-name"></strong><span class="ru-status"></span></div>
      <div class="ru-bar"><i></i></div>
      <div class="ru-meta"><span class="ru-pct"></span><span class="ru-speed"></span><span class="ru-left"></span></div>
      <div class="ru-actions"></div></div>`);
    row.querySelector('.ru-name').textContent = job.file.name;
    row.querySelector('.ru-status').textContent = job.statusText || '';
    list.appendChild(row);
    job.row = row;
    return row;
  }

  function updateRow(job) {
    const pct = job.size ? Math.min(100, Math.round(job.received / job.size * 100)) : 0;
    job.row.querySelector('.ru-bar i').style.width = pct + '%';
    job.row.querySelector('.ru-pct').textContent = pct + '%';
    job.row.querySelector('.ru-speed').textContent = job.speedText || '';
    job.row.querySelector('.ru-left').textContent = job.leftText || '';
    job.row.querySelector('.ru-status').textContent = job.statusText || '';
  }

  function setActions(job, html) { job.row.querySelector('.ru-actions').innerHTML = html; bindActions(job); }

  async function sendChunk(job, offset, blob) {
    const res = await fetch(API + '?action=chunk', {
      method: 'POST',
      headers: {
        'X-Upload-Id': String(job.uploadId),
        'X-Upload-Token': job.token,
        'X-Chunk-Offset': String(offset),
        'Content-Type': 'application/octet-stream',
      },
      body: blob,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || ('HTTP ' + res.status));
    return data;
  }

  async function uploadFile(file, resume) {
    const job = {
      file, uploadId: resume ? resume.uploadId : null, token: resume ? resume.token : null,
      received: resume ? resume.received : 0, size: file.size, speed: 0, speedText: '', leftText: '',
      statusText: resume ? 'Resuming…' : 'Starting…', done: false,
      mode: (resume && resume.mode) || 'local', partSize: (resume && resume.partSize) || CHUNK,
      parts: (resume && resume.parts) || {},
    };
    renderJob(job);
    try {
      if (!job.uploadId) {
        const s = await api('start', { filename: file.name, size: file.size, mime: file.type || 'video/mp4' });
        job.uploadId = s.upload_id; job.token = s.token;
        job.mode = s.mode || 'local'; job.partSize = s.part_size || CHUNK;
        job.received = 0;
        persist(job);
      } else {
        // Re-validate session still alive; resume offset from server truth.
        const st = await api('status', { upload_id: job.uploadId, token: job.token });
        job.mode = st.mode || job.mode;
        if (st.part_size) job.partSize = st.part_size;
        if (st.parts) { job.parts = {}; for (const pn in st.parts) job.parts[pn] = st.parts[pn].etag; }
        job.received = st.received || 0;
      }
      job.statusText = 'Uploading…';
      setActions(job, '');
      updateRow(job);

      if (job.mode === 'r2') { await uploadR2Parts(job); }
      else { await uploadLocalChunks(job); }

      job.statusText = 'Finalizing…'; updateRow(job);
      const done = await api('complete', job.mode === 'r2'
        ? { upload_id: job.uploadId, token: job.token, parts: job.parts }
        : { upload_id: job.uploadId, token: job.token });
      job.statusText = 'Done ✓ media #' + done.media_id;
      job.done = true;
      clearPersist(job);
      const targetSel = root.dataset.targetSelect;
      if (targetSel) {
        const sel = document.getElementById(targetSel);
        if (sel) {
          let opt = sel.querySelector('option[value="' + done.media_id + '"]');
          if (!opt) {
            opt = document.createElement('option');
            opt.value = done.media_id;
            opt.textContent = job.file.name + ' (just uploaded)';
            sel.appendChild(opt);
          }
          sel.value = String(done.media_id);
          sel.dispatchEvent(new Event('change', { bubbles: true }));
          job.statusText = 'Done ✓ selected as movie video';
        }
        setActions(job, '');
      } else {
        setActions(job, `<a class="btn btn-small btn-primary" href="/admin/dashboard.php?tab=media" target="_blank">Open Media Library</a>`);
      }
      updateRow(job);
    } catch (e) {
      job.statusText = 'Failed: ' + e.message;
      job.speedText = '';
      updateRow(job);
      setActions(job, `<button class="btn btn-small btn-outline ru-retry" type="button">Retry</button><button class="btn btn-small ru-abort" type="button">Discard</button>`);
      job.retryFn = () => { clearPersist(job); uploadFile(job.file, { uploadId: job.uploadId, token: job.token, received: job.received, mode: job.mode, partSize: job.partSize, parts: job.parts }); };
    }
  }

  async function uploadLocalChunks(job) {
    const t0 = Date.now(); let lastBytes = job.received; let lastTime = t0;
    while (job.received < job.size) {
      const end = Math.min(job.received + CHUNK, job.size);
      const blob = job.file.slice(job.received, end);
      const r = await sendChunk(job, job.received, blob);
      job.received = r.received;
      persist(job);
      const now = Date.now();
      const dt = (now - lastTime) / 1000;
      if (dt > 0.5) {
        job.speed = (job.received - lastBytes) / dt;
        job.speedText = fmtSpeed(job.speed);
        const remain = job.size - job.received;
        job.leftText = remain > 0 ? fmtBytes(remain) + ' left · ~' + Math.max(1, Math.round(remain / Math.max(1, job.speed))) + 's' : '';
        lastBytes = job.received; lastTime = now;
      }
      updateRow(job);
    }
  }

  // R2 mode: each part is PUT straight to Cloudflare via a presigned URL.
  async function uploadR2Parts(job) {
    const totalParts = Math.ceil(job.size / job.partSize);
    const t0 = Date.now(); let lastBytes = job.received; let lastTime = t0;
    for (let pn = 1; pn <= totalParts; pn++) {
      if (job.parts[pn]) continue;
      const start = (pn - 1) * job.partSize;
      const end = Math.min(start + job.partSize, job.size);
      const blob = job.file.slice(start, end);
      const pu = await api('parturl', { upload_id: job.uploadId, token: job.token, part: pn });
      const res = await fetch(pu.url, { method: 'PUT', body: blob });
      if (!res.ok) throw new Error('Cloud upload failed for part ' + pn + ' (HTTP ' + res.status + ')');
      const etag = (res.headers.get('ETag') || '').replace(/"/g, '');
      if (!etag) throw new Error('Cloud did not return an ETag for part ' + pn);
      job.parts[pn] = etag;
      job.received = Math.min(job.size, job.received + (end - start));
      persist(job);
      const now = Date.now();
      const dt = (now - lastTime) / 1000;
      if (dt > 0.5) {
        job.speed = ((end - start)) / dt;
        job.speedText = fmtSpeed(job.speed);
        const remain = job.size - job.received;
        job.leftText = remain > 0 ? fmtBytes(remain) + ' left · ~' + Math.max(1, Math.round(remain / Math.max(1, job.speed))) + 's' : '';
        lastBytes = job.received; lastTime = now;
      }
      updateRow(job);
    }
  }

  function partSizeFor(job, pn) {
    const start = (pn - 1) * job.partSize;
    return Math.min(job.partSize, job.size - start);
  }

  function persist(job) {
    const s = loadState();
    s[job.uploadId] = { uploadId: job.uploadId, token: job.token, name: job.file.name, size: job.size, received: job.received, mode: job.mode, partSize: job.partSize, parts: job.parts, ts: Date.now() };
    saveState(s);
  }
  function clearPersist(job) { const s = loadState(); delete s[job.uploadId]; saveState(s); }

  function bindActions(job) {
    job.row.querySelector('.ru-retry')?.addEventListener('click', job.retryFn);
    job.row.querySelector('.ru-abort')?.addEventListener('click', async () => {
      try { await api('abort', { upload_id: job.uploadId, token: job.token }); } catch (e) {}
      clearPersist(job); job.row.remove();
    });
  }

  fileInput?.addEventListener('change', async () => {
    for (const file of fileInput.files) {
      if (!/^video\//.test(file.type) && !/\.(mp4|webm|mov|mkv|avi|ogv|3gp)$/i.test(file.name)) {
        alert('Only video files are supported: ' + file.name); continue;
      }
      // Resume if we have a stored session for an identical file.
      const saved = loadState()[null]; // placeholder — sessions keyed by id; match by name+size below
      let resume = null;
      const st = loadState();
      for (const k in st) { if (st[k].name === file.name && st[k].size === file.size) { resume = st[k]; break; } }
      uploadFile(file, resume);
    }
    fileInput.value = '';
  });

  // Offer resume of in-progress sessions after refresh.
  (async () => {
    try {
      const res = await fetch(API + '?action=sessions', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}' });
      const data = await res.json();
      const st = loadState();
      for (const s of (data.sessions || [])) {
        const saved = st[s.id];
        if (!saved) continue;
        // Rebuild a File-like object lazily: we cannot recreate the File; user must re-pick.
        // Show a resume hint row instead.
        const row = el(`<div class="ru-job"><div class="ru-job-head"><strong>${s.filename}</strong><span class="ru-status">Interrupted — ${Math.round(s.received_bytes / s.size_bytes * 100)}%</span></div>
          <div class="ru-meta"><span>Re-select the same file to resume from ${Math.round(s.received_bytes / 1048576)} MB</span></div></div>`);
        list.appendChild(row);
      }
    } catch (e) {}
  })();
})();
