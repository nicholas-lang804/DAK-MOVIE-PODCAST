(function () {
  'use strict';

  function parseTime(value) {
    const match = /^(\d{1,5}):([0-5]\d):([0-5]\d)$/.exec(value.trim());
    if (!match) return null;
    return ((Number(match[1]) * 3600) + (Number(match[2]) * 60) + Number(match[3])) * 1000;
  }

  function updateLinks(form) {
    const from = form.elements.from.value.trim();
    const to = form.elements.to.value.trim();
    const nav = form.parentElement.querySelector('.live-transcript-downloads');
    if (!nav) return;
    nav.querySelectorAll('a[href*="live-captions.php"]').forEach(link => {
      const url = new URL(link.href, window.location.href);
      url.searchParams.delete('from_ms');
      url.searchParams.delete('to_ms');
      if (from && to) {
        url.searchParams.set('from_ms', String(parseTime(from)));
        url.searchParams.set('to_ms', String(parseTime(to)));
      }
      link.href = url.toString();
    });
  }

  document.querySelectorAll('.live-transcript-range').forEach(form => {
    form.addEventListener('submit', event => {
      event.preventDefault();
      const from = form.elements.from.value.trim();
      const to = form.elements.to.value.trim();
      if (!from && !to) {
        updateLinks(form);
        return;
      }
      const fromMs = parseTime(from);
      const toMs = parseTime(to);
      if (fromMs === null || toMs === null || toMs < fromMs) {
        window.alert('Enter both times as HH:MM:SS, with the end time after the start time.');
        return;
      }
      updateLinks(form);
    });
    form.addEventListener('reset', () => window.setTimeout(() => updateLinks(form), 0));
  });
})();
