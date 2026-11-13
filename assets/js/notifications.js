(function () {
  'use strict';

  const root = document.getElementById('tmzNotifications');
  if (!root) return;

  const api = '/api/notifications.php';
  const toggle = document.getElementById('tmzNotificationToggle');
  const panel = document.getElementById('tmzNotificationPanel');
  const closeButton = document.getElementById('tmzNotificationClose');
  const count = document.getElementById('tmzNotificationCount');
  const notificationList = document.getElementById('tmzNotificationList');
  const threadList = document.getElementById('tmzThreadList');
  const threadView = document.getElementById('tmzThreadView');
  const threadMessages = document.getElementById('tmzThreadMessages');
  const threadTitle = document.getElementById('tmzThreadTitle');
  const status = document.getElementById('tmzNotificationStatus');
  const toastRegion = document.getElementById('tmzNotificationToasts');
  const csrf = document.querySelector('#tmzNotificationCsrf input[name="csrf_token"]')?.value || '';
  let currentThreadId = null;
  let initialLoadComplete = false;
  const knownIds = new Set();
  let requestInProgress = false;

  function showStatus(message) {
    if (status) status.textContent = message || '';
  }

  const LOGO_URL = '/assets/img/dak-mark.png';

  function brandAvatar(className) {
    const img = document.createElement('img');
    img.className = className || 'tmz-avatar';
    img.src = LOGO_URL;
    img.alt = '';
    img.setAttribute('aria-hidden', 'true');
    return img;
  }

  function senderAvatar(role, name) {
    if (role === 'admin') return brandAvatar();
    const span = document.createElement('span');
    span.className = 'tmz-avatar tmz-avatar-initial';
    span.textContent = (name || 'M').trim().charAt(0).toUpperCase();
    span.setAttribute('aria-hidden', 'true');
    return span;
  }

  function toast(title, body) {
    const item = document.createElement('article');
    item.className = 'tmz-notification-toast';
    const heading = document.createElement('strong');
    heading.textContent = title;
    const message = document.createElement('p');
    message.textContent = body;
    const row = document.createElement('div');
    row.className = 'tmz-toast-row';
    row.append(brandAvatar('tmz-avatar tmz-avatar-sm'), heading);
    item.append(row, message);
    toastRegion.appendChild(item);
    window.setTimeout(() => item.remove(), 6500);
  }

  function setOpen(open) {
    panel.hidden = !open;
    toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    if (open) refresh().catch(error => showStatus(error.message));
  }

  async function getJson(url) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' }
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'Could not load notifications.');
    return data;
  }

  async function postAction(fields) {
    if (!csrf) throw new Error('Refresh the page to renew the security token.');
    const body = new URLSearchParams({ ...fields, csrf_token: csrf });
    const response = await fetch(api, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
      },
      body
    });
    const data = await response.json();
    if (!response.ok) throw new Error(data.error || 'The action could not be completed.');
    return data;
  }

  function renderNotifications(notifications) {
    notificationList.replaceChildren();
    if (!notifications.length) {
      const empty = document.createElement('p');
      empty.className = 'tmz-notification-empty';
      empty.textContent = 'You’re all caught up.';
      notificationList.appendChild(empty);
      return;
    }

    notifications.forEach(notification => {
      const item = document.createElement('article');
      item.className = 'tmz-notification-item tmz-notification-item-row' + (notification.read_at ? '' : ' is-unread');
      const avatar = brandAvatar();
      const content = document.createElement('div');
      content.className = 'tmz-notification-content';
      const title = document.createElement('strong');
      title.textContent = notification.title;
      const body = document.createElement('p');
      body.textContent = notification.body;
      const time = document.createElement('time');
      time.dateTime = notification.created_at;
      const date = new Date(String(notification.created_at).replace(' ', 'T'));
      time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
      const actions = document.createElement('div');
      actions.className = 'tmz-notification-actions';
      if (notification.thread_id) {
        const reply = document.createElement('button');
        reply.type = 'button';
        reply.textContent = 'Open and reply';
        reply.addEventListener('click', () => openThread(notification.thread_id));
        actions.appendChild(reply);
      } else if (notification.target_url) {
        const link = document.createElement('a');
        link.textContent = 'View';
        try {
          const target = new URL(notification.target_url, window.location.origin);
          if (target.origin === window.location.origin) {
            link.href = target.pathname + target.search + target.hash;
            link.addEventListener('click', async event => {
              event.preventDefault();
              try {
                await postAction({ action: 'read', notification_id: String(notification.id) });
                window.location.assign(link.href);
              } catch (error) {
                showStatus(error.message);
              }
            });
            actions.appendChild(link);
          }
        } catch (error) {
          console.warn('Ignoring an invalid notification link.');
        }
      }
      if (!notification.read_at) {
        const markRead = document.createElement('button');
        markRead.type = 'button';
        markRead.textContent = 'Mark read';
        markRead.addEventListener('click', async () => {
          try {
            await postAction({ action: 'read', notification_id: String(notification.id) });
            await refresh();
          } catch (error) {
            showStatus(error.message);
          }
        });
        actions.appendChild(markRead);
      }
      const clear = document.createElement('button');
      clear.type = 'button';
      clear.textContent = 'Clear';
      clear.addEventListener('click', async () => {
        try {
          await postAction({ action: 'clear', notification_id: String(notification.id) });
          await refresh();
        } catch (error) {
          showStatus(error.message);
        }
      });
      actions.appendChild(clear);
      content.append(title, body, time, actions);
      item.append(avatar, content);
      notificationList.appendChild(item);
    });
  }

  function renderThreads(threads) {
    if (!threadList) return;
    threadList.replaceChildren();
    if (!threads.length) {
      const empty = document.createElement('p');
      empty.className = 'tmz-notification-empty';
      empty.textContent = 'No conversations yet.';
      threadList.appendChild(empty);
      return;
    }
    threads.forEach(thread => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'tmz-thread-item';
      const title = document.createElement('strong');
      title.textContent = thread.subject;
      const detail = document.createElement('span');
      const preview = String(thread.last_body || '').replace(/\s+/g, ' ');
      detail.textContent = (thread.username ? thread.username + ' · ' : '') + preview.slice(0, 100);
      button.append(title, detail);
      const unread = Number(thread.unread_count) || 0;
      if (unread) {
        const badge = document.createElement('b');
        badge.textContent = String(unread);
        button.appendChild(badge);
      }
      button.addEventListener('click', () => openThread(thread.id));
      threadList.appendChild(button);
    });
  }

  async function openThread(threadId) {
    if (!threadView || !threadList) return;
    try {
      const data = await getJson(api + '?action=thread&thread_id=' + encodeURIComponent(threadId));
      currentThreadId = Number(data.thread.id);
      threadTitle.textContent = data.thread.subject + (data.thread.username ? ' — ' + data.thread.username : '');
      threadMessages.replaceChildren();
      data.messages.forEach(message => {
        const item = document.createElement('article');
        item.className = 'tmz-chat-message tmz-chat-message-row' + (message.sender_role === root.dataset.role ? ' is-own' : '');
        const author = document.createElement('strong');
        author.textContent = message.sender_role === 'admin' ? 'THE DAK FILM PODCAST MEDIA team' : (data.thread.username || 'Member');
        const body = document.createElement('p');
        body.textContent = message.body;
        const time = document.createElement('time');
        time.dateTime = message.created_at;
        const date = new Date(String(message.created_at).replace(' ', 'T'));
        time.textContent = Number.isNaN(date.getTime()) ? '' : date.toLocaleString();
        const bubble = document.createElement('div');
        bubble.className = 'tmz-chat-bubble';
        bubble.append(author, body, time);
        item.append(senderAvatar(message.sender_role, data.thread.username || 'Member'), bubble);
        threadMessages.appendChild(item);
      });
      threadList.hidden = true;
      threadView.hidden = false;
      const newMessageForm = document.getElementById('tmzNewMessageForm');
      if (newMessageForm) newMessageForm.hidden = true;
      showStatus('');
      await refresh();
    } catch (error) {
      showStatus(error.message);
    }
  }

  async function refresh() {
    if (requestInProgress) return;
    requestInProgress = true;
    try {
      const data = await getJson(api);
      const notifications = Array.isArray(data.notifications) ? data.notifications : [];
      if (initialLoadComplete) {
        notifications.forEach(notification => {
          const id = String(notification.id);
          if (!knownIds.has(id) && !notification.read_at) toast(notification.title, notification.body);
        });
      }
      notifications.forEach(notification => knownIds.add(String(notification.id)));
      renderNotifications(notifications);
      renderThreads(Array.isArray(data.threads) ? data.threads : []);
      const unreadNonMessageNotifications = notifications.filter(notification => !notification.read_at && notification.category !== 'message').length;
      const unreadMessageNotifications = notifications.filter(notification => !notification.read_at && notification.category === 'message').length;
      const totalUnread = unreadNonMessageNotifications + Math.max(unreadMessageNotifications, Number(data.unread_messages) || 0);
      count.textContent = totalUnread > 99 ? '99+' : String(totalUnread);
      count.hidden = totalUnread === 0;
      document.getElementById('tmzNotificationSummary').textContent =
        totalUnread ? totalUnread + ' unread update' + (totalUnread === 1 ? '' : 's and messages') : 'Updates and conversations';
      initialLoadComplete = true;
      if (currentThreadId && !threadView.hidden) await reloadCurrentThread();
    } finally {
      requestInProgress = false;
    }
  }

  async function reloadCurrentThread() {
    if (!currentThreadId || !threadView || threadView.hidden) return;
    const data = await getJson(api + '?action=thread&thread_id=' + encodeURIComponent(currentThreadId));
    threadMessages.replaceChildren();
    data.messages.forEach(message => {
      const item = document.createElement('article');
      item.className = 'tmz-chat-message tmz-chat-message-row' + (message.sender_role === root.dataset.role ? ' is-own' : '');
      const author = document.createElement('strong');
      author.textContent = message.sender_role === 'admin' ? 'THE DAK FILM PODCAST MEDIA team' : (data.thread.username || 'Member');
      const body = document.createElement('p');
      body.textContent = message.body;
      const time = document.createElement('time');
      time.textContent = new Date(String(message.created_at).replace(' ', 'T')).toLocaleString();
      const bubble = document.createElement('div');
      bubble.className = 'tmz-chat-bubble';
      bubble.append(author, body, time);
      item.append(senderAvatar(message.sender_role, data.thread.username || 'Member'), bubble);
      threadMessages.appendChild(item);
    });
  }

  toggle.addEventListener('click', () => setOpen(panel.hidden));
  closeButton.addEventListener('click', () => setOpen(false));
  document.getElementById('tmzClearNotifications').addEventListener('click', async () => {
    try {
      await postAction({ action: 'clear_all' });
      await refresh();
      showStatus('Notifications cleared.');
    } catch (error) {
      showStatus(error.message);
    }
  });

  document.getElementById('tmzThreadBack')?.addEventListener('click', () => {
    currentThreadId = null;
    threadView.hidden = true;
    threadList.hidden = false;
    const newMessageForm = document.getElementById('tmzNewMessageForm');
    if (newMessageForm) newMessageForm.hidden = false;
    refresh().catch(error => showStatus(error.message));
  });

  document.getElementById('tmzReplyForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const input = document.getElementById('tmzReplyBody');
    try {
      await postAction({ action: 'reply', thread_id: String(currentThreadId), body: input.value });
      input.value = '';
      await reloadCurrentThread();
      await refresh();
      showStatus('Reply sent.');
    } catch (error) {
      showStatus(error.message);
    }
  });

  document.getElementById('tmzNewMessageForm')?.addEventListener('submit', async event => {
    event.preventDefault();
    const form = event.currentTarget;
    const fields = new FormData(form);
    try {
      const data = await postAction({
        action: 'send_message',
        subject: String(fields.get('subject') || ''),
        body: String(fields.get('body') || '')
      });
      form.reset();
      showStatus('Message sent.');
      await refresh();
      await openThread(data.thread_id);
    } catch (error) {
      showStatus(error.message);
    }
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && !panel.hidden) setOpen(false);
  });

  refresh().catch(error => showStatus(error.message));
  window.setInterval(() => refresh().catch(error => showStatus(error.message)), 12000);
})();
