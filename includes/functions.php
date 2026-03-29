<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

function h(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

function flash_set(string $key, string $message): void
{
    $_SESSION['_flash'][$key] = $message;
}

function flash_get(string $key): ?string
{
    if (!isset($_SESSION['_flash'][$key])) {
        return null;
    }
    $m = $_SESSION['_flash'][$key];
    unset($_SESSION['_flash'][$key]);
    return $m;
}

function random_txn_id(string $prefix = 'TXN'): string
{
    return strtoupper($prefix) . bin2hex(random_bytes(8));
}

function valid_email(string $email): bool
{
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

/** Philippines mobile: +639XXXXXXXXX or 09XXXXXXXXX */
function valid_ph_phone(string $n): bool
{
    $n = preg_replace('/\s+/', '', $n);
    return (bool) preg_match('/^(?:\+63|0)?9\d{9}$/', $n);
}

function normalize_ph_phone(string $n): string
{
    $n = preg_replace('/\s+/', '', $n);
    if (str_starts_with($n, '+63')) {
        return '0' . substr($n, 3);
    }
    return $n;
}

/** Min 8, upper, lower, digit, special */
function valid_password(string $p): bool
{
    if (strlen($p) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $p) || !preg_match('/[a-z]/', $p) || !preg_match('/\d/', $p)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $p)) {
        return false;
    }
    return true;
}

/** Username: at least 6 characters */
function valid_username(string $u): bool
{
    return strlen($u) >= 6;
}

function is_email_blocked(PDO $pdo, string $email): bool
{
    $st = $pdo->prepare('SELECT 1 FROM blocked_emails WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $st->execute([$email]);
    return (bool) $st->fetchColumn();
}

function email_registered(PDO $pdo, string $email): bool
{
    $st = $pdo->prepare('SELECT 1 FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $st->execute([$email]);
    return (bool) $st->fetchColumn();
}

function count_premium_users(PDO $pdo): int
{
    return (int) $pdo->query("SELECT COUNT(*) FROM users WHERE account_type = 'premium' AND role = 'user'")->fetchColumn();
}

function next_savings_txn_no(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT COALESCE(MAX(txn_no), 0) + 1 FROM savings_transactions WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function next_loan_txn_no(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare('SELECT COALESCE(MAX(txn_no), 0) + 1 FROM loan_transactions WHERE user_id = ?');
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

/** Loan amount must be in thousands: 5000, 6000, ... */
function valid_loan_amount_thousands(float $amount): bool
{
    if ($amount < LOAN_MIN_AMOUNT || $amount > LOAN_MAX_CEILING) {
        return false;
    }
    return fmod($amount, 1000.0) < 0.01;
}

function allowed_initial_terms(): array
{
    return [1, 3, 6, 12];
}

/** Terms allowed for this account: 1/3/6/12 plus steps of +3 up to max (cap 32) */
function allowed_terms_for_user(int $maxMonths): array
{
    $opts = [1, 3, 6, 12];
    for ($t = 15; $t <= min(32, $maxMonths); $t += 3) {
        $opts[] = $t;
    }
    $opts = array_values(array_unique(array_filter($opts, static fn ($t) => $t <= $maxMonths)));
    sort($opts);
    return $opts;
}

/** Format sheet date: MM/DD/YY */
function format_sheet_date(?string $mysqlDatetime): string
{
    if ($mysqlDatetime === null || $mysqlDatetime === '') {
        return '—';
    }
    $ts = strtotime($mysqlDatetime);
    if ($ts === false) {
        return '—';
    }
    return date('m/d/y', $ts);
}

function savings_category_label(string $category): string
{
    return match ($category) {
        'withdrawal' => 'Withdrawal',
        'deposit' => 'Deposit',
        'interest_earned' => 'Interest Earned',
        default => $category,
    };
}

/**
 * @param array<int, array<string, mixed>> $rowsAsc Ordered by id ASC
 * @return array<int, array<string, mixed>>
 */
function savings_transactions_with_running_balance(array $rowsAsc): array
{
    $run = 0.0;
    $out = [];
    foreach ($rowsAsc as $r) {
        if (($r['status'] ?? '') === 'completed') {
            $cat = (string) ($r['category'] ?? '');
            $amt = (float) ($r['amount'] ?? 0);
            if ($cat === 'deposit' || $cat === 'interest_earned') {
                $run += $amt;
            } elseif ($cat === 'withdrawal') {
                $run -= $amt;
            }
        }
        $r['current_amount'] = $run;
        $out[] = $r;
    }
    return $out;
}

function calculate_age(string $birthday): int
{
    $b = new DateTimeImmutable($birthday);
    $today = new DateTimeImmutable('today');
    return $b->diff($today)->y;
}

/** On each authenticated request — downgrade Premium if savings balance 0 continuously for 3 months */
function maybe_downgrade_premium_savings(PDO $pdo, array $user): void
{
    if ($user['role'] !== 'user' || $user['account_type'] !== 'premium') {
        return;
    }
    $uid = (int) $user['id'];
    $st = $pdo->prepare('SELECT balance, zero_since FROM savings_accounts WHERE user_id = ?');
    $st->execute([$uid]);
    $row = $st->fetch();
    $balance = $row ? (float) $row['balance'] : 0.0;

    if ($balance > 0) {
        $pdo->prepare('UPDATE users SET savings_last_nonzero_at = NOW() WHERE id = ?')->execute([$uid]);
        return;
    }

    $zs = $row['zero_since'] ?? null;
    if ($zs === null) {
        return;
    }
    $zDt = new DateTimeImmutable((string) $zs);
    if ($zDt->modify('+3 months') <= new DateTimeImmutable('now')) {
        $pdo->prepare("UPDATE users SET account_type = 'basic' WHERE id = ? AND account_type = 'premium'")->execute([$uid]);
    }
}

function savings_withdrawals_today(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare(
        "SELECT COUNT(*) FROM savings_transactions WHERE user_id = ? AND category = 'withdrawal'
         AND DATE(created_at) = CURDATE() AND status IN ('pending','completed')"
    );
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function savings_withdrawal_amount_today(PDO $pdo, int $userId): float
{
    $st = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) FROM savings_transactions WHERE user_id = ? AND category = 'withdrawal'
         AND DATE(created_at) = CURDATE() AND status IN ('pending','completed')"
    );
    $st->execute([$userId]);
    return (float) $st->fetchColumn();
}

function delete_expired_rejected_registrations(PDO $pdo): void
{
    $pdo->exec(
        "DELETE FROM users WHERE role = 'user' AND registration_status = 'rejected'
         AND rejected_at IS NOT NULL AND rejected_at < DATE_SUB(NOW(), INTERVAL 30 DAY)"
    );
}
