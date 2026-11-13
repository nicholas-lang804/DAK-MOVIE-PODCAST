<?php
/**
 * Admin Events Management View
 * Events, ticket levels, discounts, physical sales and gate check-in
 */
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$event = $event ?? null;
$isForm = ($action === 'create' || $action === 'edit');
$eventId = (int)($event['id'] ?? 0);

$ticketRows = $ticketTypes ?? [];
$defaults = [['name' => 'Ordinary', 'code' => 'ORDINARY'], ['name' => 'VIP', 'code' => 'VIP'], ['name' => 'VVIP', 'code' => 'VVIP']];
$levelCount = max(3, count($ticketRows));
$ruleRows = $discountRules ?? [];
$ruleCount = max(2, count($ruleRows));
$dtLocal = function ($v) { return !empty($v) ? date('Y-m-d\TH:i', strtotime($v)) : ''; };
?>

<section class="admin-section">
    <div class="section-header">
        <h1>Manage Events &amp; Tickets</h1>
        <a href="/admin/dashboard.php?tab=events&amp;action=create" class="btn btn-primary">Add Event</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $h($_SESSION['success']); ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-error"><?php echo $h($_SESSION['error']); ?></div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

<?php if ($isForm): ?>
    <div class="form-container">
        <h2><?php echo $action === 'create' ? 'Create Event' : 'Edit Event'; ?></h2>
        <form method="POST" action="/admin/dashboard.php?tab=events" class="admin-form">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="form_action" value="save_event">
            <input type="hidden" name="id" value="<?php echo $eventId ?: ''; ?>">

            <div class="form-group">
                <label for="title">Title *</label>
                <input type="text" id="title" name="title" value="<?php echo $h($event['title'] ?? ''); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="6"><?php echo $h($event['description'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label for="event_date">Event date &amp; time</label>
                <input type="datetime-local" id="event_date" name="event_date" value="<?php echo $dtLocal($event['event_date'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="location">Location / venue</label>
                <input type="text" id="location" name="location" value="<?php echo $h($event['location'] ?? ''); ?>">
            </div>

            <div class="event-contact-grid">
                <div class="form-group"><label for="contact_name">Contact person</label><input id="contact_name" name="contact_name" value="<?php echo $h($event['contact_name'] ?? ''); ?>"></div>
                <div class="form-group"><label for="contact_phone">Contact phone</label><input id="contact_phone" name="contact_phone" value="<?php echo $h($event['contact_phone'] ?? ''); ?>"></div>
            </div>
            <p class="form-help">Visitor enquiries are sent to the public contact email configured in Website Settings.</p>

            <div class="form-group">
                <label for="registration_url">External registration URL (optional)</label>
                <input type="url" id="registration_url" name="registration_url" value="<?php echo $h($event['registration_url'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php $cur = $event['status'] ?? 'published'; ?>
                    <option value="draft" <?php echo $cur === 'draft' ? 'selected' : ''; ?>>Draft (hidden)</option>
                    <option value="published" <?php echo $cur === 'published' ? 'selected' : ''; ?>>Published — visible to everyone, tickets on sale</option>
                    <option value="archived" <?php echo $cur === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    <option value="past" <?php echo $cur === 'past' ? 'selected' : ''; ?>>Past</option>
                </select>
            </div>

            <?php if (!empty($feeSettings['enabled']) && !empty($event['user_id'])): $ef = $eventFee ?? null; $efStatus = $ef['status'] ?? 'none'; ?>
            <div class="form-container" style="border-left:4px solid #c9a227">
                <h3>Publishing fee — organiser event</h3>
                <p class="form-help">Fixed fee to publish this event: <strong><?php echo $h(EventPublishFee::feeAmount() . ' ' . EventPublishFee::feeCurrency()); ?></strong>. An event can only be set to <em>Published</em> after this fee is paid or exempted. Editing an already-published event never re-charges.</p>
                <p>Status: <span class="status-badge status-<?php echo $h($efStatus === 'paid' ? 'published' : ($efStatus === 'exempt' ? 'active' : 'draft')); ?>"><?php echo $h(ucfirst($efStatus)); ?></span>
                <?php if ($ef && $ef['reference']): ?> · Ref <?php echo $h($ef['reference']); ?><?php endif; ?>
                <?php if ($ef && $ef['paid_at']): ?> · Paid <?php echo $h($ef['paid_at']); ?><?php endif; ?></p>
                <div class="admin-actions">
                    <?php if (in_array($efStatus, ['none','failed','cancelled'], true)): ?>
                    <form method="POST" action="/admin/dashboard.php?tab=events" class="inline-form"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="form_action" value="pay_event_fee"><input type="hidden" name="id" value="<?php echo (int)$eventId; ?>">
                        <button class="btn btn-primary btn-small" type="submit">Pay publishing fee (Pesapal)</button></form>
                    <?php endif; ?>
                    <?php if ($efStatus !== 'exempt' && $efStatus !== 'paid'): ?>
                    <form method="POST" action="/admin/dashboard.php?tab=events" class="inline-form" onsubmit="return confirm('Grant a free-publish exemption for this event?')"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="form_action" value="grant_exemption"><input type="hidden" name="id" value="<?php echo (int)$eventId; ?>">
                        <input type="text" name="fee_note" placeholder="Reason (optional)" style="max-width:180px">
                        <button class="btn btn-outline btn-small" type="submit">Grant exemption</button></form>
                    <?php endif; ?>
                    <?php if ($efStatus === 'exempt'): ?>
                    <form method="POST" action="/admin/dashboard.php?tab=events" class="inline-form"><?php echo CSRF::getField(); ?>
                        <input type="hidden" name="form_action" value="revoke_exemption"><input type="hidden" name="id" value="<?php echo (int)$eventId; ?>">
                        <button class="btn btn-small" type="submit">Revoke exemption</button></form>
                    <?php endif; ?>
                </div>
            </div>
            <?php elseif (!empty($feeSettings['enabled'])): ?>
            <div class="form-container" style="border-left:4px solid #2e7d32">
                <h3>Official platform event</h3>
                <p class="form-help">This is a platform event — publishing is free and it is always eligible for site promotion. No publishing fee applies.</p>
            </div>
            <?php endif; ?>

            <div class="event-ticket-builder">
                <h3>Ticket levels &amp; prices</h3>
                <p class="form-help">Set a price and optional capacity for each level (e.g. Ordinary, VIP, VVIP). Leave a row empty to skip it. Removing a level hides it from sale but keeps its sales history.</p>
                <div class="ticket-level-head"><span>Level</span><span>Code</span><span>Price</span><span>Currency</span><span>Capacity</span></div>
                <div id="ticket-levels">
                <?php for ($i = 0; $i < $levelCount; $i++): $t = $ticketRows[$i] ?? ($defaults[$i] ?? []); ?>
                    <div class="ticket-level-row">
                        <input name="tickets[<?php echo $i; ?>][name]" value="<?php echo $h($t['name'] ?? ''); ?>" placeholder="Level name">
                        <input name="tickets[<?php echo $i; ?>][code]" value="<?php echo $h($t['code'] ?? ''); ?>" placeholder="CODE">
                        <input type="number" min="0" step="0.01" name="tickets[<?php echo $i; ?>][price]" value="<?php echo $h($t['price'] ?? ''); ?>" placeholder="Price">
                        <input maxlength="3" name="tickets[<?php echo $i; ?>][currency]" value="<?php echo $h($t['currency'] ?? 'UGX'); ?>" placeholder="UGX">
                        <input type="number" min="1" name="tickets[<?php echo $i; ?>][capacity]" value="<?php echo $h($t['capacity'] ?? ''); ?>" placeholder="Unlimited">
                    </div>
                <?php endfor; ?>
                </div>
                <button type="button" class="btn btn-outline btn-small" id="add-level">+ Add another level</button>
            </div>

            <div class="event-ticket-builder">
                <h3>Discounts</h3>
                <p class="form-help">Each discount has a code you share with the people who qualify, a percentage, and the period it is valid. Choose whether it works online, for physical sales recorded below, or both.</p>
                <div class="discount-rule-head"><span>Code</span><span>Who receives it</span><span>Discount %</span><span>Applies to</span><span>Starts</span><span>Ends</span></div>
                <div id="discount-rules">
                <?php for ($i = 0; $i < $ruleCount; $i++): $r = $ruleRows[$i] ?? []; $ch = $r['channel'] ?? 'both'; ?>
                    <div class="discount-rule-grid">
                        <input name="discounts[<?php echo $i; ?>][code]" value="<?php echo $h($r['code'] ?? ''); ?>" placeholder="e.g. STUDENT20">
                        <input name="discounts[<?php echo $i; ?>][eligible_label]" value="<?php echo $h($r['eligible_label'] ?? ''); ?>" placeholder="e.g. Students">
                        <input type="number" min="0" max="100" step="0.01" name="discounts[<?php echo $i; ?>][discount_percent]" value="<?php echo $h($r['discount_percent'] ?? ''); ?>" placeholder="%">
                        <select name="discounts[<?php echo $i; ?>][channel]">
                            <option value="both" <?php echo $ch === 'both' ? 'selected' : ''; ?>>Online &amp; physical</option>
                            <option value="online" <?php echo $ch === 'online' ? 'selected' : ''; ?>>Online only</option>
                            <option value="physical" <?php echo $ch === 'physical' ? 'selected' : ''; ?>>Physical only</option>
                        </select>
                        <input type="datetime-local" name="discounts[<?php echo $i; ?>][starts_at]" value="<?php echo $dtLocal($r['starts_at'] ?? ''); ?>">
                        <input type="datetime-local" name="discounts[<?php echo $i; ?>][ends_at]" value="<?php echo $dtLocal($r['ends_at'] ?? ''); ?>">
                    </div>
                <?php endfor; ?>
                </div>
                <button type="button" class="btn btn-outline btn-small" id="add-rule">+ Add another discount</button>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Save Event</button>
                <a href="/admin/dashboard.php?tab=events" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>

<?php if ($action === 'edit' && $eventId): ?>
    <?php $sum = $salesSummary; $cur0 = $ticketRows[0]['currency'] ?? 'UGX'; ?>
    <div class="form-container admin-ticket-panel">
        <h2>Sales overview</h2>
        <div class="sales-stats">
            <div><span>Tickets issued</span><strong><?php echo (int)$sum['tickets_issued']; ?></strong></div>
            <div><span>Checked in</span><strong><?php echo (int)$sum['tickets_used']; ?></strong></div>
            <div><span>Online revenue</span><strong><?php echo $h(Event::money($cur0, $sum['online']['revenue'])); ?></strong><small><?php echo (int)$sum['online']['orders']; ?> orders</small></div>
            <div><span>Physical revenue</span><strong><?php echo $h(Event::money($cur0, $sum['physical']['revenue'])); ?></strong><small><?php echo (int)$sum['physical']['orders']; ?> orders</small></div>
            <div><span>Discounts given</span><strong><?php echo $h(Event::money($cur0, $sum['online']['discounts'] + $sum['physical']['discounts'])); ?></strong></div>
        </div>
        <?php if (!empty($ticketRows)): ?>
        <table class="admin-table">
            <thead><tr><th>Level</th><th>Price</th><th>Sold</th><th>Capacity</th></tr></thead>
            <tbody>
            <?php foreach ($ticketRows as $t): ?>
                <tr><td><?php echo $h($t['name']); ?></td><td><?php echo $h(Event::money($t['currency'], $t['price'])); ?></td><td><?php echo (int)$t['sold_count']; ?></td><td><?php echo $t['capacity'] === null ? 'Unlimited' : (int)$t['capacity']; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <div class="admin-two-col">
        <div class="form-container admin-ticket-panel">
            <h2>Record a physical sale</h2>
            <p class="form-help">For tickets sold in person. Tickets are issued instantly and counted against capacity. Enter a discount code to apply a physical discount.</p>
            <?php if (empty($ticketRows)): ?>
                <p class="form-help">Save at least one ticket level first.</p>
            <?php else: ?>
            <form method="POST" action="/admin/dashboard.php?tab=events" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="form_action" value="physical_sale">
                <input type="hidden" name="id" value="<?php echo $eventId; ?>">
                <div class="form-group"><label>Ticket level</label>
                    <select name="ticket_type_id" required>
                        <?php foreach ($ticketRows as $t): ?>
                            <option value="<?php echo (int)$t['id']; ?>"><?php echo $h($t['name'] . ' — ' . Event::money($t['currency'], $t['price'])); ?></option>
                        <?php endforeach; ?>
                    </select></div>
                <div class="form-group"><label>Quantity</label><input type="number" name="quantity" min="1" max="20" value="1" required></div>
                <div class="form-group"><label>Buyer name</label><input name="buyer_name" placeholder="Walk-in buyer"></div>
                <div class="form-group"><label>Buyer phone</label><input name="buyer_phone"></div>
                <div class="form-group"><label>Discount code (optional)</label><input name="discount_code" placeholder="e.g. STUDENT20"></div>
                <button type="submit" class="btn btn-primary">Record sale</button>
            </form>
            <?php endif; ?>
        </div>

        <div class="form-container admin-ticket-panel">
            <h2>Gate check-in</h2>
            <p class="form-help">Type or scan the code printed on the ticket (e.g. TMZ-AB12CD34EF).</p>
            <form method="POST" action="/admin/dashboard.php?tab=events" class="admin-form">
                <?php echo CSRF::getField(); ?>
                <input type="hidden" name="form_action" value="check_in">
                <input type="hidden" name="id" value="<?php echo $eventId; ?>">
                <div class="form-group"><label>Ticket code</label><input name="ticket_code" required autocomplete="off" placeholder="TMZ-XXXXXXXXXX"></div>
                <button type="submit" class="btn btn-primary">Check in ticket</button>
            </form>
        </div>
    </div>

    <div class="form-container admin-ticket-panel">
        <h2>Recent orders</h2>
        <?php if (empty($orders)): ?>
            <p class="form-help">No orders yet.</p>
        <?php else: ?>
        <div class="table-container"><table class="admin-table">
            <thead><tr><th>Reference</th><th>Buyer</th><th>Level</th><th>Qty</th><th>Total</th><th>Discount</th><th>Channel</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><?php echo $h($o['reference']); ?></td>
                    <td><?php echo $h($o['username'] ?: ($o['buyer_name'] ?: 'Walk-in')); ?></td>
                    <td><?php echo $h($o['ticket_name']); ?></td>
                    <td><?php echo (int)$o['quantity']; ?></td>
                    <td><?php echo $h(Event::money($o['currency'], $o['total'])); ?></td>
                    <td><?php echo $o['discount_code'] ? $h($o['discount_code']) : '—'; ?></td>
                    <td><?php echo $h(ucfirst($o['channel'])); ?></td>
                    <td><span class="status-badge status-<?php echo $h($o['status']); ?>"><?php echo $h(ucfirst($o['status'])); ?></span></td>
                    <td><?php echo $h(date('M d, H:i', strtotime($o['created_at']))); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

    <script>
    (function () {
        function addRow(containerId, buttonId) {
            var container = document.getElementById(containerId), button = document.getElementById(buttonId);
            if (!container || !button) return;
            button.addEventListener('click', function () {
                var rows = container.children, last = rows[rows.length - 1];
                var clone = last.cloneNode(true), index = rows.length;
                clone.querySelectorAll('input,select').forEach(function (el) {
                    el.name = el.name.replace(/\[\d+\]/, '[' + index + ']');
                    if (el.tagName === 'INPUT') el.value = '';
                    if (el.tagName === 'SELECT') el.selectedIndex = 0;
                });
                var cur = clone.querySelector('input[name$="[currency]"]');
                if (cur) cur.value = 'UGX';
                container.appendChild(clone);
            });
        }
        addRow('ticket-levels', 'add-level');
        addRow('discount-rules', 'add-rule');
    })();
    </script>

<?php else: ?>
    <div class="form-container" style="border-left:4px solid #c9a227">
        <h3>Organiser pricing — event publishing fee</h3>
        <?php $fs = $feeSettings ?? ['enabled'=>false,'amount'=>0,'currency'=>'UGX']; $fst = $feeStats ?? []; ?>
        <form method="POST" action="/admin/dashboard.php?tab=events" class="admin-form" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
            <?php echo CSRF::getField(); ?>
            <input type="hidden" name="form_action" value="save_fee_settings">
            <label style="display:flex;gap:6px;align-items:center"><input type="checkbox" name="fee_enabled" value="1" <?php echo !empty($fs['enabled']) ? 'checked' : ''; ?>> Fee enabled</label>
            <div class="form-group" style="margin:0"><label>Amount</label><input type="number" min="0" step="1" name="fee_amount" value="<?php echo $h($fs['amount']); ?>" style="width:120px"></div>
            <div class="form-group" style="margin:0"><label>Currency</label><input maxlength="3" name="fee_currency" value="<?php echo $h($fs['currency']); ?>" style="width:80px"></div>
            <button class="btn btn-primary btn-small" type="submit">Save pricing</button>
        </form>
        <p class="form-help">Collected: <strong><?php echo $h(EventPublishFee::feeCurrency()); ?> <?php echo number_format((float)($fst['revenue'] ?? 0)); ?></strong>
        · Paid <?php echo (int)($fst['paid_count'] ?? 0); ?> · Pending <?php echo (int)($fst['pending_count'] ?? 0); ?> · Exempt <?php echo (int)($fst['exempt_count'] ?? 0); ?> · Failed <?php echo (int)($fst['failed_count'] ?? 0); ?></p>
    </div>

    <?php if (!empty($feeRecords)): ?>
    <div class="table-container">
        <h3>Publishing fee records</h3>
        <table class="admin-table">
            <thead><tr><th>Event</th><th>Payer</th><th>Amount</th><th>Status</th><th>Reference</th><th>Date</th><th>Paid at</th></tr></thead>
            <tbody>
            <?php foreach ($feeRecords as $fr): ?>
                <tr>
                    <td><?php echo $h($fr['event_title'] ?? ('#' . $fr['event_id'])); ?></td>
                    <td><?php echo $h($fr['payer'] ?? '—'); ?></td>
                    <td><?php echo $h($fr['currency']); ?> <?php echo number_format((float)$fr['amount']); ?></td>
                    <td><span class="status-badge status-<?php echo $h($fr['status'] === 'paid' ? 'published' : ($fr['status'] === 'exempt' ? 'active' : 'draft')); ?>"><?php echo $h(ucfirst($fr['status'])); ?></span></td>
                    <td><?php echo $h($fr['reference'] ?? '—'); ?></td>
                    <td><?php echo $h(date('M d, Y', strtotime($fr['created_at']))); ?></td>
                    <td><?php echo $h($fr['paid_at'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($feeAudit)): ?>
    <div class="table-container">
        <h3>Fee audit trail</h3>
        <table class="admin-table">
            <thead><tr><th>When</th><th>Actor</th><th>Event</th><th>Action</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($feeAudit as $au): ?>
                <tr><td><?php echo $h($au['created_at']); ?></td><td><?php echo $h($au['actor'] ?? 'system'); ?></td><td><?php echo $au['event_id'] ? '#' . (int)$au['event_id'] : '—'; ?></td><td><?php echo $h($au['action']); ?></td><td><?php echo $h($au['detail']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="table-container">
        <table class="admin-table">
            <thead>
                <tr><th>Title</th><th>Date</th><th>Location</th><th>Tickets from</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="6">No events yet. Click “Add Event” to create the first one.</td></tr>
                <?php endif; ?>
                <?php foreach ($events as $row): ?>
                    <tr>
                        <td><?php echo $h($row['title']); ?></td>
                        <td><?php echo !empty($row['event_date']) ? date('M d, Y H:i', strtotime($row['event_date'])) : 'N/A'; ?></td>
                        <td><?php echo $h($row['location'] ?? 'N/A'); ?></td>
                        <td><?php echo isset($row['min_price']) && $row['min_price'] !== null ? $h(Event::money($row['price_currency'], $row['min_price'])) : '—'; ?></td>
                        <td><span class="status-badge status-<?php echo $h($row['status']); ?>"><?php echo $h(ucfirst($row['status'])); ?></span></td>
                        <td class="admin-actions">
                            <a href="/admin/dashboard.php?tab=events&amp;action=edit&amp;id=<?php echo (int)$row['id']; ?>" class="btn btn-small">Edit / Sales</a>
                            <form method="POST" action="/admin/dashboard.php?tab=events" class="inline-form" onsubmit="return confirm('Delete this event? Events with paid tickets cannot be deleted.');">
                                <?php echo CSRF::getField(); ?>
                                <input type="hidden" name="form_action" value="delete_event">
                                <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
</section>
