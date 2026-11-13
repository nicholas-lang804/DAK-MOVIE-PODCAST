<?php
/**
 * Viewer panel under the live player: viewer count, "now talking about", pinned message,
 * shout-outs, polls, Q&A and emoji reactions. Expects $live. Optional $lxLoggedIn.
 */
require_once __DIR__ . '/../../../app/Models/LiveExtras.php';
$lxLoggedIn = !empty($_SESSION['user_id']);
?>
<section class="live-extras" id="liveExtras" aria-label="Live show extras"
         data-live-id="<?php echo (int)$live['id']; ?>"
         data-csrf="<?php echo htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8'); ?>"
         data-api="/TMZ%20PODCAST/api/live-extras.php"
         data-logged-in="<?php echo $lxLoggedIn ? '1' : '0'; ?>">
  <div class="lx-topline">
    <span class="lx-viewers" id="lxViewers" hidden></span>
    <div class="lx-topic" id="lxTopic" hidden><small>NOW TALKING ABOUT</small><strong></strong></div>
  </div>
  <div class="lx-shoutout" id="lxShoutout" hidden role="status" aria-live="polite"></div>
  <div class="lx-pinned" id="lxPinned" hidden><small>📌 PINNED</small><span></span></div>

  <div class="lx-reactbar" id="lxReactBar" role="group" aria-label="Send a reaction">
    <?php foreach (LiveExtras::REACTIONS as $emoji): ?>
      <button type="button" data-emoji="<?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?>" aria-label="React <?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8'); ?></button>
    <?php endforeach; ?>
  </div>

  <div class="lx-grid">
    <div class="lx-card lx-poll" id="lxPoll" hidden></div>
    <div class="lx-card lx-qa">
      <div class="lx-card-head"><span class="lx-tag">Q&amp;A</span><span class="lx-muted">Upvote the questions you want answered</span></div>
      <?php if ($lxLoggedIn): ?>
        <form id="lxAskForm" class="lx-ask" autocomplete="off">
          <input id="lxAskInput" maxlength="300" placeholder="Ask the host a question…" aria-label="Your question">
          <button class="btn btn-primary" type="submit">Ask</button>
        </form>
      <?php else: ?>
        <p class="lx-muted"><a class="lx-login" href="/TMZ%20PODCAST/login.php">Sign in</a> to ask questions and vote.</p>
      <?php endif; ?>
      <p class="lx-message" id="lxAskMessage" role="status" aria-live="polite" hidden></p>
      <ul class="lx-questions" id="lxQuestions" aria-live="polite"><li class="lx-empty">Loading questions…</li></ul>
    </div>
  </div>
</section>
<link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/live-extras.css?v=lx-1">
