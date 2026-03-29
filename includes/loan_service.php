<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

function total_active_principal(PDO $pdo, int $userId): float
{
    $st = $pdo->prepare("SELECT COALESCE(SUM(principal_remaining),0) FROM loans WHERE user_id = ? AND status = 'active'");
    $st->execute([$userId]);
    return (float) $st->fetchColumn();
}

/** When admin releases funds: activate loan and create billing rows */
function release_loan_funds(PDO $pdo, int $loanId): void
{
    $st = $pdo->prepare('SELECT * FROM loans WHERE id = ? FOR UPDATE');
    $st->execute([$loanId]);
    $loan = $st->fetch();
    if (!$loan || !in_array($loan['status'], ['pending', 'approved'], true)) {
        throw new RuntimeException('Loan not approvable.');
    }
    $released = new DateTimeImmutable('now');
    $principal = (float) $loan['requested_amount'];
    $term = (int) $loan['term_months'];
    $interestTotal = (float) $loan['interest_amount'];
    $uid = (int) $loan['user_id'];

    $pdo->prepare(
        "UPDATE loans SET status = 'active', money_released_at = ?, principal_remaining = ? WHERE id = ?"
    )->execute([$released->format('Y-m-d H:i:s'), $principal, $loanId]);

    $monthlyPrincipal = round($principal / $term, 2);
    $monthlyInterestDisplay = round($interestTotal / $term, 2);
    $firstDue = $released->modify('+28 days');

    for ($m = 1; $m <= $term; $m++) {
        $due = $m === 1 ? $firstDue : $firstDue->modify('+' . ($m - 1) . ' months');
        $year = (int) $due->format('Y');
        $month = (int) $due->format('n');
        $totalDue = round($monthlyPrincipal + $monthlyInterestDisplay, 2);
        $pdo->prepare(
            'INSERT INTO billing_statements (loan_id, user_id, period_year, period_month, date_generated, due_date,
             loaned_amount, received_amount, monthly_principal, interest_display, penalty_percent, penalty_amount, total_due, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,0,0,?,\'pending\')'
        )->execute([
            $loanId,
            $uid,
            $year,
            $month,
            $released->format('Y-m-d'),
            $due->format('Y-m-d'),
            $principal,
            (float) $loan['received_amount'],
            $monthlyPrincipal,
            $monthlyInterestDisplay,
            $totalDue,
        ]);
    }
}

/** Mark overdue if past due and not paid — adds 2% penalty on that month only */
function refresh_billing_penalties(PDO $pdo, int $userId): void
{
    $today = new DateTimeImmutable('today');
    $st = $pdo->prepare(
        "SELECT * FROM billing_statements WHERE user_id = ? AND status = 'pending' AND due_date < ?"
    );
    $st->execute([$userId, $today->format('Y-m-d')]);
    foreach ($st->fetchAll() as $row) {
        $base = (float) $row['monthly_principal'] + (float) $row['interest_display'];
        $penalty = round($base * (PENALTY_PERCENT / 100.0), 2);
        $total = round($base + $penalty, 2);
        $pdo->prepare(
            "UPDATE billing_statements SET status = 'overdue', penalty_percent = ?, penalty_amount = ?, total_due = ?
             WHERE id = ? AND status = 'pending'"
        )->execute([PENALTY_PERCENT, $penalty, $total, $row['id']]);
    }
}

function pay_billing_id(PDO $pdo, int $billingId, int $userId): void
{
    $st = $pdo->prepare('SELECT b.*, l.id AS loan_id FROM billing_statements b JOIN loans l ON l.id = b.loan_id WHERE b.id = ? AND b.user_id = ?');
    $st->execute([$billingId, $userId]);
    $b = $st->fetch();
    if (!$b || !in_array($b['status'], ['pending', 'overdue'], true)) {
        throw new RuntimeException('Invalid billing.');
    }
    $pdo->prepare(
        "UPDATE billing_statements SET status = 'completed', paid_at = NOW() WHERE id = ?"
    )->execute([$billingId]);
    $mp = (float) $b['monthly_principal'];
    $pdo->prepare('UPDATE loans SET principal_remaining = GREATEST(0, principal_remaining - ?) WHERE id = ?')
        ->execute([$mp, $b['loan_id']]);
    $st = $pdo->prepare('SELECT principal_remaining FROM loans WHERE id = ?');
    $st->execute([$b['loan_id']]);
    $rem = (float) $st->fetchColumn();
    if ($rem < 0.01) {
        $pdo->prepare("UPDATE loans SET status = 'completed' WHERE id = ?")->execute([$b['loan_id']]);
    }
}
