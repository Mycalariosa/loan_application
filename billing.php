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

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Billing & Payments</h1>
        <p class="text-gray-600">View your billing statements, payment schedules, and payment history.</p>
    </div>

    <?php if ($summaryLoan && $summarySchedule !== []): ?>
    <!-- Billing Summary -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 mb-8">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                Billing Summary
            </h2>
            
            <div class="grid md:grid-cols-3 gap-6 mb-8">
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Loan Amount</h3>
                    <p class="text-2xl font-bold text-brand">₱<?= number_format((float) $summaryLoan['requested_amount'], 2) ?></p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Interest (3%)</h3>
                    <p class="text-2xl font-bold text-red-600">₱<?= number_format((float) $summaryLoan['interest_amount'], 2) ?></p>
                </div>
                <div class="bg-green-50 rounded-lg p-4">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">Total Received</h3>
                    <p class="text-2xl font-bold text-green-600">₱<?= number_format((float) $summaryLoan['received_amount'], 2) ?></p>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Monthly Payment Schedule</h3>
                <p class="text-sm text-gray-600 mb-4">Payment Schedule (<?= (int) $summaryLoan['term_months'] ?> months)</p>
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Due Date</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Installment Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($summarySchedule as $s): ?>
                                <?php
                                $dueTs = strtotime((string) $s['due_date']);
                                $dueDisp = $dueTs ? date('m/d/y', $dueTs) : h((string) $s['due_date']);
                                $installment = (float) $s['monthly_principal'] + (float) $s['interest_display'];
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm"><?= h($dueDisp) ?></td>
                                    <td class="py-3 px-4 text-sm font-semibold">₱<?= number_format($installment, 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="text-sm text-gray-500 mt-4">
                    <strong>Note:</strong> Installment = principal + allocated interest for that month. Penalties (if any) apply on overdue periods only.
                </p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!$current): ?>
        <!-- No Bills -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Bills to Pay</h3>
                <p class="text-gray-600">You don't have any outstanding bills at this time.</p>
            </div>
        </div>
    <?php else: ?>
        <!-- Current Billing -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 mb-8">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Current Billing Period
                </h2>
                
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Billing Information</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Date Generated:</span>
                                <span class="font-medium"><?= h(format_sheet_date($current['date_generated'] ?? null)) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Due Date:</span>
                                <span class="font-medium"><?= h(format_sheet_date($current['due_date'] ?? null)) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                    <?php 
                                    $statusColor = 'bg-gray-100 text-gray-800';
                                    if ($current['status'] === 'pending') $statusColor = 'bg-yellow-100 text-yellow-800';
                                    elseif ($current['status'] === 'overdue') $statusColor = 'bg-red-100 text-red-800';
                                    elseif ($current['status'] === 'paid') $statusColor = 'bg-green-100 text-green-800';
                                    echo $statusColor;
                                    ?>">
                                    <?= h(ucfirst($current['status'])) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Details</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Borrower:</span>
                                <span class="font-medium"><?= h($u['name']) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Account Type:</span>
                                <span class="font-medium"><?= h(ucfirst($u['account_type'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Payment Breakdown</h3>
                    <div class="bg-gray-50 rounded-lg p-6">
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Loaned Amount:</span>
                                <span class="font-medium">₱<?= number_format((float) $current['loaned_amount'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Received Amount:</span>
                                <span class="font-medium">₱<?= number_format((float) $current['received_amount'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Monthly Principal:</span>
                                <span class="font-medium">₱<?= number_format((float) $current['monthly_principal'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Interest (3%):</span>
                                <span class="font-medium">₱<?= number_format((float) $current['interest_display'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Penalty (2%):</span>
                                <span class="font-medium">₱<?= number_format((float) $current['penalty_amount'], 2) ?> (<?= h((string) $current['penalty_percent']) ?>%)</span>
                            </div>
                            <div class="border-t border-gray-300 pt-3 mt-3">
                                <div class="flex justify-between">
                                    <span class="text-lg font-semibold text-gray-900">Total Due:</span>
                                    <span class="text-lg font-bold text-red-600">₱<?= number_format((float) $current['total_due'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-8">
                    <form method="post" onsubmit="return confirm('Record payment for this period?');" class="inline-block">
                        <input type="hidden" name="pay_billing" value="1">
                        <input type="hidden" name="billing_id" value="<?= (int) $current['id'] ?>">
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-8 rounded-lg transition">
                            <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            Pay Now (Simulated)
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Billing History -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                <svg class="w-8 h-8 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Billing History
            </h2>
            
            <p class="text-gray-600 mb-6">Browse your billing history by year and month. Select a month to view detailed information.</p>
            
            <div class="grid md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">History by Year</h3>
                    <div class="space-y-4">
                        <?php
                        $byYear = [];
                        foreach ($grouped as $g) {
                            $byYear[(int) $g['period_year']][] = (int) $g['period_month'];
                        }
                        krsort($byYear);
                        foreach ($byYear as $y => $months):
                            rsort($months);
                        ?>
                        <div class="border border-gray-200 rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-2"><?= $y ?></h4>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($months as $m): ?>
                                <a href="<?= h(app_url('billing.php?y=' . $y . '&m=' . $m)) ?>" 
                                   class="inline-flex items-center px-3 py-1 rounded-lg text-sm font-medium bg-gray-100 hover:bg-gray-200 text-gray-700 transition">
                                    <?= date('M', mktime(0, 0, 0, $m, 1)) ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if ($grouped === []): ?>
                            <div class="text-center py-8 text-gray-500">
                                <svg class="w-12 h-12 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                <p>No billing history yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($yearFilter && $monthFilter && $detailRows !== []): ?>
                <div>
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">
                        <?= date('F Y', mktime(0, 0, 0, (int) $monthFilter, 1, (int) $yearFilter)) ?> — Details
                    </h3>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="border-b border-gray-200">
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Due Date</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Principal</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Interest</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Penalty</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Total</th>
                                    <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($detailRows as $r): ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm"><?= h($r['due_date']) ?></td>
                                    <td class="py-3 px-4 text-sm">₱<?= number_format((float) $r['monthly_principal'], 2) ?></td>
                                    <td class="py-3 px-4 text-sm">₱<?= number_format((float) $r['interest_display'], 2) ?></td>
                                    <td class="py-3 px-4 text-sm">₱<?= number_format((float) $r['penalty_amount'], 2) ?></td>
                                    <td class="py-3 px-4 text-sm font-semibold">₱<?= number_format((float) $r['total_due'], 2) ?></td>
                                    <td class="py-3 px-4 text-sm">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                            <?php 
                                            $statusColor = 'bg-gray-100 text-gray-800';
                                            if ($r['status'] === 'pending') $statusColor = 'bg-yellow-100 text-yellow-800';
                                            elseif ($r['status'] === 'overdue') $statusColor = 'bg-red-100 text-red-800';
                                            elseif ($r['status'] === 'paid') $statusColor = 'bg-green-100 text-green-800';
                                            echo $statusColor;
                                            ?>">
                                            <?= h(ucfirst($r['status'])) ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php render_footer();
