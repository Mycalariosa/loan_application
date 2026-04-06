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
$ceiling = (float) $u['current_loan_ceiling'];
$outstanding = total_active_principal($pdo, $uid);
$remaining = max(0.0, $ceiling - $outstanding);
$maxSingle = min(10000.0, $remaining);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_loan'])) {
    $amount = (float) ($_POST['amount'] ?? 0);
    $term = (int) ($_POST['term_months'] ?? 0);

    if ($amount < LOAN_MIN_AMOUNT || $amount - $maxSingle > 0.01) {
        $errors[] = 'Amount must be at least ₱' . number_format(LOAN_MIN_AMOUNT) . ' and not more than ₱' . number_format($maxSingle, 2) . ' (remaining under your ceiling), in thousands only.';
    }
    if (!valid_loan_amount_thousands($amount)) {
        $errors[] = 'Loan amount must be in thousands (e.g. 5000, 6000, 10000).';
    }
    $allowedTerms = allowed_terms_for_user((int) $u['max_loan_term_months']);
    if (!in_array($term, $allowedTerms, true)) {
        $errors[] = 'Choose a valid payment term for your account.';
    }
    if ($amount - $remaining > 0.01) {
        $errors[] = 'This loan would exceed your remaining loan limit.';
    }

    if ($errors === []) {
        $interest = round($amount * (LOAN_INTEREST_PERCENT / 100.0), 2);
        $received = round($amount - $interest, 2);
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO loans (user_id, requested_amount, term_months, interest_rate_percent, interest_amount, received_amount, principal_remaining, status)
                 VALUES (?,?,?,?,?,?,?,\'pending\')'
            )->execute([$uid, $amount, $term, LOAN_INTEREST_PERCENT, $interest, $received, $amount]);
            $lid = (int) $pdo->lastInsertId();
            $txnNo = next_loan_txn_no($pdo, $uid);
            $tid = random_txn_id('LN');
            $pdo->prepare(
                'INSERT INTO loan_transactions (user_id, loan_id, txn_no, transaction_id, txn_type, status)
                 VALUES (?,?,?,?,\'new_loan\',\'pending\')'
            )->execute([$uid, $lid, $txnNo, $tid]);
            $pdo->commit();
            flash_set('ok', 'Loan application submitted. Awaiting admin approval.');
            header('Location: ' . app_url('loans.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Could not submit loan. Try again.';
        }
    }
}

$st = $pdo->prepare(
    'SELECT l.*, t.transaction_id AS txn_id, t.created_at AS txn_at, t.status AS txn_status, t.admin_reject_reason AS txn_note
     FROM loans l
     LEFT JOIN loan_transactions t ON t.loan_id = l.id AND t.txn_type = \'new_loan\'
     WHERE l.user_id = ? ORDER BY l.created_at DESC'
);
$st->execute([$uid]);
$loans = $st->fetchAll();

render_header('Loans', $u);
flash_alert();
foreach ($errors as $e) {
    echo '<div class="max-w-6xl mx-auto mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">' . h($e) . '</div>';
}
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Loan Management</h1>
        <p class="text-gray-600">Apply for new loans and track your loan applications.</p>
    </div>

    <!-- Loan Status Overview -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Loan Ceiling</h3>
            <p class="text-2xl font-bold text-brand mb-1">₱<?= number_format($ceiling, 2) ?></p>
            <p class="text-sm text-gray-600">Maximum borrowing limit</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Outstanding</h3>
            <p class="text-2xl font-bold text-red-600 mb-1">₱<?= number_format($outstanding, 2) ?></p>
            <p class="text-sm text-gray-600">Current loan balance</p>
        </div>

        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Available</h3>
            <p class="text-2xl font-bold text-green-600 mb-1">₱<?= number_format($remaining, 2) ?></p>
            <p class="text-sm text-gray-600">Remaining credit limit</p>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Loan Application Form -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Apply for a Loan
                </h2>
                
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex items-start">
                        <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <h3 class="text-sm font-semibold text-blue-800 mb-1">Loan Information</h3>
                            <p class="text-sm text-blue-700">3% interest is calculated on the full borrowed amount and deducted immediately. Terms available: 1, 3, 6, 12 months.</p>
                        </div>
                    </div>
                </div>

                <?php if ($remaining < LOAN_MIN_AMOUNT): ?>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                        <div class="flex items-start">
                            <svg class="w-5 h-5 text-yellow-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            <div>
                                <h3 class="text-sm font-semibold text-yellow-800 mb-1">Loan Limit Reached</h3>
                                <p class="text-sm text-yellow-700">You have reached your loan limit or have insufficient remaining credit for a new loan.</p>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="post" class="space-y-6">
                        <input type="hidden" name="apply_loan" value="1">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="amount">Loan Amount</label>
                            <select id="amount" name="amount" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Select Amount</option>
                                <?php
                                for ($a = LOAN_MIN_AMOUNT; $a <= min(LOAN_MAX_CEILING, $maxSingle) + 0.1; $a += 1000) {
                                    if ($a - $maxSingle > 0.01) {
                                        break;
                                    }
                                    echo '<option value="' . $a . '">₱' . number_format($a) . '</option>';
                                }
                                ?>
                            </select>
                            <p class="text-sm text-gray-500 mt-1">Minimum: ₱5,000 • Maximum: ₱<?= number_format($maxSingle, 0) ?></p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="term_months">Payment Term</label>
                            <select id="term_months" name="term_months" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                                <option value="">Select Term</option>
                                <?php foreach (allowed_terms_for_user((int) $u['max_loan_term_months']) as $t): ?>
                                <option value="<?= $t ?>"><?= $t ?> month(s)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                            Submit Application
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Loan History -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Loan History
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">#</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Date</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Transaction ID</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Amount</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Note</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($loans as $i => $row): ?>
                                <?php
                                $dt = $row['txn_at'] ?? $row['created_at'] ?? null;
                                $note = (string) ($row['txn_note'] ?? $row['admin_reject_reason'] ?? '');
                                $stDisp = (string) ($row['txn_status'] ?? '');
                                if ($stDisp === '') {
                                    $stDisp = (string) ($row['status'] ?? '');
                                }
                                $statusColor = 'text-gray-600';
                                if ($stDisp === 'approved') $statusColor = 'text-green-600';
                                elseif ($stDisp === 'pending') $statusColor = 'text-yellow-600';
                                elseif ($stDisp === 'rejected') $statusColor = 'text-red-600';
                                elseif ($stDisp === 'active') $statusColor = 'text-blue-600';
                                ?>
                                <tr class="border-b border-gray-100 hover:bg-gray-50">
                                    <td class="py-3 px-4 text-sm"><?= $i + 1 ?></td>
                                    <td class="py-3 px-4 text-sm"><?= h(format_sheet_date($dt)) ?></td>
                                    <td class="py-3 px-4 text-sm font-mono"><?= h($row['txn_id'] ?? '—') ?></td>
                                    <td class="py-3 px-4 text-sm font-semibold">₱<?= number_format((float) $row['requested_amount'], 2) ?></td>
                                    <td class="py-3 px-4 text-sm">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?= $statusColor ?> bg-opacity-10">
                                            <?= h(ucfirst($stDisp)) ?>
                                        </span>
                                    </td>
                                    <td class="py-3 px-4 text-sm text-gray-600"><?= $note !== '' ? h($note) : '—' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if ($loans === []): ?>
                                <tr>
                                    <td colspan="6" class="py-8 text-center text-gray-500">No loan applications yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-6 p-4 bg-gray-50 rounded-lg">
                    <p class="text-sm text-gray-600">
                        <strong>Note:</strong> Term and received amounts are fixed when the loan is approved. See Billing page for repayment schedule.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php render_footer();
