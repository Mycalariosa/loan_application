<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$u = require_admin();
$pdo = db();

$pendingReg = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND registration_status='pending'")->fetchColumn();
$pendingLoans = (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status='pending'")->fetchColumn();
$pendingSav = (int) $pdo->query("SELECT COUNT(*) FROM savings_transactions WHERE status='pending' AND category='withdrawal'")->fetchColumn();

render_header('Admin', $u);
flash_alert();
?>
<h1 class="h4 mb-4">Administration</h1>
<div class="row g-3">
    <div class="col-md-4"><div class="card p-3"><h2 class="h6">Pending registrations</h2><p class="display-6"><?= $pendingReg ?></p><a href="<?= h(app_url('admin/registrations.php')) ?>">Review</a></div></div>
    <div class="col-md-4"><div class="card p-3"><h2 class="h6">Pending loans</h2><p class="display-6"><?= $pendingLoans ?></p><a href="<?= h(app_url('admin/loans.php')) ?>">Review</a></div></div>
    <div class="col-md-4"><div class="card p-3"><h2 class="h6">Pending savings withdrawals</h2><p class="display-6"><?= $pendingSav ?></p><a href="<?= h(app_url('admin/savings.php')) ?>">Review</a></div></div>
</div>
<p class="mt-4 text-muted small">Admins cannot submit loan applications or personal savings deposits/withdrawals from the member UI.</p>
<?php render_footer();
