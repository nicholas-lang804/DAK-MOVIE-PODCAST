<?php
/** Admin: Pesapal payments — credentials, IPN registration, stats and transaction history. */
$s = $settings;
$g = $gateway;
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$cfg = $g->isConfigured();
$env = $g->environment();
$connStatus = $s['pesapal_connection_status'] ?? '';
$ipnStatus = $s['pesapal_ipn_status'] ?? '';
$hasSecret = trim((string)($s['pesapal_consumer_secret'] ?? '')) !== '';
$callbackUrl = $g->callbackUrl();
$ipnUrl = $g->ipnUrl();
?>
<section class="admin-section">
    <div class="section-header">
        <div><span class="eyebrow">PAYMENTS</span><h1>Pesapal Payments</h1><p>Enter your Pesapal Consumer Key and Secret, pick Sandbox or Live, then test the connection and register the IPN automatically. Secrets are stored server-side and never shown again.</p></div>
    </div>

    <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

    <!-- Status cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
        <div class="stat-card"><span class="stat-label">Credentials</span><strong><?php echo $cfg ? 'Configured' : 'Missing'; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Environment</span><strong><?php echo strtoupper($env); ?></strong></div>
        <div class="stat-card"><span class="stat-label">Connection</span><strong><?php echo $connStatus === 'ok' ? 'OK' : ($connStatus === 'fail' ? 'Failed' : 'Not tested'); ?></strong></div>
        <div class="stat-card"><span class="stat-label">IPN registration</span><strong><?php echo $ipnStatus === 'registered' ? 'Registered' : 'Not registered'; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Notification ID</span><strong style="font-size:.85rem;word-break:break-all"><?php echo $h($s['pesapal_notification_id'] ?? '—'); ?></strong></div>
        <div class="stat-card"><span class="stat-label">Last test</span><strong><?php echo $h($s['pesapal_last_test_at'] ?? '—'); ?></strong></div>
    </div>

    <!-- Credentials -->
    <h2>Pesapal credentials</h2>
    <form method="post" class="admin-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.8rem;align-items:end">
        <?php echo CSRF::getField(); ?>
        <input type="hidden" name="payment_action" value="save_credentials">
        <label>Consumer Key<input name="pesapal_consumer_key" value="<?php echo $h($s['pesapal_consumer_key'] ?? ''); ?>" autocomplete="off"></label>
        <label>Consumer Secret<?php if ($hasSecret): ?><small style="display:block;color:#888">Saved — leave blank to keep it</small><?php endif; ?><input type="password" name="pesapal_consumer_secret" value="" placeholder="<?php echo $hasSecret ? '•••••••• (saved)' : 'Enter secret'; ?>" autocomplete="new-password"></label>
        <label>Environment<select name="pesapal_environment"><option value="sandbox" <?php echo $env === 'sandbox' ? 'selected' : ''; ?>>Sandbox / testing</option><option value="live" <?php echo $env === 'live' ? 'selected' : ''; ?>>Live / production</option></select></label>
        <label>Public base URL <small style="display:block;color:#888">Optional override — auto-detected otherwise</small><input name="pesapal_public_base_url" value="<?php echo $h($s['pesapal_public_base_url'] ?? ''); ?>" placeholder="https://your-domain/"></label>
        <div><button class="btn btn-primary" type="submit">Save settings</button></div>
    </form>

    <!-- Connection + IPN -->
    <h2 style="margin-top:2rem">Connection &amp; IPN</h2>
    <div class="table-container" style="margin-bottom:1rem">
        <table class="admin-table">
            <tbody>
                <tr><td>Callback URL</td><td style="word-break:break-all"><code><?php echo $h($callbackUrl); ?></code></td></tr>
                <tr><td>IPN URL</td><td style="word-break:break-all"><code><?php echo $h($ipnUrl); ?></code></td></tr>
                <tr><td>IPN registered at</td><td><?php echo $h($s['pesapal_ipn_registered_at'] ?? '—'); ?></td></tr>
            </tbody>
        </table>
    </div>
    <div style="display:flex;gap:.6rem;flex-wrap:wrap">
        <form method="post"><?php echo CSRF::getField(); ?><input type="hidden" name="payment_action" value="test_connection"><button class="btn btn-outline" type="submit">Test connection</button></form>
        <form method="post" onsubmit="return confirm('Check that both endpoints are publicly reachable, then register (or reuse) the IPN with Pesapal?');"><?php echo CSRF::getField(); ?><input type="hidden" name="payment_action" value="register_ipn"><button class="btn btn-primary" type="submit">Register / refresh IPN</button></form>
        <form method="post" onsubmit="return confirm('Force-register a NEW IPN even if one already exists for this URL? Only needed if the old registration is broken.');"><?php echo CSRF::getField(); ?><input type="hidden" name="payment_action" value="register_ipn"><input type="hidden" name="force_new_ipn" value="1"><button class="btn btn-outline" type="submit">Force new IPN</button></form>
    </div>
    <p style="color:#888;font-size:.85rem;margin-top:.6rem">The IPN is registered automatically via Pesapal's RegisterIPN API — the returned Notification ID is saved here. Registration is refused if the callback/IPN URLs are not publicly reachable (e.g. still on localhost).</p>

    <!-- Stats -->
    <h2 style="margin-top:2rem">Transactions</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:1rem;margin-bottom:1rem">
        <div class="stat-card"><span class="stat-label">Total</span><strong><?php echo (int)$paymentStats['total']; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Completed</span><strong><?php echo (int)$paymentStats['completed']; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Pending</span><strong><?php echo (int)$paymentStats['pending']; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Failed</span><strong><?php echo (int)$paymentStats['failed']; ?></strong></div>
        <div class="stat-card"><span class="stat-label">Revenue (completed)</span><strong><?php echo number_format((float)$paymentStats['revenue'], 2); ?></strong></div>
    </div>

    <div class="table-container" style="max-height:520px;overflow:auto">
        <table class="admin-table">
            <thead><tr><th>Date</th><th>Customer</th><th>Item</th><th>Amount</th><th>Reference</th><th>Gateway ref</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($paymentRows as $p):
                $item = $p['movie_title'] ?? ($p['gift_title'] ? 'Gift: ' . $p['gift_title'] . ' → ' . $p['gift_recipient'] : ($p['event_order_id'] ? 'Event tickets' : ($p['subscription_plan_id'] ? 'Subscription' : 'Payment')));
                $cls = $p['status'] === 'completed' ? 'published' : ($p['status'] === 'failed' ? 'draft' : 'pending');
            ?>
                <tr>
                    <td><?php echo $h(substr((string)$p['created_at'], 0, 16)); ?></td>
                    <td><?php echo $h($p['username'] ?? ('User #' . (int)$p['user_id'])); ?></td>
                    <td><?php echo $h($item); ?></td>
                    <td><?php echo $h($p['amount']); ?> <?php echo $h($p['currency']); ?></td>
                    <td style="font-size:.78rem"><?php echo $h($p['reference']); ?></td>
                    <td style="font-size:.78rem"><?php echo $h($p['gateway_reference'] ?: '—'); ?></td>
                    <td><span class="status-badge status-<?php echo $cls; ?>"><?php echo ucfirst($p['status']); ?></span></td>
                    <td><?php if ($p['gateway_reference']): ?><form method="post" style="display:inline"><?php echo CSRF::getField(); ?><input type="hidden" name="payment_action" value="verify_payment"><input type="hidden" name="payment_id" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-outline" type="submit" style="padding:.25rem .6rem;font-size:.8rem">Verify</button></form><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($paymentRows)): ?><tr><td colspan="8">No payments yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
