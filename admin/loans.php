<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/loan_service.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lid = (int) ($_POST['loan_id'] ?? 0);
    $st = $pdo->prepare('SELECT l.*, u.name, u.email FROM loans l JOIN users u ON u.id = l.user_id WHERE l.id = ? AND l.status = ?');
    $st->execute([$lid, 'pending']);
    $loan = $st->fetch();
    if (isset($_POST['approve']) && $loan) {
        $pdo->beginTransaction();
        try {
            release_loan_funds($pdo, $lid);
            $pdo->prepare(
                "UPDATE loan_transactions SET status = 'approved' WHERE loan_id = ? AND txn_type = 'new_loan'"
            )->execute([$lid]);
            $pdo->prepare(
                'INSERT INTO notifications (user_id, email_to, subject, body) VALUES (?,?,?,?)'
            )->execute([
                (int) $loan['user_id'],
                $loan['email'],
                'Loan approved',
                'Your loan request was approved. Funds will be sent via bank transfer.',
            ]);
            $pdo->commit();
            flash_set('ok', 'Loan approved and billing schedule created.');
        } catch (Throwable $e) {
            $pdo->rollBack();
            flash_set('error', 'Could not approve loan: ' . $e->getMessage());
        }
    }
    if (isset($_POST['reject']) && $loan) {
        $reason = trim((string) ($_POST['reject_reason'] ?? ''));
        if ($reason === '') {
            flash_set('error', 'Provide a rejection reason.');
        } else {
            $pdo->prepare(
                'UPDATE loans SET status = \'rejected\', admin_reject_reason = ? WHERE id = ?'
            )->execute([$reason, $lid]);
            $pdo->prepare(
                "UPDATE loan_transactions SET status = 'rejected', admin_reject_reason = ? WHERE loan_id = ? AND txn_type = 'new_loan'"
            )->execute([$reason, $lid]);
            $pdo->prepare(
                'INSERT INTO notifications (user_id, email_to, subject, body) VALUES (?,?,?,?)'
            )->execute([
                (int) $loan['user_id'],
                $loan['email'],
                'Loan rejected',
                'Your loan was rejected. Reason: ' . $reason,
            ]);
            flash_set('ok', 'Loan rejected.');
        }
    }
    header('Location: ' . app_url('admin/loans.php'));
    exit;
}

$list = $pdo->query(
    'SELECT l.*, u.name, u.email, u.username FROM loans l JOIN users u ON u.id = l.user_id WHERE l.status = \'pending\' ORDER BY l.created_at ASC'
)->fetchAll();

render_header('Loan Management', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Loan Management</h1>
        <p class="text-gray-600">Review and approve pending loan applications.</p>
    </div>

    <?php if ($list === []): ?>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Pending Loans</h3>
                <p class="text-gray-600">All loan applications have been processed.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($list as $loan): ?>
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
                <div class="p-8">
                    <div class="flex items-start justify-between mb-6">
                        <div>
                            <h3 class="text-xl font-bold text-gray-900 mb-2"><?= h($loan['name']) ?></h3>
                            <div class="flex items-center gap-4 text-sm text-gray-600">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    <?= h($loan['username']) ?>
                                </span>
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <?= h($loan['email']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            Applied: <?= h(format_sheet_date($loan['created_at'])) ?>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-3 gap-6 mb-6">
                        <div class="bg-blue-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-blue-900 mb-2">Loan Amount</h4>
                            <p class="text-2xl font-bold text-blue-600">₱<?= number_format((float) $loan['requested_amount'], 2) ?></p>
                        </div>
                        <div class="bg-green-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-green-900 mb-2">Received Amount</h4>
                            <p class="text-2xl font-bold text-green-600">₱<?= number_format((float) $loan['received_amount'], 2) ?></p>
                            <p class="text-xs text-green-700 mt-1">After 3% interest</p>
                        </div>
                        <div class="bg-purple-50 rounded-lg p-4">
                            <h4 class="text-sm font-semibold text-purple-900 mb-2">Payment Term</h4>
                            <p class="text-2xl font-bold text-purple-600"><?= (int) $loan['term_months'] ?> months</p>
                        </div>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h4 class="text-sm font-semibold text-gray-900 mb-3">Loan Details</h4>
                        <div class="grid md:grid-cols-2 gap-4 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Interest Rate:</span>
                                <span class="font-medium">3% (₱<?= number_format((float) $loan['interest_amount'], 2) ?>)</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Monthly Installment:</span>
                                <span class="font-medium">₱<?= number_format(((float) $loan['received_amount'] + (float) $loan['interest_amount']) / (int) $loan['term_months'], 2) ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Status:</span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    Pending
                                </span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Transaction ID:</span>
                                <span class="font-medium font-mono"><?= h($loan['transaction_id'] ?? 'Processing...') ?></span>
                            </div>
                        </div>
                    </div>

                    <form method="post" class="space-y-4">
                        <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Rejection Reason (required if rejecting)</label>
                            <input type="text" name="reject_reason" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Enter reason if rejecting this loan">
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="submit" name="approve" 
                                    class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Approve & Release Funds
                            </button>
                            <button type="submit" name="reject" 
                                    onclick="return confirm('Are you sure you want to reject this loan application?');"
                                    class="bg-red-600 hover:bg-red-700 text-white font-semibold py-3 px-6 rounded-lg transition">
                                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                Reject Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php render_footer();
