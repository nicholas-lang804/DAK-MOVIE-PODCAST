/* TMZ Music player: persistent bar + Now Playing sheet.
 * Queue, shuffle, repeat, seek, volume, speed, sleep timer, 5-band EQ, visualiser, synced lyrics,
 * music-video mode, likes, playlists, downloads, media keys and in-page navigation inside #musicApp. */
(function () {
  'use strict';
  var root = document.getElementById('mzPlayer');
  if (!root || root.dataset.mzInit === '1') return;
  root.dataset.mzInit = '1';

  var API = root.dataset.api, CSRF = root.dataset.csrf, LOGGED = root.dataset.loggedIn === '1';
  var KEY = 'tmz_music_state_v1';
  var $ = function (id) { return document.getElementById(id); };
  var audio = $('mzAudio'), video = $('mzVideo'), sheet = $('mzSheet');
  var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  var EQ_FREQS = [60, 230, 910, 3600, 14000];
  var EQ_LABELS = ['60 Hz', '230 Hz', '910 Hz', '3.6 kHz', '14 kHz'];
  var EQ_PRESETS = { flat: [0, 0, 0, 0, 0], bass: [6, 4, 0, 0, 0], vocal: [-2, 0, 3, 3, 1], treble: [0, 0, 0, 3, 6], rock: [4, 2, -1, 2, 4], electronic: [5, 3, 0, 2, 4], acoustic: [3, 1, 1, 2, 2] };

  var state = { queue: [], order: [], pos: -1, shuffle: false, repeat: 'off', volume: 0.8, muted: false, speed: 1, eq: 'flat', eqGains: [0, 0, 0, 0, 0] };
  var mode = 'audio';
  var started = false, seeking = false, listened = 0, lastTick = 0, counted = false, pendingSeek = 0;
  var lyricsCache = {}, lyricsLines = [], lyricsSynced = false, lyricsActive = -1, lyricsFor = 0;
  var sleepTimer = null, sleepAtEnd = false;
  var ctx = null, analyser = null, filters = [], vizRaf = 0;

  /* ------------------------------------------------------------- utilities */
  function el(tag, cls, text) { var n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined && text !== null) n.textContent = text; return n; }
  function fmt(s) { s = Math.max(0, Math.floor(s || 0)); return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  function media() { return mode === 'video' ? video : audio; }
  function cur() { return state.pos >= 0 ? state.queue[state.order[state.pos]] || null : null; }
  function clone(o) { return JSON.parse(JSON.stringify(o)); }
  function coverNode(t, cls) {
    if (t && t.cover) { var s = el('span', 'mz-cover ' + (cls || '')); var i = document.createElement('img'); i.src = t.cover; i.alt = ''; s.appendChild(i); return s; }
    var f = el('span', 'mz-cover mz-cover-fallback tone-' + ((((t && t.title) || 'x').length % 4) + 1) + ' ' + (cls || ''), ((t && t.title) || '♪').charAt(0).toUpperCase());
    return f;
  }
  function setFill(input) { var max = +input.max || 100; input.style.setProperty('--fill', (100 * (+input.value) / max) + '%'); }

  var toastTimer = null;
  function toast(msg, href, linkText) {
    var t = $('mzToast'); t.textContent = msg;
    if (href) { var a = el('a', null, linkText || 'Continue'); a.href = href; t.appendChild(a); }
    t.hidden = false; window.clearTimeout(toastTimer); toastTimer = window.setTimeout(function () { t.hidden = true; }, href ? 6000 : 3500);
  }

  function api(action, data, method) {
    var opts = { credentials: 'same-origin', headers: { Accept: 'application/json' } };
    var url = API;
    if (method === 'GET') { url += '?action=' + encodeURIComponent(action) + '&' + new URLSearchParams(data || {}).toString(); }
    else {
      var b = new URLSearchParams(); b.set('action', action); b.set('csrf_token', CSRF);
      Object.keys(data || {}).forEach(function (k) { b.set(k, data[k]); });
      opts.method = 'POST'; opts.body = b;
    }
    return fetch(url, opts).then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { var e = new Error(j.error || 'Something went wrong.'); e.login = !!j.login; throw e; } return j; }); });
  }

  /* ------------------------------------------------------------ persistence */
  var lastSave = 0;
  function save(force) {
    var now = Date.now(); if (!force && now - lastSave < 2000) return; lastSave = now;
    try {
      var c = cur(); if (!c) { localStorage.removeItem(KEY); return; }
      var keep = state.order.slice(Math.max(0, state.pos - 20), state.pos + 180);
      var map = {}, q = [], o = [];
      keep.forEach(function (qi) { map[qi] = q.length; q.push(state.queue[qi]); });
      keep.forEach(function (qi) { o.push(map[qi]); });
      localStorage.setItem(KEY, JSON.stringify({ queue: q, order: o, pos: keep.indexOf(state.order[state.pos]), shuffle: state.shuffle, repeat: state.repeat, volume: state.volume, muted: state.muted, speed: state.speed, eq: state.eq, eqGains: state.eqGains, time: media().currentTime || 0 }));
    } catch (e) { /* storage full or blocked: playback still works */ }
  }
  function restore() {
    try {
      var raw = localStorage.getItem(KEY); if (!raw) return false;
      var s = JSON.parse(raw); if (!s || !s.queue || !s.queue.length) return false;
      state.queue = s.queue; state.order = s.order && s.order.length === s.queue.length ? s.order : s.queue.map(function (_, i) { return i; });
      state.pos = Math.min(Math.max(0, s.pos | 0), state.order.length - 1);
      state.shuffle = !!s.shuffle; state.repeat = ['off', 'all', 'one'].indexOf(s.repeat) >= 0 ? s.repeat : 'off';
      state.volume = typeof s.volume === 'number' ? s.volume : 0.8; state.muted = !!s.muted; state.speed = s.speed || 1;
      state.eq = s.eq || 'flat'; if (s.eqGains && s.eqGains.length === 5) state.eqGains = s.eqGains;
      pendingSeek = s.time || 0; return true;
    } catch (e) { return false; }
  }
  window.addEventListener('pagehide', function () { save(true); });

  /* ----------------------------------------------------------- audio graph */
  function ensureGraph() {
    if (ctx) { if (ctx.state === 'suspended') ctx.resume(); return; }
    var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
    try {
      ctx = new AC();
      var src = ctx.createMediaElementSource(audio), prev = src;
      filters = EQ_FREQS.map(function (f, i) {
        var b = ctx.createBiquadFilter(); b.type = i === 0 ? 'lowshelf' : (i === 4 ? 'highshelf' : 'peaking'); b.frequency.value = f; b.Q.value = 1; b.gain.value = state.eqGains[i];
        prev.connect(b); prev = b; return b;
      });
      analyser = ctx.createAnalyser(); analyser.fftSize = 128; analyser.smoothingTimeConstant = 0.8;
      prev.connect(analyser); analyser.connect(ctx.destination);
      if (ctx.state === 'suspended') ctx.resume();
    } catch (e) { ctx = null; analyser = null; filters = []; }
  }
  function applyEq() { filters.forEach(function (f, i) { f.gain.value = state.eqGains[i]; }); }

  /* ---------------------------------------------------------- queue logic */
  function buildOrder(startQ) {
    var n = state.queue.length, idx = []; for (var i = 0; i < n; i++) idx.push(i);
    if (state.shuffle) {
      var rest = idx.filter(function (i) { return i !== startQ; });
      for (var j = rest.length - 1; j > 0; j--) { var k = Math.floor(Math.random() * (j + 1)); var t = rest[j]; rest[j] = rest[k]; rest[k] = t; }
      state.order = [startQ].concat(rest); state.pos = 0;
    } else { state.order = idx; state.pos = startQ; }
  }

  function setQueue(tracks, startIndex, opts) {
    opts = opts || {};
    if (!tracks.length) return;
    state.queue = tracks.map(clone);
    var start = Math.max(0, Math.min(startIndex | 0, state.queue.length - 1));
    if (opts.shuffle) state.shuffle = true;
    buildOrder(opts.shuffle ? Math.floor(Math.random() * state.queue.length) : start);
    if (opts.shuffle && opts.firstIndex !== undefined) { buildOrder(opts.firstIndex); }
    loadCurrent(true);
  }

  function enqueue(track, playNext) {
    track = clone(track);
    if (!cur()) { setQueue([track], 0); return; }
    state.queue.push(track);
    var qi = state.queue.length - 1;
    if (playNext) state.order.splice(state.pos + 1, 0, qi); else state.order.push(qi);
    renderQueue(); save(true);
    toast(playNext ? 'Playing next: ' + track.title : 'Added to queue: ' + track.title);
  }

  function skipTarget(from, dir) {
    var n = state.order.length;
    for (var step = 1; step <= n; step++) {
      var p = from + dir * step;
      if (p >= n || p < 0) {
        if (state.repeat !== 'all') return -1;
        p = (p + n) % n;
      }
      var t = state.queue[state.order[p]];
      if (t && !t.locked) return p;
    }
    return -1;
  }

  function next(auto) {
    if (auto && state.repeat === 'one') { media().currentTime = 0; playMedia(); return; }
    var p = skipTarget(state.pos, 1);
    if (p < 0) { // end of the queue
      if (auto) { pauseMedia(); media().currentTime = 0; render(); }
      return;
    }
    if (state.repeat === 'all' && p <= state.pos && state.shuffle) { buildOrder(state.order[p]); } else { state.pos = p; }
    loadCurrent(true);
  }
  function prev() {
    if (media().currentTime > 3) { media().currentTime = 0; return; }
    var p = skipTarget(state.pos, -1);
    if (p < 0) { media().currentTime = 0; return; }
    state.pos = p; loadCurrent(true);
  }

  /* --------------------------------------------------------------- loading */
  function loadCurrent(autoplay) {
    var t = cur(); if (!t) return;
    if (mode === 'video') setMode('audio', true);
    document.body.classList.add('has-player'); root.hidden = false;
    if (t.locked) {
      pauseMedia(); render();
      toast(t.lock_hint || 'This track is locked.', /All Access/.test(t.lock_hint || '') ? '/TMZ%20PODCAST/membership.php' : '/TMZ%20PODCAST/login.php', /All Access/.test(t.lock_hint || '') ? 'See plans' : 'Sign in');
      return;
    }
    listened = 0; counted = false; lastTick = 0;
    audio.src = t.src; audio.playbackRate = state.speed; applyVolume();
    if (pendingSeek) { var s = pendingSeek; pendingSeek = 0; audio.addEventListener('loadedmetadata', function once() { audio.removeEventListener('loadedmetadata', once); try { audio.currentTime = Math.min(s, (audio.duration || s) - 0.5); } catch (e) {} }); }
    lyricsFor = 0; setLyrics(null, t); if (!sheet.hidden) loadLyrics(t);
    updateMediaSession(t); render(); save(true);
    if (autoplay) playMedia();
  }

  function playMedia() {
    var t = cur(); if (!t || t.locked) return;
    started = true;
    if (mode === 'audio') ensureGraph();
    var p = media().play();
    if (p && p.catch) p.catch(function (e) { if (e && e.name === 'NotAllowedError') toast('Tap play to start the music.'); else if (e && e.name !== 'AbortError') toast('This track could not be played.'); });
  }
  function pauseMedia() { audio.pause(); video.pause(); }
  function toggle() { var m = media(); if (!cur()) return; if (m.paused) playMedia(); else pauseMedia(); }

  function applyVolume() { audio.volume = state.volume; video.volume = state.volume; audio.muted = state.muted; video.muted = state.muted; }

  /* ----------------------------------------------------------------- render */
  function render() {
    var t = cur(), m = media(), playing = t && !m.paused && !m.ended;
    if (!t) return;
    $('mzBarTitle').textContent = t.title; $('mzBarArtist').textContent = t.artist;
    var art = $('mzBarArt'); art.textContent = ''; art.appendChild(coverNode(t));
    $('mzStageTitle').textContent = t.title; $('mzStageArtist').textContent = t.artist;
    var sa = $('mzStageArt'); sa.textContent = ''; sa.appendChild(coverNode(t));
    [$('mzPlay'), $('mzPlayMobile')].forEach(function (b) { b.textContent = playing ? '❚❚' : '▶'; b.setAttribute('aria-label', playing ? 'Pause' : 'Play'); });
    sheet.classList.toggle('is-playing', !!playing);
    [$('mzBarLike')].forEach(function (b) { b.classList.toggle('is-on', !!t.liked); b.setAttribute('aria-pressed', t.liked ? 'true' : 'false'); });
    $('mzShuffle').setAttribute('aria-pressed', state.shuffle ? 'true' : 'false');
    var r = $('mzRepeat'); r.dataset.mode = state.repeat; r.setAttribute('aria-label', 'Repeat: ' + state.repeat);
    $('mzMute').textContent = (state.muted || state.volume === 0) ? '🔇' : '🔊';
    $('mzVolume').value = Math.round(state.volume * 100); setFill($('mzVolume'));
    $('mzVideoBtn').hidden = !t.video; $('mzModeBtn').hidden = !t.video;
    $('mzModeBtn').textContent = mode === 'video' ? '♫ Listen only' : '▣ Watch video';
    $('mzPip').hidden = !(mode === 'video' && document.pictureInPictureEnabled); $('mzFullscreen').hidden = mode !== 'video';
    $('mzDownload').hidden = t.downloads_enabled === false;
    $('mzDownload').style.opacity = t.can_download ? '1' : '.5';
    $('mzSeek').disabled = false;
    $('mzSpeed').value = String(state.speed);
    markRows(); renderQueue();
  }

  function updateTime() {
    var m = media(), d = m.duration, c = m.currentTime || 0, t = cur();
    var total = isFinite(d) && d > 0 ? d : (t ? t.duration : 0);
    $('mzCur').textContent = fmt(c); $('mzDur').textContent = fmt(total);
    if (!seeking) { var sk = $('mzSeek'); sk.value = total ? Math.round(1000 * c / total) : 0; setFill(sk); }
    $('mzMiniFill').style.width = total ? (100 * c / total) + '%' : '0%';
    updateLyricsHighlight(c);
    if ('mediaSession' in navigator && navigator.mediaSession.setPositionState && total && isFinite(total)) {
      try { navigator.mediaSession.setPositionState({ duration: total, playbackRate: m.playbackRate || 1, position: Math.min(c, total) }); } catch (e) {}
    }
  }

  function markRows() {
    var t = cur(), m = media();
    Array.prototype.forEach.call(document.querySelectorAll('[data-track-id]'), function (n) {
      var on = !!t && +n.getAttribute('data-track-id') === t.id;
      n.classList.toggle('is-playing', on); n.classList.toggle('is-paused', on && m.paused);
    });
  }

  /* ---------------------------------------------------- listen counting (30 s) */
  function countListening() {
    var t = cur(), m = media(); if (!t || counted || m.paused) { lastTick = 0; return; }
    var now = performance.now();
    if (lastTick) listened += Math.min(1.5, (now - lastTick) / 1000);
    lastTick = now;
    var need = Math.min(30, (t.duration || 60) * 0.5);
    if (listened >= need) { counted = true; api('played', { track_id: t.id }).catch(function () {}); }
  }

  /* ---------------------------------------------------------- media events */
  function bindMedia(m) {
    m.addEventListener('timeupdate', function () { if (m !== media()) return; updateTime(); countListening(); save(false); });
    m.addEventListener('play', function () { if (m !== media()) return; render(); startViz(); setSessionState('playing'); });
    m.addEventListener('pause', function () { if (m !== media()) return; render(); setSessionState('paused'); });
    m.addEventListener('loadedmetadata', function () { if (m !== media()) return; updateTime(); });
    m.addEventListener('ended', function () { if (m !== media()) return; if (sleepAtEnd) { sleepAtEnd = false; $('mzSleep').value = '0'; showSleepNote(''); pauseMedia(); return; } next(true); });
    m.addEventListener('error', function () {
      if (m !== media() || !m.getAttribute('src')) return;
      var t = cur(); if (!started) return; // a restored queue must never skip tracks by itself
      toast('Could not play “' + (t ? t.title : 'this track') + '”.'); window.setTimeout(function () { if (state.repeat !== 'one') next(true); }, 800);
    });
    m.addEventListener('ratechange', function () { if (m === media()) save(false); });
  }
  bindMedia(audio); bindMedia(video);

  /* ---------------------------------------------------------- media session */
  function updateMediaSession(t) {
    if (!('mediaSession' in navigator) || typeof MediaMetadata === 'undefined') return;
    try {
      navigator.mediaSession.metadata = new MediaMetadata({ title: t.title, artist: t.artist, album: t.album || '', artwork: t.cover ? [{ src: t.cover, sizes: '512x512' }] : [] });
      var h = navigator.mediaSession.setActionHandler.bind(navigator.mediaSession);
      h('play', function () { playMedia(); }); h('pause', pauseMedia); h('previoustrack', prev); h('nexttrack', function () { next(false); });
      h('seekto', function (d) { if (d && typeof d.seekTime === 'number') media().currentTime = d.seekTime; });
      h('seekbackward', function () { media().currentTime = Math.max(0, media().currentTime - 10); });
      h('seekforward', function () { media().currentTime = media().currentTime + 10; });
    } catch (e) {}
  }
  function setSessionState(s) { if ('mediaSession' in navigator) { try { navigator.mediaSession.playbackState = s; } catch (e) {} } }

  /* ------------------------------------------------------------ video mode */
  function setMode(m, silent) {
    var t = cur(); if (m === mode) return;
    if (m === 'video') {
      if (!t || !t.video || t.locked) return;
      var at = audio.currentTime, was = !audio.paused;
      audio.pause(); mode = 'video'; video.hidden = false; sheet.classList.add('is-video');
      video.src = t.video; video.playbackRate = state.speed;
      video.addEventListener('loadedmetadata', function once() { video.removeEventListener('loadedmetadata', once); try { video.currentTime = at; } catch (e) {} if (was || !silent) playMedia(); });
    } else {
      var vt = video.currentTime, wasV = !video.paused;
      video.pause(); mode = 'audio'; video.removeAttribute('src'); video.load(); video.hidden = true; sheet.classList.remove('is-video');
      try { audio.currentTime = vt; } catch (e) {}
      if (wasV && !silent) playMedia();
    }
    render(); updateTime();
  }

  /* ------------------------------------------------------------ Now Playing */
  function openSheet(tab) {
    if (!cur()) return; sheet.hidden = false; document.body.style.overflow = 'hidden';
    if (tab) selectTab(tab);
    loadLyrics(cur()); renderQueue(); startViz(); $('mzCloseSheet').focus();
  }
  function closeSheet() { sheet.hidden = true; document.body.style.overflow = ''; $('mzOpenSheet').focus(); }
  function selectTab(name) {
    Array.prototype.forEach.call(sheet.querySelectorAll('.mz-panel-tabs button'), function (b) { b.setAttribute('aria-selected', b.dataset.tab === name ? 'true' : 'false'); });
    Array.prototype.forEach.call(sheet.querySelectorAll('.mz-panel-body'), function (p) { p.hidden = p.dataset.panel !== name; });
  }

  /* ----------------------------------------------------------------- lyrics */
  function parseLrc(text) {
    var lines = [], timed = 0, re = /^\s*((?:\[\d{1,2}:\d{2}(?:[.:]\d{1,3})?\]\s*)+)(.*)$/;
    String(text || '').split(/\r?\n/).forEach(function (raw) {
      var m = raw.match(re);
      if (m) {
        var words = m[2].trim(); var stamps = m[1].match(/\[(\d{1,2}):(\d{2})(?:[.:](\d{1,3}))?\]/g) || [];
        stamps.forEach(function (st) { var p = st.match(/\[(\d{1,2}):(\d{2})(?:[.:](\d{1,3}))?\]/); var frac = p[3] ? parseFloat('0.' + p[3]) : 0; lines.push({ t: (+p[1]) * 60 + (+p[2]) + frac, text: words }); timed++; });
      } else if (!/^\s*\[(ar|ti|al|by|offset|length):/i.test(raw)) { lines.push({ t: null, text: raw.trim() }); }
    });
    if (timed >= 2) { lines = lines.filter(function (l) { return l.t !== null; }).sort(function (a, b) { return a.t - b.t; }); return { synced: true, lines: lines }; }
    return { synced: false, lines: lines.map(function (l) { return { t: null, text: l.text }; }) };
  }
  function setLyrics(text, t) {
    var box = $('mzLyrics'); box.textContent = ''; lyricsLines = []; lyricsActive = -1; lyricsSynced = false; box.classList.remove('is-synced');
    if (text === null) { box.appendChild(el('p', 'mz-muted', t ? 'Loading lyrics…' : 'No lyrics for this song.')); return; }
    if (!String(text).trim()) { box.appendChild(el('p', 'mz-muted', 'No lyrics for this song.')); return; }
    var parsed = parseLrc(text); lyricsSynced = parsed.synced; box.classList.toggle('is-synced', lyricsSynced);
    parsed.lines.forEach(function (l, i) {
      var p = el('p', null, l.text || '♪'); if (!l.text && !lyricsSynced) p.innerHTML = '&nbsp;';
      if (lyricsSynced) { p.dataset.t = l.t; p.addEventListener('click', function () { media().currentTime = l.t; }); }
      box.appendChild(p); lyricsLines.push({ t: l.t, node: p });
    });
  }
  function loadLyrics(t) {
    if (!t || lyricsFor === t.id) return; lyricsFor = t.id;
    if (lyricsCache[t.id] !== undefined) { setLyrics(lyricsCache[t.id], t); return; }
    api('track', { id: t.id }, 'GET').then(function (j) { lyricsCache[t.id] = j.lyrics || ''; if (cur() && cur().id === t.id) setLyrics(lyricsCache[t.id], t); })
      .catch(function () { if (cur() && cur().id === t.id) setLyrics('', t); });
  }
  function updateLyricsHighlight(c) {
    if (!lyricsSynced || !lyricsLines.length || sheet.hidden) return;
    var lo = 0, hi = lyricsLines.length - 1, idx = -1;
    while (lo <= hi) { var mid = (lo + hi) >> 1; if (lyricsLines[mid].t <= c + 0.15) { idx = mid; lo = mid + 1; } else hi = mid - 1; }
    if (idx === lyricsActive) return;
    if (lyricsActive >= 0 && lyricsLines[lyricsActive]) lyricsLines[lyricsActive].node.classList.remove('is-active');
    lyricsActive = idx;
    if (idx >= 0) {
      var n = lyricsLines[idx].node; n.classList.add('is-active');
      var box = $('mzLyrics').parentNode; if (box && !box.hidden) { var top = n.offsetTop - box.clientHeight / 2 + n.clientHeight / 2; box.scrollTo ? box.scrollTo({ top: top, behavior: reduceMotion ? 'auto' : 'smooth' }) : (box.scrollTop = top); }
    }
  }

  /* ------------------------------------------------------------------ queue UI */
  var dragFrom = -1;
  function renderQueue() {
    var ol = $('mzQueue'); if (!ol || sheet.hidden) return;
    ol.textContent = '';
    state.order.forEach(function (qi, p) {
      var t = state.queue[qi]; if (!t) return;
      var li = el('li', 'mz-q-item' + (p === state.pos ? ' is-current' : '')); li.draggable = true; li.dataset.pos = p;
      li.appendChild(coverNode(t));
      var meta = el('div', 'mz-q-meta'); meta.appendChild(el('strong', null, t.title)); meta.appendChild(el('small', null, t.artist + (p === state.pos ? ' · playing' : '')));
      li.appendChild(meta);
      var rm = el('button', 'mz-q-remove', '✕'); rm.type = 'button'; rm.setAttribute('aria-label', 'Remove ' + t.title + ' from queue');
      rm.addEventListener('click', function (e) { e.stopPropagation(); removeFromQueue(p); });
      li.appendChild(rm);
      li.addEventListener('click', function () { state.pos = p; loadCurrent(true); });
      li.addEventListener('dragstart', function (e) { dragFrom = p; e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', String(p)); } catch (x) {} });
      li.addEventListener('dragover', function (e) { e.preventDefault(); li.classList.add('is-over'); });
      li.addEventListener('dragleave', function () { li.classList.remove('is-over'); });
      li.addEventListener('drop', function (e) { e.preventDefault(); li.classList.remove('is-over'); moveInQueue(dragFrom, p); });
      ol.appendChild(li);
    });
  }
  function moveInQueue(from, to) {
    if (from < 0 || from === to) return;
    var curQ = state.order[state.pos], item = state.order.splice(from, 1)[0]; state.order.splice(to, 0, item);
    state.pos = state.order.indexOf(curQ); renderQueue(); save(true);
  }
  function removeFromQueue(p) {
    if (state.order.length <= 1) { clearQueue(); return; }
    var qi = state.order[p], wasCurrent = p === state.pos;
    state.order.splice(p, 1); state.queue.splice(qi, 1);
    state.order = state.order.map(function (x) { return x > qi ? x - 1 : x; });
    if (p < state.pos) state.pos -= 1;
    if (wasCurrent) { if (state.pos >= state.order.length) state.pos = state.order.length - 1; loadCurrent(!media().paused); } else { renderQueue(); save(true); }
  }
  function clearQueue() {
    pauseMedia(); audio.removeAttribute('src'); audio.load(); state.queue = []; state.order = []; state.pos = -1;
    try { localStorage.removeItem(KEY); } catch (e) {}
    if (!sheet.hidden) closeSheet(); root.hidden = true; document.body.classList.remove('has-player'); markRows();
  }

  /* ---------------------------------------------------------------- visualiser */
  function startViz() { if (vizRaf || reduceMotion) return; vizRaf = window.requestAnimationFrame(drawViz); }
  function drawViz() {
    vizRaf = 0;
    if (sheet.hidden || mode === 'video' || !analyser || media().paused) { if (!sheet.hidden && !reduceMotion) vizRaf = window.requestAnimationFrame(drawViz); return; }
    var c = $('mzViz'), w = c.clientWidth, h = c.clientHeight; if (!w || !h) { vizRaf = window.requestAnimationFrame(drawViz); return; }
    if (c.width !== w) c.width = w; if (c.height !== h) c.height = h;
    var g = c.getContext('2d'), data = new Uint8Array(analyser.frequencyBinCount); analyser.getByteFrequencyData(data);
    g.clearRect(0, 0, w, h);
    var bars = 32, bw = w / bars, grad = g.createLinearGradient(0, h, 0, 0); grad.addColorStop(0, 'rgba(47,107,255,.9)'); grad.addColorStop(1, 'rgba(255,61,82,.95)'); g.fillStyle = grad;
    for (var i = 0; i < bars; i++) { var v = data[Math.floor(i * data.length / bars * 0.8)] / 255, bh = Math.max(2, v * h); g.fillRect(i * bw + 2, h - bh, bw - 4, bh); }
    vizRaf = window.requestAnimationFrame(drawViz);
  }

  /* ------------------------------------------------------------ sound panel */
  function buildSoundPanel() {
    var sel = $('mzEqPreset'); sel.textContent = '';
    Object.keys(EQ_PRESETS).concat(['custom']).forEach(function (k) { var o = el('option', null, k.charAt(0).toUpperCase() + k.slice(1)); o.value = k; sel.appendChild(o); });
    sel.value = state.eq;
    var box = $('mzEq'); box.textContent = '';
    EQ_FREQS.forEach(function (f, i) {
      var lab = el('label'); var inp = document.createElement('input'); inp.type = 'range'; inp.min = -12; inp.max = 12; inp.step = 1; inp.value = state.eqGains[i]; inp.setAttribute('aria-label', EQ_LABELS[i] + ' gain');
      inp.addEventListener('input', function () { state.eqGains[i] = +inp.value; state.eq = 'custom'; sel.value = 'custom'; applyEq(); save(true); });
      lab.appendChild(inp); lab.appendChild(el('span', null, EQ_LABELS[i])); box.appendChild(lab);
    });
    sel.addEventListener('change', function () {
      state.eq = sel.value; if (EQ_PRESETS[sel.value]) { state.eqGains = EQ_PRESETS[sel.value].slice(); Array.prototype.forEach.call(box.querySelectorAll('input'), function (inp, i) { inp.value = state.eqGains[i]; }); }
      if (!ctx) ensureGraph(); applyEq(); save(true);
    });
    $('mzSpeed').addEventListener('change', function () { state.speed = +this.value; audio.playbackRate = state.speed; video.playbackRate = state.speed; save(true); });
    $('mzSleep').addEventListener('change', function () { setSleep(this.value); });
  }
  function showSleepNote(msg) { var n = $('mzSleepNote'); n.hidden = !msg; n.textContent = msg || ''; }
  function setSleep(v) {
    window.clearTimeout(sleepTimer); sleepAtEnd = false;
    if (v === '0') { showSleepNote(''); return; }
    if (v === 'end') { sleepAtEnd = true; showSleepNote('Music will stop when this song ends.'); return; }
    var mins = +v; showSleepNote('Music will fade out in ' + mins + ' minutes.');
    sleepTimer = window.setTimeout(function () {
      var start = state.volume, steps = 20, i = 0;
      var iv = window.setInterval(function () { i++; audio.volume = video.volume = Math.max(0, start * (1 - i / steps)); if (i >= steps) { window.clearInterval(iv); pauseMedia(); applyVolume(); $('mzSleep').value = '0'; showSleepNote(''); } }, 120);
    }, mins * 60000);
  }

  /* ------------------------------------------------------------------- likes */
  function patchTrackEverywhere(id, patch) {
    state.queue.forEach(function (t) { if (t.id === id) Object.keys(patch).forEach(function (k) { t[k] = patch[k]; }); });
    Array.prototype.forEach.call(document.querySelectorAll('[data-track-id="' + id + '"]'), function (n) {
      try { var t = JSON.parse(n.getAttribute('data-track')); Object.keys(patch).forEach(function (k) { t[k] = patch[k]; }); n.setAttribute('data-track', JSON.stringify(t)); } catch (e) {}
      if ('liked' in patch) Array.prototype.forEach.call(n.querySelectorAll('.mz-like'), function (b) { b.classList.toggle('is-on', patch.liked); b.setAttribute('aria-pressed', patch.liked ? 'true' : 'false'); });
    });
  }
  function toggleLike(t) {
    if (!t) return;
    if (!LOGGED) { toast('Sign in to like songs.', '/TMZ%20PODCAST/login.php', 'Sign in'); return; }
    var want = !t.liked; patchTrackEverywhere(t.id, { liked: want }); render();
    api('like', { track_id: t.id }).then(function (j) { patchTrackEverywhere(t.id, { liked: j.liked }); render(); toast(j.liked ? 'Added to Liked songs' : 'Removed from Liked songs'); })
      .catch(function (e) { patchTrackEverywhere(t.id, { liked: !want }); render(); toast(e.message); });
  }

  /* ------------------------------------------------- download, share, playlists */
  function download(t, type) {
    if (!t) return;
    if (t.downloads_enabled === false) { toast('Downloads are currently disabled.'); return; }
    if (!t.can_download) { toast(t.download_hint || 'Download is not available.', /Sign in/.test(t.download_hint || '') ? '/TMZ%20PODCAST/login.php' : (/All Access/.test(t.download_hint || '') ? '/TMZ%20PODCAST/membership.php' : ''), /Sign in/.test(t.download_hint || '') ? 'Sign in' : 'See plans'); return; }
    var url = type === 'video' ? t.download_video : t.download;
    if (!url) { toast('No video file for this track.'); return; }
    var a = document.createElement('a'); a.href = url; a.rel = 'noopener'; document.body.appendChild(a); a.click(); a.remove(); toast('Download started');
  }
  function share(t) {
    var url = new URL(t.url, location.href).href;
    if (navigator.share) { navigator.share({ title: t.title, text: t.title + ' · ' + t.artist, url: url }).catch(function () {}); return; }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(url).then(function () { toast('Link copied'); }, function () { toast(url); }); else toast(url);
  }

  var modal = $('mzModal'), modalCard = modal.querySelector('.mz-modal-card');
  function openModal() { modal.hidden = false; }
  function closeModal() { modal.hidden = true; modalCard.textContent = ''; }
  modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });

  function choosePlaylist(t) {
    if (!LOGGED) { toast('Sign in to build playlists.', '/TMZ%20PODCAST/login.php', 'Sign in'); return; }
    modalCard.textContent = ''; modalCard.appendChild(el('h3', null, 'Add to playlist')); modalCard.appendChild(el('p', 'mz-muted', 'Loading…')); openModal();
    api('playlists', {}, 'GET').then(function (j) {
      modalCard.textContent = ''; modalCard.appendChild(el('h3', null, 'Add to playlist'));
      (j.playlists || []).forEach(function (p) {
        var b = el('button', 'mz-pl-item'); b.type = 'button'; b.appendChild(el('span', null, p.name)); b.appendChild(el('small', 'mz-muted', p.tracks + ' songs'));
        b.addEventListener('click', function () { api('playlist_add', { playlist_id: p.id, track_id: t.id }).then(function () { closeModal(); toast('Added to ' + p.name); }).catch(function (e) { toast(e.message); }); });
        modalCard.appendChild(b);
      });
      var inp = document.createElement('input'); inp.placeholder = 'New playlist name'; inp.maxLength = 120; inp.setAttribute('aria-label', 'New playlist name');
      var actions = el('div', 'mz-modal-actions'); var cancel = el('button', 'btn btn-outline btn-small', 'Cancel'); cancel.type = 'button'; cancel.addEventListener('click', closeModal);
      var create = el('button', 'btn btn-primary btn-small', 'Create & add'); create.type = 'button';
      create.addEventListener('click', function () { api('playlist_create', { name: inp.value, track_id: t.id }).then(function () { closeModal(); toast('Playlist created'); }).catch(function (e) { toast(e.message); }); });
      modalCard.appendChild(inp); actions.appendChild(cancel); actions.appendChild(create); modalCard.appendChild(actions); inp.focus();
    }).catch(function (e) { closeModal(); toast(e.message); });
  }
  function newPlaylistDialog() {
    modalCard.textContent = ''; modalCard.appendChild(el('h3', null, 'New playlist'));
    var inp = document.createElement('input'); inp.placeholder = 'Playlist name'; inp.maxLength = 120; inp.setAttribute('aria-label', 'Playlist name'); modalCard.appendChild(inp);
    var actions = el('div', 'mz-modal-actions'); var cancel = el('button', 'btn btn-outline btn-small', 'Cancel'); cancel.type = 'button'; cancel.addEventListener('click', closeModal);
    var ok = el('button', 'btn btn-primary btn-small', 'Create'); ok.type = 'button';
    ok.addEventListener('click', function () { api('playlist_create', { name: inp.value }).then(function () { closeModal(); navigate(location.href, false); }).catch(function (e) { toast(e.message); }); });
    actions.appendChild(cancel); actions.appendChild(ok); modalCard.appendChild(actions); openModal(); inp.focus();
  }

  /* ------------------------------------------------------------ context menu */
  var menu = $('mzMenu'), menuOpenedAt = 0;
  function closeMenu() { menu.hidden = true; menu.textContent = ''; }
  function openMenu(t, anchor, node) {
    menu.textContent = '';
    function item(label, fn, disabled) { var b = el('button', null, label); b.type = 'button'; b.setAttribute('role', 'menuitem'); if (disabled) b.disabled = true; b.addEventListener('click', function () { closeMenu(); fn(); }); menu.appendChild(b); }
    item('Play next', function () { enqueue(t, true); }, t.locked);
    item('Add to queue', function () { enqueue(t, false); }, t.locked);
    item('Add to playlist…', function () { choosePlaylist(t); });
    item(t.liked ? 'Remove from Liked songs' : 'Add to Liked songs', function () { toggleLike(t); });
    var list = node && node.closest('[data-playlist-id]'); var pid = list ? +list.getAttribute('data-playlist-id') : 0;
    if (pid) item('Remove from this playlist', function () { api('playlist_remove', { playlist_id: pid, track_id: t.id }).then(function () { if (node) node.remove(); toast('Removed from playlist'); }).catch(function (e) { toast(e.message); }); });
    menu.appendChild(document.createElement('hr'));
    var a1 = el('a', null, 'Go to artist'); a1.href = '/TMZ%20PODCAST/music.php?artist=' + encodeURIComponent(t.artist_slug); menu.appendChild(a1);
    if (t.album_slug) { var a2 = el('a', null, 'Go to album'); a2.href = '/TMZ%20PODCAST/music.php?album=' + encodeURIComponent(t.album_slug); menu.appendChild(a2); }
    menu.appendChild(document.createElement('hr'));
    item('Share', function () { share(t); });
    if (t.downloads_enabled !== false) {
      item(t.can_download ? 'Download audio' : 'Download audio (locked)', function () { download(t); });
      if (t.download_video) item(t.can_download ? 'Download video' : 'Download video (locked)', function () { download(t, 'video'); });
    }
    menu.hidden = false; menuOpenedAt = Date.now();
    var r = anchor.getBoundingClientRect(), mw = menu.offsetWidth, mh = menu.offsetHeight;
    var left = Math.min(Math.max(8, r.right - mw), window.innerWidth - mw - 8), top = r.bottom + 6; if (top + mh > window.innerHeight - 8) top = Math.max(8, r.top - mh - 6);
    menu.style.left = left + 'px'; menu.style.top = top + 'px';
    var first = menu.querySelector('button:not([disabled]), a'); if (first) first.focus();
  }
  document.addEventListener('click', function (e) { if (!menu.hidden && !menu.contains(e.target) && !e.target.closest('[data-act="menu"], #mzSheetMenu')) closeMenu(); });
  window.addEventListener('scroll', function () { if (!menu.hidden && Date.now() - menuOpenedAt > 500) closeMenu(); }, { passive: true });

  /* -------------------------------------------------------- page interactions */
  function trackFrom(node) { try { return JSON.parse(node.getAttribute('data-track')); } catch (e) { return null; } }

  function playFrom(node, opts) {
    opts = opts || {};
    var t = trackFrom(node); if (!t) return;
    if (t.locked) { toast(t.lock_hint || 'This track is locked.', /All Access/.test(t.lock_hint || '') ? '/TMZ%20PODCAST/membership.php' : '/TMZ%20PODCAST/login.php', /All Access/.test(t.lock_hint || '') ? 'See plans' : 'Sign in'); return; }
    var c = cur();
    if (c && c.id === t.id && !opts.video) { toggle(); return; }
    var list = node.closest('[data-track-list]'), tracks = [], idx = 0;
    if (list) {
      Array.prototype.forEach.call(list.querySelectorAll('[data-track]'), function (n) { var x = trackFrom(n); if (x) { if (n === node || n.contains(node)) idx = tracks.length; tracks.push(x); } });
    } else tracks = [t];
    setQueue(tracks, idx);
    if (opts.video && t.video) { openSheet('lyrics'); setMode('video'); }
  }

  document.addEventListener('click', function (e) {
    var listBtn = e.target.closest('[data-play-list]');
    if (listBtn) {
      var box = document.querySelector(listBtn.getAttribute('data-play-list')); if (!box) return;
      var tracks = Array.prototype.map.call(box.querySelectorAll('[data-track]'), trackFrom).filter(Boolean).filter(function (t) { return !t.locked; });
      if (!tracks.length) { toast('Nothing playable here yet.'); return; }
      var sh = listBtn.getAttribute('data-shuffle') === '1'; state.shuffle = sh; setQueue(tracks, 0, sh ? { shuffle: true } : {}); return;
    }
    var btn = e.target.closest('[data-act]'); if (!btn) return;
    var node = btn.closest('[data-track]'); if (!node) return;
    var act = btn.getAttribute('data-act'), t = trackFrom(node); if (!t) return;
    if (act === 'play') { e.preventDefault(); playFrom(node); }
    else if (act === 'play-video') { e.preventDefault(); playFrom(node, { video: true }); }
    else if (act === 'like') { e.preventDefault(); toggleLike(cur() && cur().id === t.id ? cur() : t); }
    else if (act === 'menu') { e.preventDefault(); openMenu(t, btn, node); }
    else if (act === 'download') { e.preventDefault(); download(t); }
    else if (act === 'download-video') { e.preventDefault(); download(t, 'video'); }
    else if (act === 'share') { e.preventDefault(); share(t); }
  });

  /* ------------------------------------------------------------ bar controls */
  $('mzPlay').addEventListener('click', toggle); $('mzPlayMobile').addEventListener('click', toggle);
  $('mzNext').addEventListener('click', function () { next(false); }); $('mzPrev').addEventListener('click', prev);
  $('mzShuffle').addEventListener('click', toggleShuffle);
  function toggleShuffle() {
    state.shuffle = !state.shuffle; var c = state.order[state.pos];
    if (state.shuffle) buildOrder(c); else { state.order = state.queue.map(function (_, i) { return i; }); state.pos = c; }
    render(); save(true); toast(state.shuffle ? 'Shuffle on' : 'Shuffle off');
  }
  $('mzRepeat').addEventListener('click', cycleRepeat);
  function cycleRepeat() { state.repeat = state.repeat === 'off' ? 'all' : (state.repeat === 'all' ? 'one' : 'off'); render(); save(true); toast('Repeat: ' + state.repeat); }
  $('mzBarLike').addEventListener('click', function () { toggleLike(cur()); });
  $('mzDownload').addEventListener('click', function () { download(cur()); });
  $('mzQueueBtn').addEventListener('click', function () { openSheet('queue'); });
  $('mzVideoBtn').addEventListener('click', function () { openSheet('lyrics'); setMode('video'); });
  $('mzOpenSheet').addEventListener('click', function () { openSheet(); });
  $('mzCloseSheet').addEventListener('click', closeSheet);
  $('mzSheetMenu').addEventListener('click', function (e) { if (cur()) openMenu(cur(), e.currentTarget); });
  $('mzClearQueue').addEventListener('click', clearQueue);
  var closeBtn = $('mzClose'); if (closeBtn) closeBtn.addEventListener('click', function () { clearQueue(); toast('Player closed.'); });
  $('mzMute').addEventListener('click', function () { state.muted = !state.muted; applyVolume(); render(); save(true); });
  $('mzVolume').addEventListener('input', function () { state.volume = this.value / 100; state.muted = false; applyVolume(); setFill(this); render(); save(false); });
  $('mzModeBtn').addEventListener('click', function () { setMode(mode === 'video' ? 'audio' : 'video'); });
  $('mzPip').addEventListener('click', function () { if (video.requestPictureInPicture) video.requestPictureInPicture().catch(function () { toast('Picture in picture is not available.'); }); });
  $('mzFullscreen').addEventListener('click', function () { var f = video.requestFullscreen || video.webkitRequestFullscreen || video.webkitEnterFullscreen; if (f) f.call(video); });
  Array.prototype.forEach.call(sheet.querySelectorAll('.mz-panel-tabs button'), function (b) { b.addEventListener('click', function () { selectTab(b.dataset.tab); if (b.dataset.tab === 'lyrics') loadLyrics(cur()); if (b.dataset.tab === 'queue') renderQueue(); }); });

  var seekEl = $('mzSeek');
  seekEl.addEventListener('pointerdown', function () { seeking = true; });
  seekEl.addEventListener('input', function () { var m = media(), d = m.duration || (cur() ? cur().duration : 0); setFill(seekEl); $('mzCur').textContent = fmt(d * seekEl.value / 1000); });
  seekEl.addEventListener('change', function () { var m = media(), d = m.duration || (cur() ? cur().duration : 0); if (d) m.currentTime = d * seekEl.value / 1000; seeking = false; });
  document.addEventListener('pointerup', function () { seeking = false; });

  /* --------------------------------------------------------------- keyboard */
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { if (!modal.hidden) closeModal(); else if (!menu.hidden) closeMenu(); else if (!sheet.hidden) closeSheet(); return; }
    if (e.ctrlKey || e.metaKey || e.altKey || !cur()) return;
    var tg = e.target, tag = tg && tg.tagName; if (tag === 'INPUT' && tg.type !== 'range' || tag === 'TEXTAREA' || tag === 'SELECT' || (tg && tg.isContentEditable)) return;
    var k = e.key, m = media();
    if (k === ' ' || k === 'Spacebar') { if (tag === 'BUTTON' || tag === 'A' || tag === 'SUMMARY') return; e.preventDefault(); toggle(); }
    else if (k === 'ArrowRight') { e.preventDefault(); if (e.shiftKey) next(false); else m.currentTime = Math.min((m.duration || 1e9), m.currentTime + 5); }
    else if (k === 'ArrowLeft') { e.preventDefault(); if (e.shiftKey) prev(); else m.currentTime = Math.max(0, m.currentTime - 5); }
    else if ((k === 'ArrowUp' || k === 'ArrowDown') && tag !== 'INPUT') { e.preventDefault(); state.volume = Math.max(0, Math.min(1, state.volume + (k === 'ArrowUp' ? 0.05 : -0.05))); state.muted = false; applyVolume(); render(); save(false); }
    else if (k === 'm' || k === 'M') { state.muted = !state.muted; applyVolume(); render(); }
    else if (k === 's' || k === 'S') toggleShuffle();
    else if (k === 'r' || k === 'R') cycleRepeat();
    else if (k === 'l' || k === 'L') toggleLike(cur());
    else if (k === 'q' || k === 'Q') { sheet.hidden ? openSheet('queue') : selectTab('queue'); }
  });

  /* ----------------------------------------------- in-page navigation (Music Zone) */
  function navigate(url, push) {
    var app = document.getElementById('musicApp'); if (!app) { location.href = url; return; }
    app.setAttribute('aria-busy', 'true');
    fetch(url, { credentials: 'same-origin', headers: { 'X-Requested-With': 'tmz-pjax' } })
      .then(function (r) { if (!r.ok) throw new Error('status ' + r.status); return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html'), next = doc.getElementById('musicApp');
        if (!next) throw new Error('not a music page');
        app.innerHTML = next.innerHTML; app.setAttribute('data-music-view', next.getAttribute('data-music-view') || '');
        document.title = doc.title || document.title;
        if (push) history.pushState({ mz: 1 }, '', url);
        window.scrollTo(0, 0); app.removeAttribute('aria-busy'); markRows();
        var np = document.getElementById('mzNewPlaylist'); if (np) np.addEventListener('click', newPlaylistDialog);
      })
      .catch(function () { location.href = url; });
  }
  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest('a[href]'); if (!a || a.target || a.hasAttribute('download')) return;
    var app = document.getElementById('musicApp'); if (!app || !(app.contains(a) || menu.contains(a))) return;
    var u; try { u = new URL(a.href, location.href); } catch (x) { return; }
    if (u.origin !== location.origin || !/\/music\.php$/.test(u.pathname)) return;
    e.preventDefault(); closeMenu(); if (!sheet.hidden) closeSheet(); navigate(u.href, true);
  });
  document.addEventListener('submit', function (e) {
    var f = e.target; if (!f.classList || !f.classList.contains('mz-search') || !document.getElementById('musicApp')) return;
    e.preventDefault(); var q = new FormData(f).get('q'); navigate(f.action + '?q=' + encodeURIComponent(q || ''), true);
  });
  window.addEventListener('popstate', function () { if (document.getElementById('musicApp')) navigate(location.href, false); });
  var np0 = document.getElementById('mzNewPlaylist'); if (np0) np0.addEventListener('click', newPlaylistDialog);
  document.addEventListener('click', function (e) { if (e.target && e.target.id === 'mzNewPlaylist') newPlaylistDialog(); });

  /* ------------------------------------------------------------------- init */
  var restored = restore();
  buildSoundPanel();
  setFill($('mzVolume')); setFill($('mzSeek'));
  applyVolume();
  if (restored) { document.body.classList.add('has-player'); root.hidden = false; var t0 = cur(); if (t0 && !t0.locked) { loadCurrent(false); } else { render(); } }
  window.TMZPlayer = { state: state, play: function () { playMedia(); }, pause: pauseMedia, next: function () { next(false); }, prev: prev, setQueue: setQueue };
})();
