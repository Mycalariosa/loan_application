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
    echo '<div class="alert alert-danger">' . h($e) . '</div>';
}
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card p-4">
            <h2 class="h5">Apply for a loan</h2>
            <p class="small text-muted">3% interest is calculated on the full borrowed amount and deducted immediately (you receive the net amount). Terms: 1 / 3 / 6 / 12 months.</p>
            <p class="small mb-2">Ceiling: ₱<?= number_format($ceiling, 2) ?> · Outstanding: ₱<?= number_format($outstanding, 2) ?> · Remaining: ₱<?= number_format($remaining, 2) ?></p>
            <?php if ($remaining < LOAN_MIN_AMOUNT): ?>
                <div class="alert alert-warning">You have reached your loan limit or have insufficient remaining headroom for a new loan.</div>
            <?php else: ?>
            <form method="post" class="vstack gap-3">
                <input type="hidden" name="apply_loan" value="1">
                <div>
                    <label class="form-label">Amount (thousands only, min ₱5,000 — max ₱<?= number_format($maxSingle, 0) ?> this application)</label>
                    <select name="amount" class="form-select" required>
                        <?php
                        for ($a = LOAN_MIN_AMOUNT; $a <= min(LOAN_MAX_CEILING, $maxSingle) + 0.1; $a += 1000) {
                            if ($a - $maxSingle > 0.01) {
                                break;
                            }
                            echo '<option value="' . $a . '">₱' . number_format($a) . '</option>';
                        }
                        ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Payable in</label>
                    <select name="term_months" class="form-select" required>
                        <?php foreach (allowed_terms_for_user((int) $u['max_loan_term_months']) as $t): ?>
                        <option value="<?= $t ?>"><?= $t ?> month(s)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary">Submit application</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="format-sheet-section card p-0 overflow-hidden">
            <div class="format-sheet-title">Loan Transactions</div>
            <div class="table-responsive p-3">
                <table class="table table-sm table-bordered format-sheet-table mb-0">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Date</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Note</th>
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
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= h(format_sheet_date($dt)) ?></td>
                            <td><?= h($row['txn_id'] ?? '—') ?></td>
                            <td>₱<?= number_format((float) $row['requested_amount'], 2) ?></td>
                            <td><?= h(ucfirst($stDisp)) ?></td>
                            <td class="small"><?= $note !== '' ? h($note) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($loans === []): ?>
                        <tr><td colspan="6" class="text-center text-muted">No loans yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-muted px-3 pb-3 mb-0">Term / received amounts are fixed when the loan is approved. See Billing for repayment schedule.</p>
        </div>
    </div>
</div>
<?php render_footer();
