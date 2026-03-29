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

render_header('Loans', $u);
flash_alert();
?>
<h1 class="h4 mb-3">Loan approvals</h1>
<?php foreach ($list as $loan): ?>
<div class="card mb-3 p-3">
    <p><strong><?= h($loan['name']) ?></strong> (<?= h($loan['username']) ?>) · <?= h($loan['email']) ?></p>
    <p class="mb-1">Amount: ₱<?= number_format((float) $loan['requested_amount'], 2) ?> · Received (after 3%): ₱<?= number_format((float) $loan['received_amount'], 2) ?> · Term: <?= (int) $loan['term_months'] ?> mo</p>
    <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="loan_id" value="<?= (int) $loan['id'] ?>">
        <div class="col-md-6">
            <label class="form-label small">Rejection reason (required to reject)</label>
            <input type="text" name="reject_reason" class="form-control form-control-sm" placeholder="Reason if rejecting">
        </div>
        <div class="col-auto">
            <button type="submit" name="approve" class="btn btn-success btn-sm">Approve &amp; release</button>
        </div>
        <div class="col-auto">
            <button type="submit" name="reject" class="btn btn-outline-danger btn-sm">Reject</button>
        </div>
    </form>
</div>
<?php endforeach; ?>
<?php if ($list === []): ?>
<p class="text-muted">No pending loans.</p>
<?php endif; ?>
<?php render_footer();
