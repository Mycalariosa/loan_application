<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/savings_balance.php';

$u = require_approved_user();
if (($u['role'] ?? '') === 'admin' || $u['account_type'] !== 'premium') {
    flash_set('error', 'Savings are available for Premium accounts only.');
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$pdo = db();
$uid = (int) $u['id'];
$errors = [];

$balance = get_savings_balance($pdo, $uid);

$cat = $_GET['cat'] ?? '';
$searchId = trim((string) ($_GET['q'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $amt = (float) ($_POST['amount'] ?? 0);
    if ($amt < SAVINGS_MIN_TXN || $amt > SAVINGS_MAX_DEPOSIT_TXN) {
        $errors[] = 'Deposit must be between ₱' . SAVINGS_MIN_TXN . ' and ₱' . SAVINGS_MAX_DEPOSIT_TXN . '.';
    }
    if ($balance + $amt > SAVINGS_MAX_BALANCE + 0.01) {
        $errors[] = 'Savings cannot exceed ₱' . number_format(SAVINGS_MAX_BALANCE) . '.';
    }
    if ($errors === []) {
        $pdo->beginTransaction();
        try {
            $newBal = $balance + $amt;
            set_savings_balance($pdo, $uid, $newBal);
            $pdo->prepare('UPDATE users SET savings_last_nonzero_at = NOW() WHERE id = ?')->execute([$uid]);
            $txnNo = next_savings_txn_no($pdo, $uid);
            $tid = random_txn_id('SV');
            $pdo->prepare(
                'INSERT INTO savings_transactions (user_id, txn_no, transaction_id, category, amount, status)
                 VALUES (?,?,?,?,?,\'completed\')'
            )->execute([$uid, $txnNo, $tid, 'deposit', $amt]);
            $pdo->commit();
            flash_set('ok', 'Deposit completed.');
            header('Location: ' . app_url('savings.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw_req'])) {
    $amt = (float) ($_POST['wamount'] ?? 0);
    $cnt = savings_withdrawals_today($pdo, $uid);
    $dayAmt = savings_withdrawal_amount_today($pdo, $uid);
    if ($cnt >= SAVINGS_WITHDRAW_MAX_PER_DAY) {
        $errors[] = 'Maximum ' . SAVINGS_WITHDRAW_MAX_PER_DAY . ' withdrawal requests per day.';
    }
    if ($amt < SAVINGS_WITHDRAW_MIN || $amt > SAVINGS_WITHDRAW_MAX) {
        $errors[] = 'Withdrawal must be between ₱' . SAVINGS_WITHDRAW_MIN . ' and ₱' . SAVINGS_WITHDRAW_MAX . '.';
    }
    if ($amt - $balance > 0.01) {
        $errors[] = 'Insufficient savings balance.';
    }
    if ($dayAmt + $amt - SAVINGS_WITHDRAW_MAX > 0.01) {
        $errors[] = 'Total withdrawals today cannot exceed ₱' . SAVINGS_WITHDRAW_MAX . '.';
    }
    if ($errors === []) {
        $txnNo = next_savings_txn_no($pdo, $uid);
        $tid = random_txn_id('SV');
        $pdo->prepare(
            'INSERT INTO savings_transactions (user_id, txn_no, transaction_id, category, amount, status)
             VALUES (?,?,?,?,?,\'pending\')'
        )->execute([$uid, $txnNo, $tid, 'withdrawal', $amt]);
        flash_set('ok', 'Withdrawal request submitted. An administrator will review it.');
        header('Location: ' . app_url('savings.php'));
        exit;
    }
}

$st = $pdo->prepare('SELECT * FROM savings_transactions WHERE user_id = ? ORDER BY id ASC');
$st->execute([$uid]);
$allWithRun = savings_transactions_with_running_balance($st->fetchAll());

$filtered = [];
foreach ($allWithRun as $r) {
    $c = (string) ($r['category'] ?? '');
    if ($cat === 'deposit' && $c !== 'deposit') {
        continue;
    }
    if ($cat === 'withdrawal' && $c !== 'withdrawal') {
        continue;
    }
    if ($cat === 'interest' && $c !== 'interest_earned') {
        continue;
    }
    if ($searchId !== '' && stripos((string) ($r['transaction_id'] ?? ''), $searchId) === false) {
        continue;
    }
    $filtered[] = $r;
}

$balance = get_savings_balance($pdo, $uid);

render_header('Savings', $u);
flash_alert();
foreach ($errors as $e) {
    echo '<div class="alert alert-danger">' . h($e) . '</div>';
}
?>
<div class="row g-4">
    <div class="col-md-4">
        <div class="card p-3 mb-3">
            <h2 class="h6 text-muted">Balance</h2>
            <p class="h4 mb-0">₱<?= number_format($balance, 2) ?></p>
            <p class="small text-muted mb-0">Max balance ₱<?= number_format(SAVINGS_MAX_BALANCE, 0) ?></p>
        </div>
        <div class="card p-3 mb-3">
            <h2 class="h6">Deposit</h2>
            <p class="small">Min ₱<?= SAVINGS_MIN_TXN ?> · Max ₱<?= SAVINGS_MAX_DEPOSIT_TXN ?> per transaction</p>
            <form method="post">
                <input type="hidden" name="deposit" value="1">
                <div class="mb-2">
                    <input type="number" name="amount" class="form-control" step="0.01" min="<?= SAVINGS_MIN_TXN ?>" max="<?= SAVINGS_MAX_DEPOSIT_TXN ?>" required>
                </div>
                <button class="btn btn-primary btn-sm" type="submit">Deposit</button>
            </form>
        </div>
        <div class="card p-3">
            <h2 class="h6">Withdraw (request)</h2>
            <p class="small">Requires admin approval. Max <?= SAVINGS_WITHDRAW_MAX_PER_DAY ?> requests/day; ₱<?= SAVINGS_WITHDRAW_MIN ?>–₱<?= SAVINGS_WITHDRAW_MAX ?> per request; ₱<?= SAVINGS_WITHDRAW_MAX ?> total per day.</p>
            <form method="post">
                <input type="hidden" name="withdraw_req" value="1">
                <div class="mb-2">
                    <input type="number" name="wamount" class="form-control" step="0.01" min="<?= SAVINGS_WITHDRAW_MIN ?>" max="<?= min(SAVINGS_WITHDRAW_MAX, $balance) ?>" required>
                </div>
                <button class="btn btn-outline-primary btn-sm" type="submit">Request withdrawal</button>
            </form>
        </div>
    </div>
    <div class="col-md-8">
        <div class="format-sheet-section card p-0 overflow-hidden">
            <div class="format-sheet-title">Savings Transactions</div>
            <form class="row g-2 p-3 pb-0" method="get">
                <div class="col-auto">
                    <select name="cat" class="form-select form-select-sm">
                        <option value="">All</option>
                        <option value="deposit" <?= $cat === 'deposit' ? 'selected' : '' ?>>Deposit only</option>
                        <option value="withdrawal" <?= $cat === 'withdrawal' ? 'selected' : '' ?>>Withdrawal only</option>
                        <option value="interest" <?= $cat === 'interest' ? 'selected' : '' ?>>Interest Earned only</option>
                    </select>
                </div>
                <div class="col-auto">
                    <input type="text" name="q" class="form-control form-control-sm" placeholder="Transaction ID" value="<?= h($searchId) ?>">
                </div>
                <div class="col-auto">
                    <button class="btn btn-sm btn-secondary" type="submit">Search</button>
                </div>
            </form>
            <div class="table-responsive px-3 pb-3">
                <table class="table table-sm table-bordered format-sheet-table mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Date</th>
                            <th>Transaction ID</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Current Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($filtered as $t): ?>
                        <tr>
                            <td><?= (int) $t['txn_no'] ?></td>
                            <td><?= h(format_sheet_date($t['created_at'] ?? null)) ?></td>
                            <td><?= h($t['transaction_id']) ?></td>
                            <td><?= h(savings_category_label((string) $t['category'])) ?></td>
                            <td>₱<?= number_format((float) $t['amount'], 2) ?></td>
                            <td>₱<?= number_format((float) ($t['current_amount'] ?? 0), 2) ?></td>
                            <td><?= h(ucfirst((string) $t['status'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($filtered === []): ?>
                        <tr><td colspan="7" class="text-center text-muted">No transactions.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php render_footer();
