(function () {
  'use strict';

  [
    { buttonId: 'toggleLiveRequests', listId: 'liveRequestsList', openLabel: 'Open Requests', closeLabel: 'Close Requests' },
    { buttonId: 'toggleLiveComments', listId: 'liveCommentsList', openLabel: 'Open Comments', closeLabel: 'Close Comments' }
  ].forEach(({ buttonId, listId, openLabel, closeLabel }) => {
    const toggle = document.getElementById(buttonId);
    const list = document.getElementById(listId);
    if (!toggle || !list) return;

    toggle.addEventListener('click', () => {
      const opening = list.hidden;
      list.hidden = !opening;
      toggle.setAttribute('aria-expanded', opening ? 'true' : 'false');
      toggle.textContent = opening ? closeLabel : openLabel;
    });
  });
})();
