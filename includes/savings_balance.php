<?php
declare(strict_types=1);

function get_savings_balance(PDO $pdo, int $userId): float
{
    $st = $pdo->prepare('SELECT balance, zero_since FROM savings_accounts WHERE user_id = ?');
    $st->execute([$userId]);
    $r = $st->fetch();
    return $r ? (float) $r['balance'] : 0.0;
}

/** Set balance after deposit/withdraw; tracks zero_since for 3-month Premium downgrade rule */
function set_savings_balance(PDO $pdo, int $userId, float $newBalance): void
{
    if ($newBalance > SAVINGS_MAX_BALANCE) {
        throw new RuntimeException('Savings cannot exceed maximum.');
    }
    $newBalance = round($newBalance, 2);
    $st = $pdo->prepare('SELECT balance, zero_since FROM savings_accounts WHERE user_id = ? FOR UPDATE');
    $st->execute([$userId]);
    $row = $st->fetch();
    $old = $row ? (float) $row['balance'] : 0.0;
    $zs = $row['zero_since'] ?? null;

    if ($newBalance > 0) {
        $zs = null;
    } elseif ($old > 0 && $newBalance <= 0) {
        $zs = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
    } elseif (!$row) {
        $zs = null;
    }

    if (!$row) {
        $pdo->prepare('INSERT INTO savings_accounts (user_id, balance, zero_since) VALUES (?, ?, ?)')
            ->execute([$userId, $newBalance, $zs]);
    } else {
        $pdo->prepare('UPDATE savings_accounts SET balance = ?, zero_since = ? WHERE user_id = ?')
            ->execute([$newBalance, $zs, $userId]);
    }
}
