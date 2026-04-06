<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/savings_balance.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tid = (int) ($_POST['txn_db_id'] ?? 0);
    $st = $pdo->prepare(
        "SELECT t.*, u.email, u.name FROM savings_transactions t JOIN users u ON u.id = t.user_id WHERE t.id = ? AND t.category = 'withdrawal' AND t.status = 'pending'"
    );
    $st->execute([$tid]);
    $tx = $st->fetch();
    if (isset($_POST['approve']) && $tx) {
        $uid = (int) $tx['user_id'];
        $amt = (float) $tx['amount'];
        $bal = get_savings_balance($pdo, $uid);
        if ($amt - $bal > 0.01) {
            flash_set('error', 'Insufficient balance for this withdrawal.');
        } else {
            $pdo->beginTransaction();
            try {
                set_savings_balance($pdo, $uid, $bal - $amt);
                $pdo->prepare(
                    "UPDATE savings_transactions SET status = 'completed', processed_at = NOW(), admin_note = ? WHERE id = ?"
                )->execute(['Approved. Funds sent via bank transfer within the day (manual process).', $tid]);
                $pdo->prepare(
                    'INSERT INTO notifications (user_id, email_to, subject, body) VALUES (?,?,?,?)'
                )->execute([
                    $uid,
                    $tx['email'],
                    'Savings withdrawal approved',
                    'Your withdrawal request was approved. Amount: ₱' . number_format($amt, 2),
                ]);
                $pdo->commit();
                flash_set('ok', 'Withdrawal approved.');
            } catch (Throwable $e) {
                $pdo->rollBack();
                flash_set('error', $e->getMessage());
            }
        }
    }
    if (isset($_POST['reject']) && $tx) {
        $note = trim((string) ($_POST['admin_note'] ?? ''));
        $pdo->prepare(
            "UPDATE savings_transactions SET status = 'rejected', admin_note = ?, processed_at = NOW() WHERE id = ?"
        )->execute([$note ?: 'Insufficient balance or policy.', $tid]);
        flash_set('ok', 'Withdrawal rejected.');
    }
    header('Location: ' . app_url('admin/savings.php'));
    exit;
}

$list = $pdo->query(
    'SELECT t.*, u.username, u.name, u.email FROM savings_transactions t JOIN users u ON u.id = t.user_id ORDER BY t.id DESC LIMIT 200'
)->fetchAll();

render_header('Savings Management', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Savings Management</h1>
        <p class="text-gray-600">Process withdrawal requests and monitor all savings transactions.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Savings Transactions (All Users)
            </h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">#</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Transaction ID</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">User</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Category</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Amount</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list as $t): ?>
                        <tr class="border-b border-gray-100 hover:bg-gray-50">
                            <td class="py-4 px-4 text-sm font-medium"><?= (int) $t['txn_no'] ?></td>
                            <td class="py-4 px-4 text-sm font-mono"><?= h($t['transaction_id']) ?></td>
                            <td class="py-4 px-4">
                                <div class="font-medium text-gray-900"><?= h($t['username']) ?></div>
                                <div class="text-sm text-gray-500"><?= h($t['name']) ?></div>
                            </td>
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                    <?php 
                                    $catColor = 'bg-gray-100 text-gray-800';
                                    if ($t['category'] === 'deposit') $catColor = 'bg-green-100 text-green-800';
                                    elseif ($t['category'] === 'withdrawal') $catColor = 'bg-red-100 text-red-800';
                                    echo $catColor;
                                    ?>">
                                    <?= h(savings_category_label((string) $t['category'])) ?>
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="font-semibold <?= $t['category'] === 'deposit' ? 'text-green-600' : 'text-red-600' ?>">
                                    ₱<?= number_format((float) $t['amount'], 2) ?>
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                    <?php 
                                    $statusColor = 'bg-gray-100 text-gray-800';
                                    if ($t['status'] === 'completed') $statusColor = 'bg-green-100 text-green-800';
                                    elseif ($t['status'] === 'pending') $statusColor = 'bg-yellow-100 text-yellow-800';
                                    elseif ($t['status'] === 'rejected') $statusColor = 'bg-red-100 text-red-800';
                                    echo $statusColor;
                                    ?>">
                                    <?= h(ucfirst($t['status'])) ?>
                                </span>
                            </td>
                            <td class="py-4 px-4">
                                <?php if ($t['category'] === 'withdrawal' && $t['status'] === 'pending'): ?>
                                    <div class="flex gap-2">
                                        <form method="post" class="inline">
                                            <input type="hidden" name="txn_db_id" value="<?= (int) $t['id'] ?>">
                                            <button type="submit" name="approve" 
                                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                                                Approve
                                            </button>
                                        </form>
                                        <form method="post" class="inline-flex gap-2">
                                            <input type="hidden" name="txn_db_id" value="<?= (int) $t['id'] ?>">
                                            <input type="text" name="admin_note" 
                                                   class="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm" 
                                                   placeholder="Reject reason">
                                            <button type="submit" name="reject" 
                                                    class="bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                                                Reject
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="text-sm text-gray-600">
                                        <?= h($t['admin_note'] ?? 'No notes') ?>
                                        <?php if ($t['processed_at']): ?>
                                        <br><small class="text-gray-400">Processed: <?= h(format_sheet_date($t['processed_at'])) ?></small>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($list === []): ?>
                <div class="text-center py-8">
                    <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-gray-900 mb-2">No Savings Transactions</h3>
                    <p class="text-gray-600">No savings transactions found in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer();
