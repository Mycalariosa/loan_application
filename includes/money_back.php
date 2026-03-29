<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/savings_balance.php';

/** Distribute 2% of annual company income equally among Premium users with active accounts */
function distribute_money_back(PDO $pdo, int $year, float $totalIncome): void
{
    $pdo->beginTransaction();
    try {
        $pdo->prepare('INSERT INTO company_earnings (year_year, total_income) VALUES (?,?)
         ON DUPLICATE KEY UPDATE total_income = VALUES(total_income)')->execute([$year, $totalIncome]);

        $st = $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'user' AND account_type = 'premium' AND registration_status = 'approved' AND account_status = 'active'"
        );
        $n = (int) $st->fetchColumn();
        if ($n === 0) {
            $pdo->commit();
            return;
        }
        $pool = round($totalIncome * (MONEY_BACK_PERCENT / 100.0), 2);
        $each = round($pool / $n, 2);

        $users = $pdo->query(
            "SELECT id FROM users WHERE role = 'user' AND account_type = 'premium' AND registration_status = 'approved' AND account_status = 'active'"
        )->fetchAll(PDO::FETCH_COLUMN);

        foreach ($users as $uid) {
            $uid = (int) $uid;
            $bal = get_savings_balance($pdo, $uid);
            set_savings_balance($pdo, $uid, $bal + $each);
            $pdo->prepare('UPDATE users SET savings_last_nonzero_at = NOW() WHERE id = ?')->execute([$uid]);
            $tid = random_txn_id('MB');
            $txnNo = next_savings_txn_no($pdo, $uid);
            $pdo->prepare(
                'INSERT INTO savings_transactions (user_id, txn_no, transaction_id, category, amount, status)
                 VALUES (?,?,?,?,?,\'completed\')'
            )->execute([$uid, $txnNo, $tid, 'interest_earned', $each]);
            $pdo->prepare(
                'INSERT INTO money_back_transactions (year_year, user_id, amount, transaction_id) VALUES (?,?,?,?)'
            )->execute([$year, $uid, $each, $tid]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
