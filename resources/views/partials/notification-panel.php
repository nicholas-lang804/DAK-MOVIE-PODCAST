<?php
require_once __DIR__ . '/../../../app/Middleware/CSRF.php';
$notificationRole = isAdmin() ? 'admin' : (!empty($_SESSION['user_id']) ? 'user' : 'guest');
$notificationRecipient = $notificationRole === 'admin'
    ? (string)(int)($_SESSION['admin_id'] ?? 0)
    : ($notificationRole === 'user'
        ? (string)(int)$_SESSION['user_id']
        : (string)($_SESSION['notification_guest_id'] ?? ''));
if ($notificationRole === 'guest' && $notificationRecipient === '') {
    $_SESSION['notification_guest_id'] = bin2hex(random_bytes(16));
    $notificationRecipient = $_SESSION['notification_guest_id'];
}
?>
<aside class="tmz-notifications" id="tmzNotifications" data-role="<?php echo htmlspecialchars($notificationRole, ENT_QUOTES, 'UTF-8'); ?>" data-recipient="<?php echo htmlspecialchars($notificationRecipient, ENT_QUOTES, 'UTF-8'); ?>" aria-label="Notifications and messages">
    <button class="tmz-notification-toggle" type="button" id="tmzNotificationToggle" aria-expanded="false" aria-controls="tmzNotificationPanel">
        <img class="tmz-notif-avatar" src="/TMZ%20PODCAST/assets/img/dak-mark.png" alt="" aria-hidden="true"><span aria-hidden="true">♢</span> Notifications <b id="tmzNotificationCount" hidden>0</b>
    </button>
    <section class="tmz-notification-panel" id="tmzNotificationPanel" hidden aria-labelledby="tmzNotificationHeading">
        <header class="tmz-notification-header">
            <img class="tmz-notif-avatar tmz-notif-avatar-lg" src="/TMZ%20PODCAST/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA">
            <div><h2 id="tmzNotificationHeading">Notifications</h2><p id="tmzNotificationSummary">Updates and conversations</p></div>
            <button type="button" id="tmzNotificationClose" class="tmz-notification-close" aria-label="Close notifications">×</button>
        </header>
        <div class="tmz-notification-columns">
            <section aria-labelledby="tmzUpdatesHeading">
                <div class="tmz-notification-section-heading"><h3 id="tmzUpdatesHeading">Updates</h3><button type="button" id="tmzClearNotifications">Clear all</button></div>
                <div class="tmz-notification-list" id="tmzNotificationList" aria-live="polite"><p class="tmz-notification-empty">Loading updates…</p></div>
            </section>
            <section class="tmz-inbox" aria-labelledby="tmzInboxHeading">
                <div class="tmz-notification-section-heading"><h3 id="tmzInboxHeading">Messages</h3></div>
                <?php if ($notificationRole === 'guest'): ?>
                    <p class="tmz-notification-empty">Sign in to send or reply to private messages.</p>
                <?php else: ?>
                    <div class="tmz-thread-list" id="tmzThreadList"><p class="tmz-notification-empty">Loading conversations…</p></div>
                    <div class="tmz-thread-view" id="tmzThreadView" hidden>
                        <button type="button" id="tmzThreadBack" class="tmz-thread-back">← All messages</button>
                        <h4 id="tmzThreadTitle"></h4>
                        <div class="tmz-thread-messages" id="tmzThreadMessages" aria-live="polite"></div>
                        <form id="tmzReplyForm" class="tmz-message-form">
                            <label class="sr-only" for="tmzReplyBody">Reply</label>
                            <textarea id="tmzReplyBody" name="body" maxlength="5000" rows="3" required placeholder="Write a reply…"></textarea>
                            <button type="submit" class="btn btn-primary">Reply</button>
                        </form>
                    </div>
                    <?php if ($notificationRole === 'user'): ?>
                        <form id="tmzNewMessageForm" class="tmz-message-form">
                            <label for="tmzNewMessageSubject">Message the team</label>
                            <input id="tmzNewMessageSubject" name="subject" maxlength="180" required placeholder="Subject">
                            <textarea id="tmzNewMessageBody" name="body" maxlength="5000" rows="3" required placeholder="Write your message…"></textarea>
                            <button type="submit" class="btn btn-primary">Send message</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </div>
        <p id="tmzNotificationStatus" class="tmz-notification-status" role="status" aria-live="polite"></p>
        <form id="tmzNotificationCsrf" hidden><?php echo CSRF::getField(); ?></form>
    </section>
    <div id="tmzNotificationToasts" class="tmz-notification-toasts" aria-live="polite" aria-relevant="additions"></div>
</aside>
