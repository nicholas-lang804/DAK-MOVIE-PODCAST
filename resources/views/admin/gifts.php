<?php
/** Admin: Live Gifts — catalog, exchange rates and sent-gift orders. */
$ge = $giftEdit ?? null;
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
?>
<section class="admin-section">
    <div class="section-header">
        <div><span class="eyebrow">LIVE ENGAGEMENT</span><h1>Live Gifts</h1><p>Viewers send these gifts to the host or participants during live shows. Prices are set in USD and converted to the sender's chosen currency.</p></div>
    </div>

    <?php if (isset($_SESSION['success'])): ?><div class="alert alert-success"><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?><div class="alert alert-error"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div><?php endif; ?>

    <h2><?php echo $ge ? 'Edit gift' : 'Add a gift'; ?></h2>
    <form method="post" class="admin-form" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:.8rem;align-items:end">
        <?php echo CSRF::getField(); ?>
        <input type="hidden" name="gift_action" value="save_gift">
        <input type="hidden" name="gift_id" value="<?php echo $ge ? (int)$ge['id'] : 0; ?>">
        <label>Emoji<input name="emoji" maxlength="8" value="<?php echo $h($ge['emoji'] ?? '🎁'); ?>" required></label>
        <label>Name<input name="name" maxlength="120" value="<?php echo $h($ge['name'] ?? ''); ?>" required></label>
        <label>Price (USD)<input type="number" name="price_usd" min="0.01" step="0.01" value="<?php echo $h($ge['price_usd'] ?? '1.00'); ?>" required></label>
        <label>Category<input name="category" maxlength="40" value="<?php echo $h($ge['category'] ?? 'fun'); ?>"></label>
        <label>Sort order<input type="number" name="sort_order" value="<?php echo (int)($ge['sort_order'] ?? 0); ?>"></label>
        <label style="font-weight:normal"><input type="checkbox" name="is_active" value="1" <?php echo empty($ge) || !empty($ge['is_active']) ? 'checked' : ''; ?>> Active</label>
        <div><button class="btn btn-primary" type="submit"><?php echo $ge ? 'Save changes' : 'Add gift'; ?></button>
        <?php if ($ge): ?><a class="btn btn-outline" href="/admin/dashboard.php?tab=gifts">Cancel</a><?php endif; ?></div>
    </form>

    <h2 style="margin-top:2rem">Gift catalog (<?php echo count($gifts); ?>)</h2>
    <div class="table-container" style="max-height:480px;overflow:auto">
        <table class="admin-table">
            <thead><tr><th></th><th>Name</th><th>Price (USD)</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($gifts as $g): ?>
                <tr>
                    <td style="font-size:1.4rem"><?php echo $h($g['emoji']); ?></td>
                    <td><?php echo $h($g['name']); ?></td>
                    <td>$<?php echo number_format((float)$g['price_usd'], 2); ?></td>
                    <td><?php echo $h($g['category']); ?></td>
                    <td><span class="status-badge status-<?php echo $g['is_active'] ? 'published' : 'draft'; ?>"><?php echo $g['is_active'] ? 'Active' : 'Hidden'; ?></span></td>
                    <td style="white-space:nowrap">
                        <a class="btn btn-small btn-outline" href="/admin/dashboard.php?tab=gifts&amp;edit=<?php echo (int)$g['id']; ?>">Edit</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('Delete this gift?');">
                            <?php echo CSRF::getField(); ?>
                            <input type="hidden" name="gift_action" value="delete_gift">
                            <input type="hidden" name="gift_id" value="<?php echo (int)$g['id']; ?>">
                            <button class="btn btn-small btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <h2 style="margin-top:2rem">Exchange rates (units per 1 USD)</h2>
    <form method="post">
        <?php echo CSRF::getField(); ?>
        <input type="hidden" name="gift_action" value="save_rates">
        <div class="table-container">
            <table class="admin-table">
                <thead><tr><th>Code</th><th>Name</th><th>Rate per USD</th></tr></thead>
                <tbody>
                <?php foreach ($giftRates as $r): ?>
                    <tr>
                        <td><input name="rate_code[]" value="<?php echo $h($r['code']); ?>" maxlength="8" style="width:70px"></td>
                        <td><input name="rate_name[]" value="<?php echo $h($r['name']); ?>" maxlength="60" style="width:100%"></td>
                        <td><input name="rate_value[]" type="number" step="0.000001" min="0.000001" value="<?php echo $h($r['rate_to_usd']); ?>" style="width:140px"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button class="btn btn-primary" type="submit" style="margin-top:.8rem">Save exchange rates</button>
    </form>

    <h2 style="margin-top:2rem">Sent gifts (<?php echo count($giftOrders); ?>)</h2>
    <div class="table-container" style="max-height:420px;overflow:auto">
        <table class="admin-table">
            <thead><tr><th>When</th><th>Gift</th><th>From</th><th>To</th><th>Amount</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($giftOrders as $o): ?>
                <tr>
                    <td><?php echo $h(date('M d, H:i', strtotime($o['created_at']))); ?></td>
                    <td><?php echo $h($o['emoji'] . ' ' . $o['gift_name']); ?><?php echo $o['message'] !== '' ? '<br><small>' . $h($o['message']) . '</small>' : ''; ?></td>
                    <td><?php echo $h($o['sender_name']); ?></td>
                    <td><?php echo $h($o['recipient_name']); ?></td>
                    <td><?php echo $h($o['currency'] . ' ' . number_format((float)$o['amount'], 2)); ?></td>
                    <td><span class="status-badge status-<?php echo $o['status'] === 'completed' ? 'published' : ($o['status'] === 'failed' ? 'draft' : 'pending'); ?>"><?php echo ucfirst($o['status']); ?></span></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($giftOrders)): ?><tr><td colspan="6">No gifts sent yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
