<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/app/Middleware/Auth.php';
require_once __DIR__ . '/app/Middleware/CSRF.php';
require_once __DIR__ . '/app/Models/Gift.php';
require_once __DIR__ . '/app/Models/LivePodcast.php';

if (!isLoggedIn() || empty($_SESSION['user_id'])) { header('Location: /TMZ%20PODCAST/login.php'); exit; }

$liveId = (int)($_GET['live_id'] ?? 0);
$preGift = (int)($_GET['gift_id'] ?? 0);
$preCurrency = strtoupper(substr((string)($_GET['currency'] ?? 'USD'), 0, 8));
$liveModel = new LivePodcast();
$live = $liveId ? $liveModel->getById($liveId) : null;
$giftModel = new Gift();
$gifts = $giftModel->gifts(true);
$rates = $giftModel->rates();
$commenters = $liveId ? $giftModel->recentCommenters($liveId, 20) : [];
$error = $_SESSION['gift_error'] ?? ''; unset($_SESSION['gift_error']);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Send a Gift - THE DAK FILM PODCAST MEDIA</title>
<link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/style.css">
<link rel="stylesheet" href="/TMZ%20PODCAST/assets/css/cinematic.css">
<style>
.gift-page{max-width:1100px;margin:0 auto;padding:2rem 1rem 4rem}
.gift-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:.7rem;margin:1rem 0 1.5rem}
.gift-item{position:relative;display:flex;flex-direction:column;align-items:center;gap:.3rem;padding:.8rem .4rem;border:1px solid #263d7f;border-radius:.7rem;background:#0d1733;color:#eef3ff;cursor:pointer;text-align:center;transition:border-color .15s,transform .15s}
.gift-item:hover{border-color:#4d70ce;transform:translateY(-2px)}
.gift-item.selected{border-color:#ffb020;box-shadow:0 0 0 2px rgba(255,176,32,.35)}
.gift-item .emoji{font-size:1.9rem;line-height:1.2}
.gift-item strong{font-size:.8rem;line-height:1.2;overflow-wrap:anywhere}
.gift-item small{color:#9fb4dd;font-size:.72rem}
.gift-item .price{color:#ffd479;font-weight:800;font-size:.78rem}
.gift-form{display:grid;gap:.9rem;max-width:560px}
.gift-form label{display:flex;flex-direction:column;gap:.3rem;font-size:.85rem;font-weight:600;color:#c8d4ee}
.gift-form select,.gift-form input,.gift-form textarea{padding:.6rem .7rem;border:1px solid #3157be;border-radius:.5rem;background:#0d1733;color:#eef3ff;font-size:.9rem}
.gift-form textarea{resize:vertical;min-height:70px}
.gift-total{font-size:1.05rem;color:#ffd479;font-weight:800}
.gift-error{background:#3a1420;border:1px solid #a12;border-radius:.5rem;padding:.7rem 1rem;color:#ffb6bd}
.gift-section-title{margin:1.6rem 0 .4rem}
</style>
</head>
<body class="tmz-cinema">
<main class="gift-page">
    <nav class="cine-crumbs"><a href="/TMZ%20PODCAST/live.php<?php echo $liveId ? '?live_id=' . $liveId : ''; ?>">&larr; Back to live</a></nav>
    <span class="eyebrow">LIVE GIFTS</span>
    <h1>Send a gift<?php echo $live ? ' — ' . $h($live['title']) : ''; ?></h1>
    <p style="color:#9fb4dd">Pick a gift, choose who receives it, and pay in your currency. The amount is converted automatically.</p>

    <?php if (!$live): ?>
        <div class="empty-state"><h2>No live show selected</h2><p><a href="/TMZ%20PODCAST/live.php">Open the live page</a> and tap “Send a gift” there.</p></div>
    <?php else: ?>
        <?php if ($error): ?><div class="gift-error"><?php echo $h($error); ?></div><?php endif; ?>

        <form method="post" action="/TMZ%20PODCAST/gift-checkout.php" id="giftForm">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="live_id" value="<?php echo $liveId; ?>">
            <input type="hidden" name="gift_id" id="giftId" value="<?php echo $preGift; ?>">

            <h2 class="gift-section-title">1 · Choose a gift</h2>
            <div class="gift-grid" id="giftGrid">
                <?php foreach ($gifts as $g): ?>
                <button type="button" class="gift-item<?php echo (int)$g['id'] === $preGift ? ' selected' : ''; ?>" data-gift-id="<?php echo (int)$g['id']; ?>" data-usd="<?php echo (float)$g['price_usd']; ?>">
                    <span class="emoji"><?php echo $h($g['emoji']); ?></span>
                    <strong><?php echo $h($g['name']); ?></strong>
                    <span class="price">$<?php echo number_format((float)$g['price_usd'], 2); ?></span>
                </button>
                <?php endforeach; ?>
            </div>

            <h2 class="gift-section-title">2 · Who is it for?</h2>
            <div class="gift-form">
                <label>Recipient
                    <select name="recipient_type" id="recipientType">
                        <option value="host">The Host</option>
                        <?php foreach ($commenters as $c): if ((int)$c['user_id'] === (int)$_SESSION['user_id']) continue; ?>
                        <option value="user" data-uid="<?php echo (int)$c['user_id']; ?>" data-name="<?php echo $h($c['username']); ?>"><?php echo $h($c['username']); ?> (participant)</option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <input type="hidden" name="recipient_user_id" id="recipientUserId" value="">
                <input type="hidden" name="recipient_name" id="recipientName" value="The Host">

                <label>Pay in currency
                    <select name="currency" id="currencySelect">
                        <?php foreach ($rates as $r): ?>
                        <option value="<?php echo $h($r['code']); ?>"<?php echo strtoupper($r['code']) === $preCurrency ? ' selected' : ''; ?>><?php echo $h($r['code'] . ' — ' . $r['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>Dedication message (optional)
                    <textarea name="message" maxlength="300" placeholder="Say something nice…"></textarea>
                </label>

                <p class="gift-total" id="giftTotal">Select a gift to see the total.</p>
                <button type="submit" class="btn btn-primary" id="giftSubmit" disabled>Send gift with Pesapal</button>
            </div>
        </form>
    <?php endif; ?>
</main>
<script>
(function () {
    var rates = <?php echo json_encode(array_column($rates, 'rate_to_usd', 'code')); ?>;
    var selectedUsd = 0, selectedCode = document.getElementById('currencySelect')?.value || 'USD';
    var grid = document.getElementById('giftGrid'), form = document.getElementById('giftForm');
    if (!grid || !form) return;

    function fmt(n) { return n.toLocaleString(undefined, { maximumFractionDigits: 2 }); }
    function updateTotal() {
        var total = document.getElementById('giftTotal'), btn = document.getElementById('giftSubmit');
        if (!selectedUsd) { total.textContent = 'Select a gift to see the total.'; btn.disabled = true; return; }
        var amount = selectedUsd * (rates[selectedCode] || 1);
        total.textContent = 'Total: ' + selectedCode + ' ' + fmt(amount);
        btn.disabled = false;
    }
    grid.addEventListener('click', function (e) {
        var b = e.target.closest('.gift-item'); if (!b) return;
        grid.querySelectorAll('.gift-item').forEach(function (x) { x.classList.remove('selected'); });
        b.classList.add('selected');
        selectedUsd = parseFloat(b.dataset.usd) || 0;
        document.getElementById('giftId').value = b.dataset.giftId;
        updateTotal();
    });
    document.getElementById('currencySelect').addEventListener('change', function () {
        selectedCode = this.value; updateTotal();
    });
    document.getElementById('recipientType').addEventListener('change', function () {
        var opt = this.selectedOptions[0];
        document.getElementById('recipientUserId').value = opt.dataset.uid || '';
        document.getElementById('recipientName').value = opt.dataset.name || 'The Host';
    });
    // preselect gift & recipient if provided
    var pre = document.querySelector('.gift-item.selected');
    if (pre) { selectedUsd = parseFloat(pre.dataset.usd) || 0; }
    document.getElementById('recipientType').dispatchEvent(new Event('change'));
    updateTotal();
})();
</script>
</body>
</html>
