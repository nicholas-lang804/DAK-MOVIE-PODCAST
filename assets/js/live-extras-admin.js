/* Live Extras (admin): schedule, stage banner, polls, Q&A moderation, attendance report. */
(function () {
  'use strict';
  var root = document.getElementById('liveExtrasAdmin');
  if (!root) return;
  if (root.dataset.lxInit === '1') return; // never initialise twice
  root.dataset.lxInit = '1';

  var api = root.dataset.api, liveId = root.dataset.liveId, csrf = root.dataset.csrf;
  var $ = function (id) { return document.getElementById(id); };
  var statusEl = $('lxaStatus');
  var timer = null;
  var typing = false; // don't overwrite fields while the admin is editing them

  function node(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined && text !== null) n.textContent = text;
    return n;
  }
  function say(msg, bad) {
    statusEl.hidden = false;
    statusEl.textContent = msg;
    statusEl.className = 'lxa-status' + (bad ? ' is-error' : ' is-ok');
    window.clearTimeout(say.t);
    say.t = window.setTimeout(function () { statusEl.hidden = true; }, 4500);
  }
  function post(action, data) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('live_id', liveId);
    body.set('csrf_token', csrf);
    Object.keys(data || {}).forEach(function (k) {
      if (Array.isArray(data[k])) data[k].forEach(function (v) { body.append(k + '[]', v); });
      else body.set(k, data[k]);
    });
    return fetch(api, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        return r.json().catch(function () { return {}; }).then(function (j) {
          if (!r.ok) throw new Error(j.error || 'Request failed.');
          return j;
        });
      });
  }
  function get(action) {
    return fetch(api + '?action=' + action + '&live_id=' + encodeURIComponent(liveId), { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.error || 'Request failed.'); return j; }); });
  }

  /* datetime-local <-> ISO helpers (the browser's own time zone) */
  function toLocalInput(iso) {
    var d = new Date(iso);
    if (isNaN(d)) return '';
    var p = function (n) { return (n < 10 ? '0' : '') + n; };
    return d.getFullYear() + '-' + p(d.getMonth() + 1) + '-' + p(d.getDate()) + 'T' + p(d.getHours()) + ':' + p(d.getMinutes());
  }

  function renderSchedule(iso) {
    var input = $('lxaScheduleInput'), note = $('lxaScheduleNote');
    if (!typing || document.activeElement !== input) input.value = iso ? toLocalInput(iso) : '';
    note.textContent = iso ? 'Scheduled for ' + new Date(iso).toLocaleString() + ' (your time).' : 'No start time set.';
  }

  function renderStage(stage) {
    if (typing) return;
    $('lxaTopic').value = stage.topic || '';
    $('lxaPinned').value = stage.pinned_message || '';
    $('lxaShoutout').value = stage.shoutout || '';
  }

  function renderPolls(polls) {
    var box = $('lxaPolls');
    box.textContent = '';
    (polls || []).forEach(function (p) {
      var card = node('div', 'lxa-poll' + (p.status === 'open' ? ' is-open' : ''));
      var head = node('div', 'lxa-poll-head');
      head.appendChild(node('strong', null, p.question));
      head.appendChild(node('span', 'lxa-pill', p.status === 'open' ? 'OPEN' : 'CLOSED'));
      card.appendChild(head);
      p.options.forEach(function (o) {
        var pct = p.total ? Math.round(o.votes * 100 / p.total) : 0;
        var row = node('div', 'lxa-bar-row');
        var bar = node('i', 'lxa-bar'); bar.style.width = pct + '%';
        row.appendChild(bar);
        row.appendChild(node('span', null, o.label));
        row.appendChild(node('b', null, o.votes + ' · ' + pct + '%'));
        card.appendChild(row);
      });
      var foot = node('div', 'lxa-row');
      foot.appendChild(node('small', 'form-help', p.total + ' vote' + (p.total === 1 ? '' : 's')));
      if (p.status === 'open') {
        var close = node('button', 'btn btn-outline btn-small', 'Close poll');
        close.type = 'button';
        close.addEventListener('click', function () {
          post('poll_close', { poll_id: p.id }).then(function (j) { renderPolls(j.polls); say('Poll closed.'); }).catch(function (e) { say(e.message, true); });
        });
        foot.appendChild(close);
      }
      card.appendChild(foot);
      box.appendChild(card);
    });
    if (!polls || !polls.length) box.appendChild(node('p', 'form-help', 'No polls yet.'));
  }

  function renderQuestions(list) {
    var ul = $('lxaQuestions');
    ul.textContent = '';
    if (!list || !list.length) { ul.appendChild(node('li', 'form-help', 'No questions yet.')); return; }
    list.forEach(function (q) {
      var li = node('li', 'lxa-q status-' + q.status);
      var text = node('div', 'lxa-q-text');
      text.appendChild(node('p', null, q.text));
      text.appendChild(node('small', 'form-help', q.author + ' · ▲ ' + q.votes + ' · ' + q.status));
      li.appendChild(text);
      var actions = node('div', 'lxa-q-actions');
      [['answered', 'Answered'], ['open', 'Reopen'], ['hidden', 'Hide']].forEach(function (pair) {
        if (q.status === pair[0]) return;
        var b = node('button', 'btn btn-outline btn-small', pair[1]);
        b.type = 'button';
        b.addEventListener('click', function () {
          post('question_status', { question_id: q.id, status: pair[0] }).then(function (j) { renderQuestions(j.questions); }).catch(function (e) { say(e.message, true); });
        });
        actions.appendChild(b);
      });
      li.appendChild(actions);
      ul.appendChild(li);
    });
  }

  function loadReport() {
    get('report').then(function (r) {
      var box = $('lxaReport');
      box.textContent = '';
      [['Unique viewers', r.unique_viewers], ['Members', r.members], ['Guests', r.guests], ['Peak at once', r.peak],
       ['Avg. minutes watched', r.average_minutes], ['Chat messages', r.engagement.chat_messages], ['Questions', r.engagement.questions],
       ['Poll votes', r.engagement.poll_votes], ['Speaker requests', r.engagement.speaker_requests], ['Reminders set', r.engagement.reminders]
      ].forEach(function (s) {
        var c = node('div', 'lxa-stat');
        c.appendChild(node('b', null, String(s[1])));
        c.appendChild(node('span', null, s[0]));
        box.appendChild(c);
      });
    }).catch(function (e) { $('lxaReport').textContent = e.message; });
  }

  function refresh() {
    if (document.hidden) { timer = window.setTimeout(refresh, 10000); return; }
    get('admin_state').then(function (s) {
      $('lxaViewers').textContent = s.viewers === null || s.viewers === undefined ? '0' : s.viewers;
      $('lxaPeak').textContent = s.peak || 0;
      $('lxaReminderCount').textContent = s.engagement ? s.engagement.reminders + ' reminder' + (s.engagement.reminders === 1 ? '' : 's') + ' set' : '';
      renderSchedule(s.live.scheduled_at);
      renderStage(s.stage || {});
      renderPolls(s.polls);
      renderQuestions(s.questions);
      timer = window.setTimeout(refresh, 5000);
    }).catch(function () { timer = window.setTimeout(refresh, 15000); });
  }

  root.addEventListener('focusin', function (e) { if (e.target.matches('input, select, textarea')) typing = true; });
  root.addEventListener('focusout', function () { window.setTimeout(function () { typing = root.contains(document.activeElement) && document.activeElement.matches('input, select, textarea'); }, 50); });

  $('lxaScheduleForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var v = $('lxaScheduleInput').value;
    if (!v) { say('Pick a date and time first, or use Clear.', true); return; }
    var d = new Date(v);
    if (isNaN(d)) { say('That date is not valid.', true); return; }
    post('set_schedule', { scheduled_at: d.toISOString() }).then(function (j) { typing = false; renderSchedule(j.scheduled_at); say('Schedule saved.'); }).catch(function (err) { say(err.message, true); });
  });
  $('lxaScheduleClear').addEventListener('click', function () {
    post('set_schedule', { scheduled_at: '' }).then(function () { typing = false; renderSchedule(null); say('Schedule cleared.'); }).catch(function (err) { say(err.message, true); });
  });
  $('lxaSendReminders').addEventListener('click', function () {
    if (!window.confirm('Send a "starting soon" notification to everyone who asked for a reminder?')) return;
    post('send_reminders').then(function (j) { say(j.sent + ' reminder' + (j.sent === 1 ? '' : 's') + ' sent.'); }).catch(function (err) { say(err.message, true); });
  });

  $('lxaStageForm').addEventListener('submit', function (e) {
    e.preventDefault();
    post('set_stage', { topic: $('lxaTopic').value, pinned: $('lxaPinned').value, shoutout: $('lxaShoutout').value, shoutout_minutes: $('lxaShoutMinutes').value })
      .then(function () { typing = false; say('Screen updated.'); }).catch(function (err) { say(err.message, true); });
  });
  $('lxaStageClear').addEventListener('click', function () {
    post('set_stage', { topic: '', pinned: '', shoutout: '' }).then(function () { typing = false; ['lxaTopic', 'lxaPinned', 'lxaShoutout'].forEach(function (id) { $(id).value = ''; }); say('Screen cleared.'); }).catch(function (err) { say(err.message, true); });
  });

  $('lxaPollForm').addEventListener('submit', function (e) {
    e.preventDefault();
    var options = Array.prototype.map.call(document.querySelectorAll('.lxa-poll-option'), function (i) { return i.value.trim(); }).filter(Boolean);
    post('poll_create', { question: $('lxaPollQuestion').value, options: options })
      .then(function (j) { renderPolls(j.polls); $('lxaPollForm').reset(); typing = false; say('Poll is live for viewers.'); })
      .catch(function (err) { say(err.message, true); });
  });

  $('lxaRefreshReport').addEventListener('click', loadReport);
  loadReport();
  window.setInterval(function () { if (!document.hidden) loadReport(); }, 30000);
  refresh();
})();
