/* Live Extras (viewer): topic banner, pinned message, shout-outs, polls, Q&A, viewer count, floating reactions. */
(function () {
  'use strict';
  var root = document.getElementById('liveExtras');
  if (!root) return;
  if (root.dataset.lxInit === '1') return; // never initialise twice
  root.dataset.lxInit = '1';

  var api = root.dataset.api;
  var liveId = root.dataset.liveId;
  var csrf = root.dataset.csrf;
  var loggedIn = root.dataset.loggedIn === '1';
  var since = 0;
  var timer = null;
  var failures = 0;
  var lastQuestionsKey = '';
  var lastPollKey = '';
  var shoutKey = '';

  var $ = function (id) { return document.getElementById(id); };
  var viewersEl = $('lxViewers'), topicEl = $('lxTopic'), pinnedEl = $('lxPinned'), shoutEl = $('lxShoutout');
  var pollEl = $('lxPoll'), qList = $('lxQuestions'), askForm = $('lxAskForm'), askInput = $('lxAskInput'), askMsg = $('lxAskMessage');
  var reactBar = $('lxReactBar');

  function node(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }

  function post(action, data) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('live_id', liveId);
    body.set('csrf_token', csrf);
    Object.keys(data || {}).forEach(function (k) { body.set(k, data[k]); });
    return fetch(api, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok) throw new Error(j.error || 'Something went wrong. Please try again.');
          return j;
        });
      });
  }

  /* ---------------------------------------------------------------- render */

  function renderStage(stage, viewers, peak) {
    if (viewers !== null && viewers !== undefined) {
      viewersEl.hidden = false;
      viewersEl.textContent = '👁 ' + viewers + (viewers === 1 ? ' viewer' : ' viewers') + (peak > viewers ? ' · peak ' + peak : '');
    }
    var topic = (stage && stage.topic) || '';
    topicEl.hidden = !topic;
    if (topic) topicEl.querySelector('strong').textContent = topic;

    var pinned = (stage && stage.pinned_message) || '';
    pinnedEl.hidden = !pinned;
    if (pinned) pinnedEl.querySelector('span').textContent = pinned;

    var shout = (stage && stage.shoutout) || '';
    if (shout && shout !== shoutKey) {
      shoutKey = shout;
      shoutEl.hidden = false;
      shoutEl.textContent = '📣 ' + shout;
      shoutEl.classList.remove('lx-pop'); void shoutEl.offsetWidth; shoutEl.classList.add('lx-pop');
    } else if (!shout) {
      shoutKey = '';
      shoutEl.hidden = true;
    }
  }

  function renderPoll(poll) {
    var key = JSON.stringify(poll || null);
    if (key === lastPollKey) return;
    lastPollKey = key;
    pollEl.textContent = '';
    if (!poll) { pollEl.hidden = true; return; }
    pollEl.hidden = false;
    var open = poll.status === 'open';
    var head = node('div', 'lx-card-head');
    head.appendChild(node('span', 'lx-tag' + (open ? ' lx-tag-live' : ''), open ? 'LIVE POLL' : 'POLL CLOSED'));
    head.appendChild(node('span', 'lx-muted', poll.total + (poll.total === 1 ? ' vote' : ' votes')));
    pollEl.appendChild(head);
    pollEl.appendChild(node('h3', 'lx-question', poll.question));

    var canVote = open && loggedIn && !poll.my_vote;
    poll.options.forEach(function (opt) {
      var pct = poll.total ? Math.round(opt.votes * 100 / poll.total) : 0;
      if (canVote) {
        var btn = node('button', 'lx-option', opt.label);
        btn.type = 'button';
        btn.addEventListener('click', function () {
          pollEl.querySelectorAll('button').forEach(function (b) { b.disabled = true; });
          post('vote', { poll_id: poll.id, option_id: opt.id })
            .then(function (j) { lastPollKey = ''; renderPoll(j.poll); })
            .catch(function (e) { lastPollKey = ''; flash(e.message); poll_refresh(); });
        });
        pollEl.appendChild(btn);
      } else {
        var row = node('div', 'lx-result' + (poll.my_vote === opt.id ? ' is-mine' : ''));
        var bar = node('i', 'lx-bar'); bar.style.width = pct + '%';
        row.appendChild(bar);
        row.appendChild(node('span', 'lx-result-label', opt.label + (poll.my_vote === opt.id ? '  ✓' : '')));
        row.appendChild(node('b', 'lx-result-pct', pct + '%'));
        pollEl.appendChild(row);
      }
    });
    if (open && !loggedIn) {
      var a = node('a', 'lx-login', 'Sign in to vote'); a.href = '/login.php';
      pollEl.appendChild(a);
    }
  }

  function renderQuestions(list) {
    var key = JSON.stringify(list || []);
    if (key === lastQuestionsKey) return;
    lastQuestionsKey = key;
    qList.textContent = '';
    if (!list || !list.length) {
      qList.appendChild(node('li', 'lx-empty', 'No questions yet. Be the first to ask.'));
      return;
    }
    list.forEach(function (q) {
      var li = node('li', 'lx-q' + (q.status === 'answered' ? ' is-answered' : ''));
      var btn = node('button', 'lx-vote' + (q.mine ? ' is-on' : ''));
      btn.type = 'button';
      btn.setAttribute('aria-label', (q.mine ? 'Remove your upvote from' : 'Upvote') + ' this question');
      btn.appendChild(node('span', null, '▲'));
      btn.appendChild(node('b', null, String(q.votes)));
      btn.disabled = !loggedIn;
      btn.addEventListener('click', function () {
        post('upvote', { question_id: q.id }).then(function (j) { renderQuestions(j.questions); }).catch(function (e) { flash(e.message); });
      });
      var body = node('div', 'lx-q-body');
      body.appendChild(node('p', null, q.text));
      body.appendChild(node('small', 'lx-muted', q.author + (q.status === 'answered' ? ' · answered ✓' : '')));
      li.appendChild(btn); li.appendChild(body);
      qList.appendChild(li);
    });
  }

  function flash(message) {
    if (!askMsg) return;
    askMsg.textContent = message;
    askMsg.hidden = false;
    window.clearTimeout(flash.t);
    flash.t = window.setTimeout(function () { askMsg.hidden = true; }, 4000);
  }

  /* -------------------------------------------------------- floating emoji */

  var layerHost = $('livePlayerStage') || $('livekitPlayer') || root;
  var layer = null;
  function floatEmoji(emoji) {
    if (!layer) {
      layer = node('div', 'lx-float-layer');
      layer.setAttribute('aria-hidden', 'true');
      layerHost.appendChild(layer);
    }
    if (layer.childNodes.length > 30) return;
    var s = node('span', 'lx-float', emoji);
    s.style.left = (8 + Math.random() * 80) + '%';
    s.style.fontSize = (1.4 + Math.random() * 1.2) + 'rem';
    s.style.animationDuration = (2.6 + Math.random() * 1.4) + 's';
    layer.appendChild(s);
    window.setTimeout(function () { if (s.parentNode) s.parentNode.removeChild(s); }, 4200);
  }
  var reducedMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  function burst(items) {
    if (reducedMotion) return;
    items.slice(0, 12).forEach(function (e, i) { window.setTimeout(function () { floatEmoji(e); }, i * 140); });
  }

  var legacy = { like: '👍', love: '❤️', fire: '🔥', clap: '👏' };
  var player = $('livekitPlayer');
  if (player) player.addEventListener('tmz-live-reaction', function (e) { var m = legacy[e.detail && e.detail.reaction]; if (m && !reducedMotion) floatEmoji(m); });
  document.addEventListener('click', function (e) {
    var b = e.target.closest && e.target.closest('[data-reaction]');
    if (b && legacy[b.dataset.reaction] && !reducedMotion) floatEmoji(legacy[b.dataset.reaction]);
  });

  if (reactBar) {
    reactBar.addEventListener('click', function (e) {
      var b = e.target.closest('button[data-emoji]');
      if (!b) return;
      if (!loggedIn) { flash('Sign in to send reactions.'); return; }
      floatEmoji(b.dataset.emoji);
      post('react', { emoji: b.dataset.emoji }).catch(function (err) { flash(err.message); });
    });
  }

  if (askForm) {
    askForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var text = askInput.value.trim();
      if (text.length < 3) { flash('Please write a question (at least 3 characters).'); return; }
      var btn = askForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      post('ask', { question: text })
        .then(function (j) { askInput.value = ''; lastQuestionsKey = ''; renderQuestions(j.questions); flash('Question sent ✓'); })
        .catch(function (err) { flash(err.message); })
        .then(function () { btn.disabled = false; });
    });
  }

  /* ---------------------------------------------------------------- polling */

  function poll_refresh() { window.clearTimeout(timer); tick(); }

  function tick() {
    if (document.hidden) { timer = window.setTimeout(tick, 12000); return; }
    fetch(api + '?action=state&live_id=' + encodeURIComponent(liveId) + '&since=' + since, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('state ' + r.status); return r.json(); })
      .then(function (s) {
        failures = 0;
        if (s.locked) { root.hidden = true; return; }
        renderStage(s.stage, s.viewers, s.peak);
        renderPoll(s.poll);
        renderQuestions(s.questions);
        if (s.reactions) {
          var first = since === 0;
          since = s.reactions.latest || since;
          if (!first) burst(s.reactions.items || []);
        }
        if (s.live && s.live.status === 'ended') { viewersEl.textContent = 'Show ended'; return; }
        timer = window.setTimeout(tick, 4000);
      })
      .catch(function () {
        failures += 1;
        timer = window.setTimeout(tick, Math.min(30000, 4000 * Math.pow(2, failures)));
      });
  }

  document.addEventListener('visibilitychange', function () { if (!document.hidden) poll_refresh(); });
  tick();
})();
