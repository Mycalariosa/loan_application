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

render_header('Savings (all)', $u);
flash_alert();
?>
<h1 class="h4 mb-3">Savings transactions (all users)</h1>
<div class="table-responsive">
    <table class="table table-sm table-striped">
        <thead><tr><th>No.</th><th>Txn ID</th><th>User</th><th>Category</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($list as $t): ?>
            <tr>
                <td><?= (int) $t['txn_no'] ?></td>
                <td><small><?= h($t['transaction_id']) ?></small></td>
                <td><?= h($t['username']) ?></td>
                <td><?= h(savings_category_label((string) $t['category'])) ?></td>
                <td>₱<?= number_format((float) $t['amount'], 2) ?></td>
                <td><?= h($t['status']) ?></td>
                <td>
                    <?php if ($t['category'] === 'withdrawal' && $t['status'] === 'pending'): ?>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="txn_db_id" value="<?= (int) $t['id'] ?>">
                        <button type="submit" name="approve" class="btn btn-sm btn-success">Approve</button>
                    </form>
                    <form method="post" class="d-inline">
                        <input type="hidden" name="txn_db_id" value="<?= (int) $t['id'] ?>">
                        <input type="text" name="admin_note" class="form-control form-control-sm d-inline w-auto" placeholder="Reject note">
                        <button type="submit" name="reject" class="btn btn-sm btn-outline-danger">Reject</button>
                    </form>
                    <?php else: ?>
                    <small><?= h($t['admin_note'] ?? '') ?></small>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer();
