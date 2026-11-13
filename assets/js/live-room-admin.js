(function () {
  const panel = document.getElementById('liveParticipantAdmin');
  if (!panel) return;

  const liveId = panel.dataset.liveId;
  const list = document.getElementById('liveParticipantList');
  const status = document.getElementById('liveParticipantSyncStatus');
  const requestPanel = Array.from(document.querySelectorAll('.live-control-panel h3'))
    .find((heading) => heading.textContent.trim() === 'Speaker requests')?.parentElement;
  const csrf = document.querySelector('#liveStudioCsrf input[name="csrf_token"]')?.value || '';
  let busy = false;
  let lastRoom = null;
  let roomControls = {};

  function setStatus(message, isError) {
    status.textContent = message;
    status.dataset.state = isError ? 'error' : 'ready';
  }

  async function request(url, values) {
    const body = new URLSearchParams(Object.assign({ live_id: liveId, csrf_token: csrf }, values || {}));
    const response = await fetch(url, {
      method: values ? 'POST' : 'GET',
      credentials: 'same-origin',
      headers: values ? { 'Content-Type': 'application/x-www-form-urlencoded' } : {},
      body: values ? body : undefined
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Live-room control request failed.');
    return data;
  }

  function controlLabel(button, enabled) {
    const labels = {
      mute_all: enabled ? 'Unmute all participants' : 'Mute all participants',
      disable_cameras: enabled ? 'Enable all cameras' : 'Disable all cameras',
      room_locked: enabled ? 'Unlock room for new participants' : 'Lock room to new participants'
    };
    button.textContent = labels[button.dataset.roomControl];
    button.setAttribute('aria-pressed', enabled ? 'true' : 'false');
  }

  function permissionToggle(participant, name, label, enabled) {
    const wrapper = document.createElement('label');
    wrapper.className = 'live-participant-permission';
    const input = document.createElement('input');
    input.type = 'checkbox';
    input.checked = Boolean(enabled);
    input.disabled = participant.role === 'host';
    input.addEventListener('change', async function () {
      input.disabled = true;
      try {
        await request('/TMZ%20PODCAST/api/live-room-permissions.php', {
          action: 'permission',
          identity: participant.identity,
          permission: name,
          value: String(input.checked)
        });
        setStatus(label + ' permission updated for ' + participant.name + '.', false);
      } catch (error) {
        input.checked = !input.checked;
        setStatus(error.message, true);
      } finally {
        input.disabled = participant.role === 'host';
      }
    });
    const text = document.createElement('span');
    text.textContent = label;
    wrapper.append(input, text);
    return wrapper;
  }

  function participantCard(participant) {
    const card = document.createElement('article');
    card.className = 'live-participant-card';
    card.dataset.participantIdentity = participant.identity;

    const heading = document.createElement('div');
    heading.className = 'live-participant-card-heading';
    const identity = document.createElement('div');
    const name = document.createElement('strong');
    name.textContent = participant.name || participant.identity;
    const role = document.createElement('span');
    role.textContent = participant.role.replace('_', ' ');
    identity.append(name, role);
    const state = document.createElement('span');
    state.className = 'live-participant-state';
    const tracks = Array.isArray(participant.tracks) ? participant.tracks : [];
    const talking = tracks.some((track) => String(track.source || '').toUpperCase() === 'MICROPHONE' && !track.muted);
    const onCamera = tracks.some((track) => String(track.source || '').toUpperCase() === 'CAMERA' && !track.muted);
    state.textContent = (participant.state || 'connected') + (talking ? ' · 🎤 live' : '') + (onCamera ? ' · 📷 on' : '');
    heading.append(identity, state);
    if (participant.role !== 'host' && !participant.can_publish_microphone) {
      const badge = document.createElement('span');
      badge.className = 'live-participant-muted-badge';
      badge.textContent = 'Muted by host · tap "Allow to talk" to let them speak again';
      identity.appendChild(badge);
    }

    const permissions = document.createElement('div');
    permissions.className = 'live-participant-permissions';
    permissions.append(
      permissionToggle(participant, 'microphone', 'Allow mic', participant.can_publish_microphone),
      permissionToggle(participant, 'camera', 'Allow camera', participant.can_publish_camera),
      permissionToggle(participant, 'screen_share', 'Allow screen share', participant.can_publish_screen_share),
      permissionToggle(participant, 'chat', 'Allow chat', participant.can_chat)
    );

    const actions = document.createElement('div');
    actions.className = 'live-participant-actions';
    const roleSelect = document.createElement('select');
    roleSelect.setAttribute('aria-label', 'Participant role');
    [
      ['participant', 'Participant'],
      ['presenter', 'Presenter'],
      ['co_host', 'Co-host'],
      ['moderator', 'Moderator']
    ].forEach(function (optionData) {
      const option = document.createElement('option');
      option.value = optionData[0];
      option.textContent = optionData[1];
      option.selected = participant.role === option.value;
      roleSelect.appendChild(option);
    });
    roleSelect.disabled = participant.role === 'host';
    roleSelect.addEventListener('change', async function () {
      roleSelect.disabled = true;
      try {
        await request('/TMZ%20PODCAST/api/live-room-permissions.php', {
          action: 'promote',
          identity: participant.identity,
          role: roleSelect.value
        });
        setStatus(participant.name + ' role updated.', false);
      } catch (error) {
        setStatus(error.message, true);
        refresh();
      } finally {
        roleSelect.disabled = participant.role === 'host';
      }
    });

    const micAllowed = Boolean(participant.can_publish_microphone);
    const camAllowed = Boolean(participant.can_publish_camera);
    const mute = document.createElement('button');
    mute.type = 'button';
    mute.className = micAllowed ? 'btn btn-outline' : 'btn btn-primary';
    mute.textContent = micAllowed ? 'Mute' : 'Allow to talk';
    mute.title = micAllowed
      ? 'Mutes them. They cannot talk again until you allow them.'
      : 'They are muted. Allow them to talk again.';
    mute.disabled = participant.role === 'host';
    mute.addEventListener('click', () => changeParticipant(participant, 'permission', {
      permission: 'microphone',
      value: String(!micAllowed)
    }, micAllowed ? 'Muted. They cannot talk until you allow them.' : 'Allowed. They can talk again.'));

    const stopVideo = document.createElement('button');
    stopVideo.type = 'button';
    stopVideo.className = camAllowed ? 'btn btn-outline' : 'btn btn-primary';
    stopVideo.textContent = camAllowed ? 'Stop video' : 'Allow video';
    stopVideo.disabled = participant.role === 'host';
    stopVideo.addEventListener('click', () => changeParticipant(participant, 'permission', {
      permission: 'camera',
      value: String(!camAllowed)
    }, camAllowed ? 'Camera turned off. They cannot use it until you allow them.' : 'Camera allowed.'));

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'btn btn-outline';
    remove.textContent = 'Remove';
    remove.disabled = participant.role === 'host';
    remove.addEventListener('click', () => changeParticipant(participant, 'remove', {}, 'Participant removed.'));

    const ban = document.createElement('button');
    ban.type = 'button';
    ban.className = 'btn btn-danger';
    ban.textContent = 'Ban';
    ban.disabled = participant.role === 'host';
    ban.addEventListener('click', () => changeParticipant(participant, 'ban', {}, 'Participant banned.'));
    actions.append(roleSelect, mute, stopVideo, remove, ban);
    card.append(heading, permissions, actions);
    return card;
  }

  function renderSpeakerRequests(requests) {
    if (!requestPanel) return;
    requestPanel.replaceChildren();
    const heading = document.createElement('h3');
    heading.textContent = 'Speaker requests';
    requestPanel.appendChild(heading);
    if (!requests.length) {
      const empty = document.createElement('p');
      empty.className = 'form-help';
      empty.textContent = 'No pending hand raises.';
      requestPanel.appendChild(empty);
      return;
    }
    requests.forEach((speakerRequest) => {
      const card = document.createElement('article');
      card.className = 'live-speaker-request';
      const name = document.createElement('strong');
      name.textContent = speakerRequest.username || 'Member';
      const type = document.createElement('span');
      type.textContent = speakerRequest.request_type === 'video' ? 'Raised hand · requests camera' : 'Raised hand · requests microphone';
      const invite = document.createElement('button');
      invite.type = 'button';
      invite.className = 'btn btn-primary';
      invite.textContent = speakerRequest.request_type === 'video' ? 'Invite to camera' : 'Invite to speak';
      invite.addEventListener('click', () => handleSpeakerRequest(speakerRequest.id, 'invite', invite));
      const deny = document.createElement('button');
      deny.type = 'button';
      deny.className = 'btn btn-outline';
      deny.textContent = 'Deny';
      deny.addEventListener('click', () => handleSpeakerRequest(speakerRequest.id, 'deny', deny));
      const actions = document.createElement('div');
      actions.className = 'live-participant-actions';
      actions.append(invite, deny);
      card.append(name, type, actions);
      requestPanel.appendChild(card);
    });
  }

  async function handleSpeakerRequest(requestId, action, button) {
    button.disabled = true;
    try {
      const data = await request('/TMZ%20PODCAST/api/live-room-invitation.php', {
        live_id: liveId,
        request_id: String(requestId),
        action
      });
      setStatus(action === 'invite' ? 'Invitation sent. The member must accept before their mic or camera is enabled.' : 'Speaker request denied.', false);
      await refresh();
    } catch (error) {
      setStatus(error.message, true);
      button.disabled = false;
    }
  }

  async function changeParticipant(participant, action, extra, successMessage) {
    try {
      const result = await request('/TMZ%20PODCAST/api/live-room-permissions.php', Object.assign({
        action,
        identity: participant.identity
      }, extra));
      setStatus(successMessage + ' (' + participant.name + ')', false);
      if (result.participant?.role === 'host') return;
      await refresh();
    } catch (error) {
      setStatus(error.message, true);
    }
  }

  async function refresh() {
    if (busy) return;
    const room = window.tmzLiveRoom;
    if (!room || room.state !== 'connected') {
      if (!room) setStatus('Waiting for host connection', false);
      return;
    }
    busy = true;
    try {
      const data = await request('/TMZ%20PODCAST/api/live-room-permissions.php?live_id=' + encodeURIComponent(liveId));
      roomControls = data.controls || {};
      panel.querySelectorAll('[data-room-control]').forEach(function (button) {
        controlLabel(button, Boolean(roomControls[button.dataset.roomControl]));
      });
      list.replaceChildren();
      if (!data.participants.length) {
        const empty = document.createElement('p');
        empty.className = 'form-help';
        empty.textContent = 'No participants are connected yet.';
        list.appendChild(empty);
      } else {
        data.participants.forEach(function (participant) {
          list.appendChild(participantCard(participant));
        });
      }
      renderSpeakerRequests(data.speaker_requests || []);
      setStatus(data.participants.length + ' participant(s) · permissions synced', false);
    } catch (error) {
      setStatus(error.message, true);
    } finally {
      busy = false;
    }
  }

  panel.querySelectorAll('[data-room-control]').forEach(function (button) {
    button.addEventListener('click', async function () {
      const control = button.dataset.roomControl;
      const value = !Boolean(roomControls[control]);
      button.disabled = true;
      try {
        const data = await request('/TMZ%20PODCAST/api/live-room-permissions.php', {
          action: 'room_control',
          control,
          value: String(value)
        });
        roomControls = data.controls || roomControls;
        controlLabel(button, value);
        await refresh();
      } catch (error) {
        setStatus(error.message, true);
      } finally {
        button.disabled = false;
      }
    });
  });

  panel.querySelector('[data-room-action="end_for_all"]')?.addEventListener('click', async function (event) {
    if (!window.confirm('End this broadcast for everyone?')) return;
    event.currentTarget.disabled = true;
    try {
      await request('/TMZ%20PODCAST/api/live-room-permissions.php', { action: 'end_for_all' });
      window.tmzLiveRoom?.disconnect();
      setStatus('Broadcast ended for everyone.', false);
      window.location.reload();
    } catch (error) {
      setStatus(error.message, true);
      event.currentTarget.disabled = false;
    }
  });

  document.querySelectorAll('.live-control-panel form').forEach((form) => {
    form.addEventListener('submit', async function (event) {
      const actionInput = form.querySelector('input[name="live_action"]');
      if (actionInput?.value !== 'speaker') return;
      event.preventDefault();
      const submitter = event.submitter || form.querySelector('button[name="speaker_status"]');
      const requestId = form.querySelector('input[name="request_id"]')?.value;
      const action = submitter?.value === 'denied' ? 'deny' : 'invite';
      if (!requestId) {
        setStatus('This speaker request is missing its ID.', true);
        return;
      }
      if (submitter) submitter.disabled = true;
      try {
        await request('/TMZ%20PODCAST/api/live-room-invitation.php', {
          live_id: liveId,
          request_id: requestId,
          action
        });
        form.closest('.live-status')?.remove();
        await refresh();
      } catch (error) {
        setStatus(error.message, true);
        if (submitter) submitter.disabled = false;
      }
    });
  });

  const chatForm = document.getElementById('adminChatForm');
  chatForm?.addEventListener('submit', async function (event) {
    event.preventDefault();
    const input = document.getElementById('adminChatInput');
    const message = input.value.trim();
    const room = window.tmzLiveRoom;
    if (!message || !room) return;
    const button = chatForm.querySelector('button');
    button.disabled = true;
    try {
      await room.localParticipant.publishData(
        new TextEncoder().encode(JSON.stringify({ type: 'chat', message })),
        { reliable: true, topic: 'chat' }
      );
      const body = new URLSearchParams({
        live_action: 'comment_reply',
        comment: message,
        parent_id: '',
        csrf_token: csrf
      });
      const response = await fetch('/TMZ%20PODCAST/admin/dashboard.php?tab=live', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body
      });
      if (!response.ok) throw new Error('The reply could not be saved.');
      input.value = '';
      setStatus('Reply sent to the live room.', false);
    } catch (error) {
      setStatus(error.message || 'The reply could not be sent.', true);
    } finally {
      button.disabled = false;
    }
  });

  window.setInterval(refresh, 4000);
  window.addEventListener('tmz-live-data', refresh);
  window.addEventListener('tmz-live-started', refresh);
})();
