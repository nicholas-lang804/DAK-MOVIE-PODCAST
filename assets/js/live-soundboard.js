/* Live Studio soundboard: synthesized applause/cheer/etc. for the admin,
 * plus an emoji reaction broadcast to viewers via api/live-extras.php. */
(function () {
  var board = document.getElementById('liveSoundboard');
  if (!board) return;

  var liveId = Number(board.dataset.liveId || 0);
  var api = '/api/live-extras.php';
  var csrf = (document.querySelector('#liveStudioCsrf input[name="csrf_token"]') || {}).value || '';
  var ctx = null;

  function audioCtx() {
    if (!ctx) ctx = new (window.AudioContext || window.webkitAudioContext)();
    if (ctx.state === 'suspended') ctx.resume();
    return ctx;
  }

  function noiseBuffer(seconds) {
    var c = audioCtx(), len = Math.floor(c.sampleRate * seconds);
    var buf = c.createBuffer(1, len, c.sampleRate), d = buf.getChannelData(0);
    for (var i = 0; i < len; i++) d[i] = Math.random() * 2 - 1;
    return buf;
  }

  function env(g, t, a, peak, d, sustain) {
    g.gain.setValueAtTime(0.0001, t);
    g.gain.exponentialRampToValueAtTime(Math.max(peak, 0.0002), t + a);
    g.gain.exponentialRampToValueAtTime(Math.max(sustain || 0.0001, 0.0001), t + a + d);
  }

  /* One hand-clap: short noise burst through a band-pass. */
  function clap(c, t, vol) {
    var src = c.createBufferSource(); src.buffer = noiseBuffer(0.09);
    var bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.value = 1600 + Math.random() * 900; bp.Q.value = 1.4;
    var g = c.createGain(); env(g, t, 0.004, vol, 0.07);
    src.connect(bp); bp.connect(g); g.connect(c.destination);
    src.start(t); src.stop(t + 0.12);
  }

  /* Applause: many randomized claps swelling then fading. */
  function applause(seconds, vol) {
    var c = audioCtx(), t0 = c.currentTime + 0.03;
    var n = Math.floor(seconds * 26);
    for (var i = 0; i < n; i++) {
      var t = t0 + (i / n) * seconds + Math.random() * 0.03;
      var swell = Math.sin((i / n) * Math.PI); // quiet start/end, loud middle
      clap(c, t, vol * (0.35 + 0.65 * swell) * (0.6 + Math.random() * 0.5));
    }
    // low crowd bed
    var src = c.createBufferSource(); src.buffer = noiseBuffer(seconds + 0.3); src.loop = true;
    var lp = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 500;
    var g = c.createGain(); env(g, t0, 0.25, vol * 0.35, seconds * 0.7);
    src.connect(lp); lp.connect(g); g.connect(c.destination);
    src.start(t0); src.stop(t0 + seconds + 0.4);
  }

  function cheer() {
    var c = audioCtx(), t0 = c.currentTime + 0.03, dur = 2.6;
    applause(dur * 0.8, 0.5);
    // rising crowd roar
    var src = c.createBufferSource(); src.buffer = noiseBuffer(dur + 0.3); src.loop = true;
    var bp = c.createBiquadFilter(); bp.type = 'bandpass'; bp.frequency.setValueAtTime(700, t0); bp.frequency.linearRampToValueAtTime(1600, t0 + dur * 0.6); bp.Q.value = 0.8;
    var g = c.createGain(); env(g, t0, 0.3, 0.4, dur);
    src.connect(bp); bp.connect(g); g.connect(c.destination);
    src.start(t0); src.stop(t0 + dur + 0.3);
    // whistle glide
    var o = c.createOscillator(); o.type = 'sine';
    o.frequency.setValueAtTime(1400, t0 + 0.4); o.frequency.linearRampToValueAtTime(2100, t0 + 1.2); o.frequency.linearRampToValueAtTime(1600, t0 + 2.2);
    var og = c.createGain(); env(og, t0 + 0.4, 0.15, 0.08, 1.8);
    o.connect(og); og.connect(c.destination); o.start(t0 + 0.4); o.stop(t0 + 2.6);
  }

  function clapBurst() {
    var c = audioCtx(), t0 = c.currentTime + 0.02;
    for (var i = 0; i < 5; i++) clap(c, t0 + i * 0.16, 0.9);
  }

  function whistle() {
    var c = audioCtx(), t0 = c.currentTime + 0.03;
    var o = c.createOscillator(); o.type = 'sine';
    o.frequency.setValueAtTime(2300, t0);
    o.frequency.linearRampToValueAtTime(2900, t0 + 0.35);
    o.frequency.setValueAtTime(2900, t0 + 0.55);
    o.frequency.linearRampToValueAtTime(2400, t0 + 0.85);
    var vib = c.createOscillator(); vib.frequency.value = 28;
    var vg = c.createGain(); vg.gain.value = 60;
    vib.connect(vg); vg.connect(o.frequency);
    var g = c.createGain(); env(g, t0, 0.05, 0.22, 0.85);
    o.connect(g); g.connect(c.destination);
    o.start(t0); o.stop(t0 + 1); vib.start(t0); vib.stop(t0 + 1);
  }

  function fanfare() {
    var c = audioCtx(), t0 = c.currentTime + 0.03;
    var notes = [523.25, 659.25, 783.99, 1046.5];
    notes.forEach(function (f, i) {
      var o = c.createOscillator(); o.type = 'triangle'; o.frequency.value = f;
      var g = c.createGain(); env(g, t0 + i * 0.13, 0.02, 0.25, 0.5);
      o.connect(g); g.connect(c.destination); o.start(t0 + i * 0.13); o.stop(t0 + i * 0.13 + 0.6);
    });
    applause(1.4, 0.35);
  }

  function laugh() {
    var c = audioCtx(), t0 = c.currentTime + 0.03;
    for (var i = 0; i < 8; i++) {
      var t = t0 + i * 0.22 + Math.random() * 0.05;
      var o = c.createOscillator(); o.type = 'sawtooth';
      o.frequency.setValueAtTime(300 + Math.random() * 120, t);
      o.frequency.exponentialRampToValueAtTime(180 + Math.random() * 80, t + 0.14);
      var lp = c.createBiquadFilter(); lp.type = 'lowpass'; lp.frequency.value = 900;
      var g = c.createGain(); env(g, t, 0.02, 0.16, 0.15);
      o.connect(lp); lp.connect(g); g.connect(c.destination);
      o.start(t); o.stop(t + 0.2);
    }
  }

  var sounds = {
    applause: { label: '👏 Applause', fn: function () { applause(1.8, 0.5); }, emoji: '👏' },
    cheer: { label: '🎉 Cheer', fn: cheer, emoji: '🎉' },
    clap: { label: '👏 Clap clap', fn: clapBurst, emoji: '👏' },
    whistle: { label: '📣 Whistle', fn: whistle, emoji: '😮' },
    fanfare: { label: '🏆 Fanfare', fn: fanfare, emoji: '🎉' },
    laugh: { label: '😂 Laugh', fn: laugh, emoji: '😂' }
  };

  function react(emoji) {
    if (!liveId || !csrf) return;
    var body = new URLSearchParams({ action: 'react', live_id: String(liveId), emoji: emoji, csrf_token: csrf });
    fetch(api, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body })
      .catch(function () {});
  }

  board.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-sound]');
    if (!b) return;
    var s = sounds[b.dataset.sound];
    if (!s) return;
    try { s.fn(); } catch (err) { console.warn('Sound failed:', err); }
    react(s.emoji);
    b.classList.add('is-playing');
    window.setTimeout(function () { b.classList.remove('is-playing'); }, 400);
  });
})();
