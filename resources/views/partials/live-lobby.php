<?php
/**
 * Live lobby: "Coming up" (countdown, Remind me, calendar) and "Past shows" replay shelf.
 * Works with or without a current $live; skips the show already on screen.
 */
require_once __DIR__ . '/../../../app/Models/LiveExtras.php';
try {
    $lxModel = new LiveExtras();
    $lxCurrentId = !empty($live['id']) ? (int)$live['id'] : 0;
    $lxUpcoming = array_values(array_filter($lxModel->upcoming(8), function ($s) use ($lxCurrentId) { return (int)$s['id'] !== $lxCurrentId; }));
    $lxPast = array_values(array_filter($lxModel->past(9), function ($s) use ($lxCurrentId) { return (int)$s['id'] !== $lxCurrentId; }));
} catch (Throwable $e) {
    error_log('Live lobby: ' . $e->getMessage());
    return;
}
$lxLogged = !empty($_SESSION['user_id']);
$lxToken = htmlspecialchars(CSRF::generateToken(), ENT_QUOTES, 'UTF-8');
$lxNow = gmdate('Y-m-d\TH:i:s\Z');
$lxH = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
if (empty($lxUpcoming) && empty($lxPast)) return;
?>
<section class="live-lobby" aria-label="More live shows">
  <?php if (!empty($lxUpcoming)): ?>
    <div class="cine-heading"><div><span class="cine-eyebrow">Schedule</span><h2>Coming up</h2></div></div>
    <div class="lobby-grid">
      <?php foreach ($lxUpcoming as $s): $iso = LiveExtras::toIsoUtc($s['scheduled_at']); ?>
        <article class="lobby-card">
          <?php if (!empty($s['cover_image_path'])): ?>
            <img class="lobby-cover" loading="lazy" src="/<?php echo $lxH(ltrim($s['cover_image_path'], '/')); ?>" alt="">
          <?php endif; ?>
          <div class="lobby-body">
            <span class="lobby-badge"><?php echo $s['access_type'] === 'paid' ? '🔒 Paid' : 'Free'; ?></span>
            <h3><a href="/live.php?live_id=<?php echo (int)$s['id']; ?>"><?php echo $lxH($s['title']); ?></a></h3>
            <?php if ($iso): ?>
              <p class="lobby-when"><time data-lx-localtime="<?php echo $lxH($iso); ?>" datetime="<?php echo $lxH($iso); ?>"><?php echo $lxH($iso); ?></time></p>
              <p class="lobby-countdown" data-lx-countdown="<?php echo $lxH($iso); ?>" data-now="<?php echo $lxNow; ?>">--:--:--</p>
            <?php else: ?>
              <p class="lobby-when">Date to be announced</p>
            <?php endif; ?>
            <div class="lobby-actions">
              <button type="button" class="btn btn-outline btn-small" data-lx-remind="<?php echo (int)$s['id']; ?>" data-csrf="<?php echo $lxToken; ?>" data-logged-in="<?php echo $lxLogged ? '1' : '0'; ?>">🔔 Remind me</button>
              <?php if ($iso): ?><a class="btn btn-outline btn-small" href="/api/live-calendar.php?live_id=<?php echo (int)$s['id']; ?>">📅 Add to calendar</a><?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!empty($lxPast)): ?>
    <div class="cine-heading"><div><span class="cine-eyebrow">Replays</span><h2>Past shows</h2></div></div>
    <div class="lobby-grid">
      <?php foreach ($lxPast as $s): ?>
        <a class="lobby-card lobby-card-link" href="/live.php?live_id=<?php echo (int)$s['id']; ?>">
          <?php if (!empty($s['cover_image_path'])): ?>
            <img class="lobby-cover" loading="lazy" src="/<?php echo $lxH(ltrim($s['cover_image_path'], '/')); ?>" alt="">
          <?php endif; ?>
          <div class="lobby-body">
            <span class="lobby-badge"><?php echo $s['access_type'] === 'paid' ? '🔒 Paid' : 'Free'; ?></span>
            <h3><?php echo $lxH($s['title']); ?></h3>
            <p class="lobby-when"><?php echo $s['ended_at'] ? $lxH(date('M j, Y', strtotime($s['ended_at']))) : ''; ?><?php echo (int)$s['viewer_peak'] > 0 ? ' · peak ' . (int)$s['viewer_peak'] . ' viewers' : ''; ?></p>
            <span class="lobby-cta">Recordings &amp; transcript →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<link rel="stylesheet" href="/assets/css/live-extras.css?v=lx-1">
<script src="/assets/js/live-countdown.js?v=lx-1" defer></script>
