<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$u = require_approved_user();
if (($u['role'] ?? '') === 'admin') {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

$pdo = db();
$uid = (int) $u['id'];

$st = $pdo->prepare('SELECT COALESCE(SUM(principal_remaining),0) FROM loans WHERE user_id = ? AND status IN (\'active\',\'approved\')');
$st->execute([$uid]);
$outstanding = (float) $st->fetchColumn();

$st = $pdo->prepare('SELECT balance FROM savings_accounts WHERE user_id = ?');
$st->execute([$uid]);
$sav = $st->fetch();
$savingsBal = $sav ? (float) $sav['balance'] : 0.0;

render_header('Dashboard', $u);
flash_alert();
?>
<div class="row g-3">
    <div class="col-md-4">
        <div class="card p-3">
            <h2 class="h6 text-muted">Account</h2>
            <p class="mb-1"><strong><?= h($u['account_type']) ?></strong>
                <?php if ($u['verified_tag']): ?><span class="badge bg-success">Verified</span><?php endif; ?></p>
            <p class="small mb-0">Loan ceiling: ₱<?= number_format((float) $u['current_loan_ceiling'], 2) ?> · Max term: <?= (int) $u['max_loan_term_months'] ?> mo</p>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card p-3">
            <h2 class="h6 text-muted">Outstanding loan principal</h2>
            <p class="h4 mb-0">₱<?= number_format($outstanding, 2) ?></p>
        </div>
    </div>
    <?php if ($u['account_type'] === 'premium'): ?>
    <div class="col-md-4">
        <div class="card p-3">
            <h2 class="h6 text-muted">Savings balance</h2>
            <p class="h4 mb-0">₱<?= number_format($savingsBal, 2) ?></p>
            <a href="<?= h(app_url('savings.php')) ?>">Manage savings</a>
        </div>
    </div>
    <?php endif; ?>
</div>
<div class="mt-4">
    <a class="btn btn-primary" href="<?= h(app_url('loans.php')) ?>">Loans</a>
    <a class="btn btn-outline-primary" href="<?= h(app_url('billing.php')) ?>">Billing</a>
</div>
<?php render_footer();
