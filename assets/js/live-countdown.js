/* Live countdown + local times + "Remind me" buttons (lobby and scheduled-show page). */
(function () {
  'use strict';
  if (window.__tmzLiveCountdown) return;
  window.__tmzLiveCountdown = true;

  // Times are stored in UTC and shown in the visitor's own time zone.
  document.querySelectorAll('[data-lx-localtime]').forEach(function (n) {
    var d = new Date(n.getAttribute('data-lx-localtime'));
    if (isNaN(d)) return;
    try {
      n.textContent = d.toLocaleString(undefined, { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
    } catch (e) { n.textContent = d.toLocaleString(); }
  });

  var counters = Array.prototype.slice.call(document.querySelectorAll('[data-lx-countdown]'));
  if (counters.length) {
    var skew = 0; // server clock minus browser clock
    var nowAttr = counters[0].getAttribute('data-now');
    if (nowAttr && !isNaN(Date.parse(nowAttr))) skew = Date.parse(nowAttr) - Date.now();
    var reloaded = false;

    var pad = function (n) { return (n < 10 ? '0' : '') + n; };
    var tick = function () {
      var now = Date.now() + skew;
      counters.forEach(function (c) {
        var target = Date.parse(c.getAttribute('data-lx-countdown'));
        if (isNaN(target)) return;
        var ms = target - now;
        if (ms <= 0) {
          c.textContent = 'Starting any moment…';
          c.classList.add('is-due');
          return;
        }
        var s = Math.floor(ms / 1000), d = Math.floor(s / 86400), h = Math.floor(s % 86400 / 3600), m = Math.floor(s % 3600 / 60);
        c.textContent = (d ? d + 'd ' : '') + pad(h) + ':' + pad(m) + ':' + pad(s % 60);
      });
    };
    tick();
    window.setInterval(tick, 1000);

    // When the countdown for the featured show is due, check whether it went live and reload once if so.
    var featured = counters[0];
    var liveId = featured.getAttribute('data-live-id');
    if (liveId) {
      window.setInterval(function () {
        if (reloaded || document.hidden) return;
        var target = Date.parse(featured.getAttribute('data-lx-countdown'));
        if (!isNaN(target) && target - (Date.now() + skew) > 60000) return;
        fetch('/api/live-extras.php?action=state&live_id=' + encodeURIComponent(liveId), { credentials: 'same-origin', cache: 'no-store' })
          .then(function (r) { return r.json(); })
          .then(function (s) { if (s && s.live && s.live.status === 'live' && !reloaded) { reloaded = true; window.location.reload(); } })
          .catch(function () {});
      }, 10000);
    }
  }

  document.querySelectorAll('[data-lx-remind]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.getAttribute('data-logged-in') !== '1') { window.location.href = '/login.php'; return; }
      var body = new URLSearchParams();
      body.set('action', 'remind');
      body.set('live_id', btn.getAttribute('data-lx-remind'));
      body.set('csrf_token', btn.getAttribute('data-csrf'));
      btn.disabled = true;
      fetch('/api/live-extras.php', { method: 'POST', body: body, credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j && j.error) throw new Error(j.error);
          btn.setAttribute('aria-pressed', j.reminder ? 'true' : 'false');
          btn.textContent = j.reminder ? '🔔 Reminder set' : '🔔 Remind me';
        })
        .catch(function (e) { window.alert(e.message || 'Could not save the reminder.'); })
        .then(function () { btn.disabled = false; });
    });
  });
})();
