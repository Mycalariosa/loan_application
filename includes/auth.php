<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

function session_boot(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function current_user(): ?array
{
    session_boot();
    return $_SESSION['user'] ?? null;
}

function refresh_session_user(PDO $pdo): ?array
{
    $u = current_user();
    if (!$u || !isset($u['id'])) {
        return null;
    }
    $st = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $st->execute([(int) $u['id']]);
    $fresh = $st->fetch();
    if ($fresh) {
        unset($fresh['password_hash']);
        $_SESSION['user'] = $fresh;
        return $fresh;
    }
    return $u;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        redirect('login.php');
    }
    if (($u['account_status'] ?? '') === 'disabled') {
        flash_set('error', 'Your account is disabled.');
        logout_user();
        redirect('login.php');
    }
    $pdo = db();
    delete_expired_rejected_registrations($pdo);
    $u = refresh_session_user($pdo) ?? $u;
    return $u;
}

/** Normal users must be approved; admins skip registration_status */
function require_approved_user(): array
{
    $u = require_login();
    if (($u['role'] ?? '') === 'user' && ($u['registration_status'] ?? '') !== 'approved') {
        redirect('pending.php');
    }
    $pdo = db();
    maybe_downgrade_premium_savings($pdo, $u);
    return refresh_session_user($pdo) ?? $u;
}

function require_admin(): array
{
    $u = require_login();
    if (($u['role'] ?? '') !== 'admin') {
        flash_set('error', 'Admin access only.');
        redirect('dashboard.php');
    }
    return $u;
}

function login_user(array $row): void
{
    session_boot();
    unset($row['password_hash']);
    $_SESSION['user'] = $row;
}

function logout_user(): void
{
    session_boot();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}
