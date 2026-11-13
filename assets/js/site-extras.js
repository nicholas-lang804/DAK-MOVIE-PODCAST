/* Site extras: cookie notice, "Add to My List" buttons, service worker registration. */
(function () {
  'use strict';

  /* ---- cookie notice (informational: the site only sets essential cookies) ---- */
  var notice = document.getElementById('cookieNotice');
  var ok = document.getElementById('cookieNoticeOk');
  var seen = false;
  try { seen = window.localStorage.getItem('tmz_cookie_notice') === '1'; } catch (e) { seen = false; }
  if (notice && !seen) notice.hidden = false;
  if (ok) ok.addEventListener('click', function () {
    try { window.localStorage.setItem('tmz_cookie_notice', '1'); } catch (e) {}
    if (notice) notice.hidden = true;
  });

  /* ---- watchlist buttons ---- */
  document.querySelectorAll('[data-watchlist]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      if (btn.getAttribute('data-logged-in') !== '1') { window.location.href = '/login.php'; return; }
      var body = new URLSearchParams();
      body.set('movie_id', btn.getAttribute('data-watchlist'));
      body.set('csrf_token', btn.getAttribute('data-csrf'));
      btn.disabled = true;
      fetch('/api/watchlist.php', { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Could not update your list.'); return j; }); })
        .then(function (j) {
          btn.setAttribute('aria-pressed', j.saved ? 'true' : 'false');
          btn.textContent = j.saved ? '✓ In My List' : '＋ Add to My List';
        })
        .catch(function (e) { window.alert(e.message); })
        .then(function () { btn.disabled = false; });
    });
  });

  /* ---- service worker (https or localhost only) ---- */
  if ('serviceWorker' in navigator && (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1')) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
    });
  }
})();
