<?php
/**
 * Admin "Show tools" panel: schedule, stage banner, polls, Q&A moderation, audience & attendance.
 * Expects $live (current show row). Included from admin/live.php.
 */
if (empty($live) || empty($live['id'])) return;
$lxCsrf = htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8');
?>
<section class="admin-section live-extras-admin" id="liveExtrasAdmin"
         data-live-id="<?php echo (int)$live['id']; ?>" data-csrf="<?php echo $lxCsrf; ?>"
         data-api="/TMZ%20PODCAST/api/live-extras.php">
  <div class="lxa-heading">
    <div><span class="eyebrow">SHOW TOOLS</span><h2>Audience engagement</h2>
      <p>Schedule the show, steer the conversation, run polls and answer viewer questions. Viewers see changes within a few seconds.</p></div>
    <div class="lxa-live-stats" aria-live="polite">
      <span><b id="lxaViewers">0</b> watching</span><span><b id="lxaPeak">0</b> peak</span>
    </div>
  </div>
  <p id="lxaStatus" class="lxa-status" role="status" aria-live="polite" hidden></p>

  <div class="lxa-grid">
    <div class="lxa-card">
      <h3>Schedule &amp; countdown</h3>
      <p class="form-help">Shown to visitors with a live countdown, a calendar link and a Remind me button. Times use your own time zone.</p>
      <form id="lxaScheduleForm" class="lxa-form">
        <label>Start date and time <input type="datetime-local" id="lxaScheduleInput"></label>
        <div class="lxa-row"><button class="btn btn-primary" type="submit">Save schedule</button><button class="btn btn-outline" type="button" id="lxaScheduleClear">Clear</button></div>
      </form>
      <p class="form-help" id="lxaScheduleNote"></p>
      <div class="lxa-row"><button type="button" class="btn btn-outline" id="lxaSendReminders">Send reminders now</button><span class="form-help" id="lxaReminderCount"></span></div>
    </div>

    <div class="lxa-card">
      <h3>On screen: topic, pin &amp; shout-out</h3>
      <form id="lxaStageForm" class="lxa-form">
        <label>Now talking about <input id="lxaTopic" maxlength="255" placeholder="e.g. Best African films of 2026"></label>
        <label>Pinned message <input id="lxaPinned" maxlength="500" placeholder="e.g. Poll is open. Vote now!"></label>
        <label>Shout-out <input id="lxaShoutout" maxlength="255" placeholder="e.g. Welcome to Amina, our member of the week!"></label>
        <label>Shout-out stays for
          <select id="lxaShoutMinutes"><option value="1">1 minute</option><option value="3">3 minutes</option><option value="10">10 minutes</option></select></label>
        <div class="lxa-row"><button class="btn btn-primary" type="submit">Update screen</button><button class="btn btn-outline" type="button" id="lxaStageClear">Clear all</button></div>
      </form>
    </div>

    <div class="lxa-card">
      <h3>Live poll</h3>
      <form id="lxaPollForm" class="lxa-form">
        <label>Question <input id="lxaPollQuestion" maxlength="300" required placeholder="Which movie should we review next?"></label>
        <?php for ($i = 1; $i <= 6; $i++): ?>
          <label class="lxa-option">Option <?php echo $i; ?><?php echo $i > 2 ? ' (optional)' : ''; ?> <input class="lxa-poll-option" maxlength="160" <?php echo $i <= 2 ? 'required' : ''; ?>></label>
        <?php endfor; ?>
        <button class="btn btn-primary" type="submit">Launch poll</button>
        <p class="form-help">Launching a new poll closes the one that is open.</p>
      </form>
      <div id="lxaPolls" class="lxa-polls"></div>
    </div>

    <div class="lxa-card lxa-card-wide">
      <h3>Viewer questions</h3>
      <ul id="lxaQuestions" class="lxa-questions"><li class="form-help">No questions yet.</li></ul>
    </div>

    <div class="lxa-card lxa-card-wide">
      <h3>Attendance &amp; engagement</h3>
      <div id="lxaReport" class="lxa-report"><p class="form-help">Loading…</p></div>
      <div class="lxa-row">
        <button type="button" class="btn btn-outline" id="lxaRefreshReport">Refresh</button>
        <a class="btn btn-outline" id="lxaCsv" href="/TMZ%20PODCAST/api/live-extras.php?action=report_csv&amp;live_id=<?php echo (int)$live['id']; ?>">Download attendance (CSV)</a>
      </div>
    </div>
  </div>
</section>
<link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/live-extras.css?v=lx-1">
<script src="/TMZ%20PODCAST/assets/js/live-extras-admin.js?v=lx-1"></script>
