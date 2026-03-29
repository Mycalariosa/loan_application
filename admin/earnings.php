<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money_back.php';

$u = require_admin();
$pdo = db();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute'])) {
    $year = (int) ($_POST['year'] ?? 0);
    $income = (float) ($_POST['total_income'] ?? 0);
    if ($year < 2000 || $year > 2100 || $income <= 0) {
        $errors[] = 'Enter a valid year and positive total income.';
    }
    $chk = $pdo->prepare('SELECT COUNT(*) FROM money_back_transactions WHERE year_year = ?');
    $chk->execute([$year]);
    if ((int) $chk->fetchColumn() > 0) {
        $errors[] = 'Money back for this year was already distributed (see records).';
    }
    if ($errors === []) {
        try {
            distribute_money_back($pdo, $year, $income);
            flash_set('ok', 'Company earnings saved and money back distributed to Premium members (2% pool / head).');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }
        header('Location: ' . app_url('admin/earnings.php'));
        exit;
    }
}

$earnings = $pdo->query('SELECT * FROM company_earnings ORDER BY year_year DESC')->fetchAll();
$mb = $pdo->query('SELECT * FROM money_back_transactions ORDER BY id DESC LIMIT 50')->fetchAll();

render_header('Company earnings', $u);
flash_alert();
foreach ($errors as $e) {
    echo '<div class="alert alert-danger">' . h($e) . '</div>';
}
?>
<h1 class="h4 mb-3">Total earnings of the company</h1>
<p class="text-muted small">Formula per member: (Total income for the year × 2%) ÷ (number of Premium members). Credited to each Premium member’s savings.</p>

<div class="card p-4 mb-4">
    <h2 class="h6">Record year &amp; distribute money back</h2>
    <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="distribute" value="1">
        <div class="col-auto">
            <label class="form-label">Year</label>
            <input type="number" name="year" class="form-control" value="<?= (int) date('Y') ?>" required>
        </div>
        <div class="col-auto">
            <label class="form-label">Total company income (₱)</label>
            <input type="number" name="total_income" class="form-control" step="0.01" min="1" required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Save &amp; distribute</button>
        </div>
    </form>
</div>

<h2 class="h6">Recorded earnings</h2>
<table class="table table-sm"><thead><tr><th>Year</th><th>Total income</th></tr></thead><tbody>
<?php foreach ($earnings as $e): ?>
<tr><td><?= (int) $e['year_year'] ?></td><td>₱<?= number_format((float) $e['total_income'], 2) ?></td></tr>
<?php endforeach; ?>
</tbody></table>

<h2 class="h6 mt-4">Recent money back transactions</h2>
<table class="table table-sm"><thead><tr><th>Year</th><th>User</th><th>Amount</th><th>Txn ID</th></tr></thead><tbody>
<?php foreach ($mb as $m): ?>
<tr>
    <td><?= (int) $m['year_year'] ?></td>
    <td><?= (int) $m['user_id'] ?></td>
    <td>₱<?= number_format((float) $m['amount'], 2) ?></td>
    <td><small><?= h($m['transaction_id']) ?></small></td>
</tr>
<?php endforeach; ?>
</tbody></table>
<?php render_footer();
