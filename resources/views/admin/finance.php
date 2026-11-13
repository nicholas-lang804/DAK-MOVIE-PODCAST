<?php
$h = function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$months = [];
for ($i = 5; $i >= 0; $i--) {
    $key = date('Y-m', strtotime('-' . $i . ' months'));
    $months[$key] = date('M Y', strtotime($key . '-01'));
}
$charts = [];
foreach (($summary['revenue'] ?? []) as $row) {
    $charts[$row['currency']] = array_fill_keys(array_keys($months), 0.0);
}
foreach (($monthlyRevenue ?? []) as $row) {
    $currency = $row['currency'];
    if (!isset($charts[$currency])) {
        $charts[$currency] = array_fill_keys(array_keys($months), 0.0);
    }
    $charts[$currency][$row['month']] = (float) $row['total'];
}
$paymentPages = max(1, (int) ceil(($paymentCount ?? 0) / ($pageSize ?? 50)));
$ticketPages = max(1, (int) ceil(($ticketCount ?? 0) / ($pageSize ?? 50)));
?>

<section class="admin-section finance-page">
    <div class="section-header">
        <div>
            <h1>Financial Control</h1>
            <p class="form-help">Monitor recorded payments, ticket sales and income by currency.</p>
        </div>
        <div class="finance-downloads">
            <a class="btn btn-outline" href="/TMZ%20PODCAST/admin/financial-download.php?type=payments">Download payments CSV</a>
            <a class="btn btn-outline" href="/TMZ%20PODCAST/admin/financial-download.php?type=tickets">Download tickets CSV</a>
        </div>
    </div>

    <div class="finance-stat-grid">
        <article class="finance-stat-card">
            <span>Tickets issued</span>
            <strong><?php echo number_format((int) ($summary['ticketsSold'] ?? 0)); ?></strong>
            <small>Excludes cancelled tickets</small>
        </article>
        <article class="finance-stat-card">
            <span>Completed payments</span>
            <strong><?php echo number_format((int) ($summary['paymentCounts']['completed'] ?? 0)); ?></strong>
            <small>Online payments recorded</small>
        </article>
        <article class="finance-stat-card">
            <span>Pending payments</span>
            <strong><?php echo number_format((int) ($summary['paymentCounts']['pending'] ?? 0)); ?></strong>
            <small>Awaiting gateway confirmation</small>
        </article>
        <article class="finance-stat-card">
            <span>Failed / refunded</span>
            <strong><?php echo number_format((int) ($summary['paymentCounts']['failed'] ?? 0) + (int) ($summary['paymentCounts']['refunded'] ?? 0)); ?></strong>
            <small><?php echo number_format((int) ($summary['paymentCounts']['failed'] ?? 0)); ?> failed · <?php echo number_format((int) ($summary['paymentCounts']['refunded'] ?? 0)); ?> refunded</small>
        </article>
    </div>

    <section class="finance-panel">
        <div class="finance-panel-heading">
            <div>
                <h2>Revenue overview</h2>
                <p>Monthly recorded revenue. Each currency is shown separately.</p>
            </div>
        </div>
        <?php if (!empty($summary['revenue'])): ?>
            <div class="finance-revenue-totals">
                <?php foreach ($summary['revenue'] as $row): ?>
                    <div><span><?php echo $h($row['currency']); ?> all-time revenue</span><strong><?php echo $h($row['currency']); ?> <?php echo number_format((float) $row['total'], 2); ?></strong></div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="finance-empty">No completed revenue has been recorded yet.</p>
        <?php endif; ?>

        <?php if (!empty($charts)): ?>
            <div class="finance-charts">
                <?php foreach ($charts as $currency => $values):
                    $max = max($values) ?: 1; ?>
                    <div class="finance-chart" role="img" aria-label="<?php echo $h($currency); ?> monthly revenue for the last six months">
                        <img class="tmz-chart-logo" src="/TMZ%20PODCAST/assets/img/dak-mark.png" alt="THE DAK FILM PODCAST MEDIA">
                        <h3><?php echo $h($currency); ?> monthly revenue</h3>
                        <div class="finance-chart-bars">
                            <?php foreach ($months as $key => $label):
                                $value = $values[$key] ?? 0;
                                $height = $value > 0 ? max(4, (int) round(($value / $max) * 100)) : 0; ?>
                                <div class="finance-chart-column" title="<?php echo $h($label . ': ' . $currency . ' ' . number_format($value, 2)); ?>">
                                    <span class="finance-chart-value"><?php echo $value > 0 ? number_format($value, 0) : ''; ?></span>
                                    <div class="finance-chart-track"><span style="height: <?php echo $height; ?>%"></span></div>
                                    <span class="finance-chart-label"><?php echo $h($label); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="finance-empty">Monthly revenue charts will appear when payments or physical ticket sales are recorded.</p>
        <?php endif; ?>
    </section>

    <section class="finance-panel">
        <div class="finance-panel-heading">
            <div>
                <h2>All payments</h2>
                <p><?php echo number_format((int) $paymentCount); ?> payment records, including pending and failed transactions.</p>
            </div>
        </div>
        <div class="table-container">
            <table class="admin-table finance-table">
                <thead><tr><th>Reference</th><th>Customer</th><th>Purchase</th><th>Amount</th><th>Status</th><th>Gateway reference</th><th>Created</th><th>Paid</th></tr></thead>
                <tbody>
                <?php if (empty($payments)): ?>
                    <tr><td colspan="8">No payment records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($payments as $payment):
                    $purchase = $payment['event_title'] ?: ($payment['movie_title'] ?: ($payment['plan_name'] ?: '—')); ?>
                    <tr>
                        <td><?php echo $h($payment['reference']); ?></td>
                        <td><?php echo $h($payment['username'] ?: $payment['customer_email'] ?: 'Unknown'); ?></td>
                        <td><?php echo $h($purchase); ?></td>
                        <td><?php echo $h($payment['currency']); ?> <?php echo number_format((float) $payment['amount'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $h($payment['status']); ?>"><?php echo $h(ucfirst($payment['status'])); ?></span></td>
                        <td><?php echo $h($payment['gateway_reference'] ?: '—'); ?></td>
                        <td><?php echo $h(date('M d, Y H:i', strtotime($payment['created_at']))); ?></td>
                        <td><?php echo !empty($payment['paid_at']) ? $h(date('M d, Y H:i', strtotime($payment['paid_at']))) : '—'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($paymentPages > 1): ?>
            <nav class="finance-pagination" aria-label="Payment pages">
                <?php if ($paymentPage > 1): ?><a href="/TMZ%20PODCAST/admin/dashboard.php?tab=finance&amp;payments_page=<?php echo (int) $paymentPage - 1; ?>&amp;tickets_page=<?php echo (int) $ticketPage; ?>">Previous</a><?php endif; ?>
                <span>Page <?php echo (int) $paymentPage; ?> of <?php echo $paymentPages; ?></span>
                <?php if ($paymentPage < $paymentPages): ?><a href="/TMZ%20PODCAST/admin/dashboard.php?tab=finance&amp;payments_page=<?php echo (int) $paymentPage + 1; ?>&amp;tickets_page=<?php echo (int) $ticketPage; ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

    <section class="finance-panel">
        <div class="finance-panel-heading">
            <div>
                <h2>Tickets sold</h2>
                <p><?php echo number_format((int) $ticketCount); ?> issued tickets across online and physical sales.</p>
            </div>
        </div>
        <div class="table-container">
            <table class="admin-table finance-table">
                <thead><tr><th>Ticket code</th><th>Event</th><th>Ticket type</th><th>Buyer</th><th>Order</th><th>Sale channel</th><th>Order total</th><th>Ticket status</th><th>Issued</th></tr></thead>
                <tbody>
                <?php if (empty($tickets)): ?>
                    <tr><td colspan="9">No event tickets have been issued.</td></tr>
                <?php endif; ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo $h($ticket['ticket_code']); ?></td>
                        <td><?php echo $h($ticket['event_title']); ?></td>
                        <td><?php echo $h($ticket['ticket_type']); ?></td>
                        <td><?php echo $h($ticket['buyer_name'] ?: ($ticket['username'] ?: ($ticket['customer_email'] ?: 'Walk-in'))); ?></td>
                        <td><?php echo $h($ticket['reference']); ?></td>
                        <td><?php echo $h(ucfirst($ticket['channel'])); ?></td>
                        <td><?php echo $h($ticket['currency']); ?> <?php echo number_format((float) $ticket['total'], 2); ?></td>
                        <td><span class="status-badge status-<?php echo $h($ticket['ticket_status']); ?>"><?php echo $h(ucfirst($ticket['ticket_status'])); ?></span></td>
                        <td><?php echo $h(date('M d, Y H:i', strtotime($ticket['issued_at']))); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($ticketPages > 1): ?>
            <nav class="finance-pagination" aria-label="Ticket pages">
                <?php if ($ticketPage > 1): ?><a href="/TMZ%20PODCAST/admin/dashboard.php?tab=finance&amp;payments_page=<?php echo (int) $paymentPage; ?>&amp;tickets_page=<?php echo (int) $ticketPage - 1; ?>">Previous</a><?php endif; ?>
                <span>Page <?php echo (int) $ticketPage; ?> of <?php echo $ticketPages; ?></span>
                <?php if ($ticketPage < $ticketPages): ?><a href="/TMZ%20PODCAST/admin/dashboard.php?tab=finance&amp;payments_page=<?php echo (int) $paymentPage; ?>&amp;tickets_page=<?php echo (int) $ticketPage + 1; ?>">Next</a><?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</section>
