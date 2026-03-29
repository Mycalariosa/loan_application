<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $at = $_POST['account_type'] ?? '';
    $as = $_POST['account_status'] ?? '';
    $ceiling = (float) ($_POST['current_loan_ceiling'] ?? LOAN_INITIAL_CEILING);
    $maxTerm = (int) ($_POST['max_loan_term_months'] ?? 12);
    if (!in_array($at, ['basic', 'premium'], true) || !in_array($as, ['active', 'disabled'], true)) {
        flash_set('error', 'Invalid values.');
    } else {
        $st = $pdo->prepare('SELECT account_type FROM users WHERE id = ? AND role = \'user\'');
        $st->execute([$uid]);
        $prev = $st->fetchColumn();
        if ($prev === false) {
            flash_set('error', 'User not found.');
            header('Location: ' . app_url('admin/users.php'));
            exit;
        }
        if ($prev === 'basic' && $at === 'premium' && count_premium_users($pdo) >= MAX_PREMIUM_MEMBERS) {
            flash_set('error', 'Premium member limit reached.');
            header('Location: ' . app_url('admin/users.php'));
            exit;
        }
        $pdo->prepare(
            'UPDATE users SET account_type = ?, account_status = ?, current_loan_ceiling = ?, max_loan_term_months = ? WHERE id = ? AND role = \'user\''
        )->execute([$at, $as, min(LOAN_MAX_CEILING, max(LOAN_INITIAL_CEILING, $ceiling)), min(32, max(12, $maxTerm)), $uid]);
        flash_set('ok', 'User updated.');
    }
    header('Location: ' . app_url('admin/users.php'));
    exit;
}

$users = $pdo->query(
    "SELECT id, username, name, email, account_type, account_status, registration_status, current_loan_ceiling, max_loan_term_months FROM users WHERE role = 'user' ORDER BY id DESC"
)->fetchAll();

render_header('Users', $u);
flash_alert();
?>
<h1 class="h4 mb-3">All users</h1>
<div class="table-responsive">
    <table class="table table-sm">
        <thead><tr><th>ID</th><th>User</th><th>Type</th><th>Status</th><th>Reg.</th><th>Loans / Savings / Billing</th><th>Edit</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <?php
            $uid = (int) $row['id'];
            $c1 = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?');
            $c1->execute([$uid]);
            $lc = (int) $c1->fetchColumn();
            $c2 = $pdo->prepare('SELECT COUNT(*) FROM savings_transactions WHERE user_id = ?');
            $c2->execute([$uid]);
            $sc = (int) $c2->fetchColumn();
            $c3 = $pdo->prepare('SELECT COUNT(*) FROM billing_statements WHERE user_id = ?');
            $c3->execute([$uid]);
            $bc = (int) $c3->fetchColumn();
            ?>
            <tr>
                <td><?= $uid ?></td>
                <td><?= h($row['username']) ?><br><small><?= h($row['email']) ?></small></td>
                <td><?= h($row['account_type']) ?></td>
                <td><?= h($row['account_status']) ?></td>
                <td><?= h($row['registration_status']) ?></td>
                <td><?= $lc ?> / <?= $sc ?> / <?= $bc ?></td>
                <td>
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#u<?= $uid ?>">Edit</button>
                </td>
            </tr>
            <tr class="collapse" id="u<?= $uid ?>">
                <td colspan="7">
                    <form method="post" class="row g-2 align-items-end">
                        <input type="hidden" name="user_id" value="<?= $uid ?>">
                        <div class="col-md-2">
                            <label class="form-label small">Account type</label>
                            <select name="account_type" class="form-select form-select-sm">
                                <option value="basic" <?= $row['account_type'] === 'basic' ? 'selected' : '' ?>>Basic</option>
                                <option value="premium" <?= $row['account_type'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Account status</label>
                            <select name="account_status" class="form-select form-select-sm">
                                <option value="active" <?= $row['account_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="disabled" <?= $row['account_status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Loan ceiling (₱)</label>
                            <input type="number" name="current_loan_ceiling" class="form-control form-control-sm" value="<?= (float) $row['current_loan_ceiling'] ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label small">Max loan term (mo)</label>
                            <input type="number" name="max_loan_term_months" class="form-control form-control-sm" min="12" max="32" value="<?= (int) $row['max_loan_term_months'] ?>">
                        </div>
                        <div class="col-auto">
                            <button type="submit" name="save_user" class="btn btn-primary btn-sm">Save</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php render_footer();
