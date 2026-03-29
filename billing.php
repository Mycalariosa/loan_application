<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/loan_service.php';

$u = require_approved_user();
if (($u['role'] ?? '') === 'admin') {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

$pdo = db();
$uid = (int) $u['id'];
refresh_billing_penalties($pdo, $uid);

$yearFilter = isset($_GET['y']) ? (int) $_GET['y'] : 0;
$monthFilter = isset($_GET['m']) ? (int) $_GET['m'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_billing'])) {
    $bid = (int) ($_POST['billing_id'] ?? 0);
    try {
        pay_billing_id($pdo, $bid, $uid);
        flash_set('ok', 'Payment recorded for this billing period.');
    } catch (Throwable $e) {
        flash_set('error', $e->getMessage());
    }
    header('Location: ' . app_url('billing.php'));
    exit;
}

$st = $pdo->prepare(
    "SELECT * FROM billing_statements WHERE user_id = ? AND status IN ('pending','overdue')
     ORDER BY due_date ASC LIMIT 1"
);
$st->execute([$uid]);
$current = $st->fetch();

$summaryLoan = null;
$summarySchedule = [];
if ($current) {
    $lid = (int) $current['loan_id'];
    $sl = $pdo->prepare('SELECT * FROM loans WHERE id = ? AND user_id = ? LIMIT 1');
    $sl->execute([$lid, $uid]);
    $summaryLoan = $sl->fetch();
    if ($summaryLoan) {
        $sch = $pdo->prepare('SELECT due_date, monthly_principal, interest_display, total_due, status FROM billing_statements WHERE loan_id = ? ORDER BY due_date ASC');
        $sch->execute([$lid]);
        $summarySchedule = $sch->fetchAll();
    }
}

$hist = $pdo->prepare(
    'SELECT period_year, period_month, COUNT(*) AS c FROM billing_statements WHERE user_id = ? GROUP BY period_year, period_month ORDER BY period_year DESC, period_month DESC'
);
$hist->execute([$uid]);
$grouped = $hist->fetchAll();

$detailRows = [];
if ($yearFilter && $monthFilter) {
    $ds = $pdo->prepare(
        'SELECT * FROM billing_statements WHERE user_id = ? AND period_year = ? AND period_month = ? ORDER BY due_date ASC'
    );
    $ds->execute([$uid, $yearFilter, $monthFilter]);
    $detailRows = $ds->fetchAll();
}

render_header('Billing', $u);
flash_alert();
?>
<h1 class="h4 mb-3">Billing</h1>

<?php if ($summaryLoan && $summarySchedule !== []): ?>
<div class="format-sheet-section card p-0 overflow-hidden mb-4">
    <div class="format-sheet-title">Billing Summary</div>
    <div class="p-4">
        <table class="table table-sm table-bordered w-auto mb-4">
            <tbody>
                <tr><th class="bg-light">Loan Amount</th><td class="text-end">₱<?= number_format((float) $summaryLoan['requested_amount'], 2) ?></td></tr>
                <tr><th class="bg-light">Interest Rate (3%)</th><td class="text-end">₱<?= number_format((float) $summaryLoan['interest_amount'], 2) ?></td></tr>
                <tr><th class="bg-light">Total Amount on Hand</th><td class="text-end"><strong>₱<?= number_format((float) $summaryLoan['received_amount'], 2) ?></strong></td></tr>
            </tbody>
        </table>
        <div class="format-sheet-subtitle">Monthly Payments <span class="text-danger">(<?= (int) $summaryLoan['term_months'] ?> payable months)</span></div>
        <div class="table-responsive">
            <table class="table table-sm table-bordered format-sheet-table mb-0" style="max-width: 28rem;">
                <thead>
                    <tr><th>Due Date</th><th>Amount</th></tr>
                </thead>
                <tbody>
                <?php foreach ($summarySchedule as $s): ?>
                    <?php
                    $dueTs = strtotime((string) $s['due_date']);
                    $dueDisp = $dueTs ? date('m/d/y', $dueTs) : h((string) $s['due_date']);
                    $installment = (float) $s['monthly_principal'] + (float) $s['interest_display'];
                    ?>
                    <tr>
                        <td><?= h($dueDisp) ?></td>
                        <td class="text-end">₱<?= number_format($installment, 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <p class="small text-muted mb-0 mt-2">Installment = principal + allocated interest for that month. Penalties (if any) apply on overdue periods only.</p>
    </div>
</div>
<?php endif; ?>

<?php if (!$current): ?>
    <div class="alert alert-secondary">No bills to pay.</div>
<?php else: ?>
    <div class="card p-4 mb-4">
        <h2 class="h6 text-muted">Current billing (this period)</h2>
        <p class="small mb-2">Date generated: <?= h(format_sheet_date($current['date_generated'] ?? null)) ?> · Due: <?= h(format_sheet_date($current['due_date'] ?? null)) ?> · Status: <?= h($current['status']) ?></p>
        <div class="table-responsive">
            <table class="table table-sm">
                <tbody>
                    <tr><th>Borrower</th><td><?= h($u['name']) ?></td></tr>
                    <tr><th>Account type</th><td><?= h($u['account_type']) ?></td></tr>
                    <tr><th>Loaned amount</th><td>₱<?= number_format((float) $current['loaned_amount'], 2) ?></td></tr>
                    <tr><th>Received amount</th><td>₱<?= number_format((float) $current['received_amount'], 2) ?></td></tr>
                    <tr><th>Amount for this month (principal)</th><td>₱<?= number_format((float) $current['monthly_principal'], 2) ?></td></tr>
                    <tr><th>Interest (3% total, allocated)</th><td>₱<?= number_format((float) $current['interest_display'], 2) ?></td></tr>
                    <tr><th>Penalty (2% on missed month)</th><td>₱<?= number_format((float) $current['penalty_amount'], 2) ?> (<?= h((string) $current['penalty_percent']) ?>%)</td></tr>
                    <tr><th>Total due</th><td><strong>₱<?= number_format((float) $current['total_due'], 2) ?></strong></td></tr>
                </tbody>
            </table>
        </div>
        <form method="post" onsubmit="return confirm('Record payment for this period?');">
            <input type="hidden" name="pay_billing" value="1">
            <input type="hidden" name="billing_id" value="<?= (int) $current['id'] ?>">
            <button type="submit" class="btn btn-success">Pay now (simulate)</button>
        </form>
    </div>
<?php endif; ?>

<div class="card p-4">
    <h2 class="h6">Billing history</h2>
    <p class="small text-muted">Sorted by year, then month. Select a month to see details.</p>
    <ul class="list-unstyled">
        <?php
        $byYear = [];
        foreach ($grouped as $g) {
            $byYear[(int) $g['period_year']][] = (int) $g['period_month'];
        }
        krsort($byYear);
        foreach ($byYear as $y => $months):
            rsort($months);
        ?>
        <li class="mb-2"><strong><?= $y ?></strong>
            <ul>
                <?php foreach ($months as $m): ?>
                <li><a href="<?= h(app_url('billing.php?y=' . $y . '&m=' . $m)) ?>"><?= date('F', mktime(0, 0, 0, $m, 1)) ?></a></li>
                <?php endforeach; ?>
            </ul>
        </li>
        <?php endforeach; ?>
        <?php if ($grouped === []): ?>
            <li>No billing history yet.</li>
        <?php endif; ?>
    </ul>
</div>

<?php if ($yearFilter && $monthFilter && $detailRows !== []): ?>
<div class="card p-4 mt-3">
    <h2 class="h6"><?= h((string) $monthFilter) ?>/<?= h((string) $yearFilter) ?> — details</h2>
    <div class="table-responsive">
        <table class="table table-sm">
            <thead><tr><th>Due</th><th>Principal</th><th>Interest</th><th>Penalty</th><th>Total</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($detailRows as $r): ?>
                <tr>
                    <td><?= h($r['due_date']) ?></td>
                    <td>₱<?= number_format((float) $r['monthly_principal'], 2) ?></td>
                    <td>₱<?= number_format((float) $r['interest_display'], 2) ?></td>
                    <td>₱<?= number_format((float) $r['penalty_amount'], 2) ?></td>
                    <td>₱<?= number_format((float) $r['total_due'], 2) ?></td>
                    <td><?= h($r['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php render_footer();
