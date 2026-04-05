<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/mail_service.php';

function password_reset_otp_count_last_hour(PDO $pdo, int $userId): int
{
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM password_reset_otps WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $st->execute([$userId]);
    return (int) $st->fetchColumn();
}

function password_reset_store_otp(PDO $pdo, int $userId, string $otpPlain): void
{
    $pdo->prepare('DELETE FROM password_reset_otps WHERE user_id = ?')->execute([$userId]);
    $hash = password_hash($otpPlain, PASSWORD_DEFAULT);
    $mins = (int) PASSWORD_RESET_OTP_MINUTES;
    $st = $pdo->prepare(
        'INSERT INTO password_reset_otps (user_id, otp_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))'
    );
    $st->execute([$userId, $hash, $mins]);
}

/**
 * @return array{ok:bool, message:string, user?:array}
 */
function password_reset_try_send_otp(PDO $pdo, string $username): array
{
    $username = trim($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Enter your username.'];
    }
    if (!mail_is_configured()) {
        return [
            'ok' => false,
            'message' => 'In config/config.php, set MAIL_SMTP_PASS to your 16-character Gmail App Password (Google Account → Security → 2-Step Verification → App passwords). Replace the placeholder; do not use your normal Gmail password.',
        ];
    }

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();
    if (!$user) {
        return [
            'ok' => false,
            'message' => 'No account found with that username. Check the spelling and try again.',
        ];
    }

    if (($user['registration_status'] ?? '') === 'rejected') {
        return ['ok' => false, 'message' => 'This account cannot reset its password.'];
    }

    $uid = (int) $user['id'];
    if (password_reset_otp_count_last_hour($pdo, $uid) >= PASSWORD_RESET_MAX_PER_HOUR) {
        return ['ok' => false, 'message' => 'Too many reset attempts. Try again in about an hour.'];
    }

    $otp = (string) random_int(100000, 999999);

    try {
        send_password_reset_otp_email((string) $user['email'], $username, $otp);
    } catch (Throwable $e) {
        $detail = $e->getMessage();
        if (str_contains($detail, '535') || str_contains($detail, 'BadCredentials') || str_contains($detail, 'not accepted')) {
            return [
                'ok' => false,
                'message' => 'Gmail rejected the SMTP password. Create an App Password: Google Account → Security → 2-Step Verification → App passwords → Mail → copy the 16-character code into MAIL_SMTP_PASS in config/config.php (do not use your normal Gmail password).',
            ];
        }
        return ['ok' => false, 'message' => 'Could not send email. ' . $detail];
    }

    password_reset_store_otp($pdo, $uid, $otp);

    return [
        'ok' => true,
        'message' => 'A reset code was sent to the email address on file for this account.',
        'user' => ['username' => $username],
    ];
}

/**
 * @return array{ok:bool, message:string}
 */
function password_reset_apply(PDO $pdo, string $username, string $otp, string $newPass, string $newPass2): array
{
    $username = trim($username);
    $otp = trim($otp);
    if ($username === '' || $otp === '') {
        return ['ok' => false, 'message' => 'Username and code are required.'];
    }
    if ($newPass !== $newPass2) {
        return ['ok' => false, 'message' => 'New passwords do not match.'];
    }
    if (!valid_password($newPass)) {
        return [
            'ok' => false,
            'message' => 'Password must be at least 8 characters with upper, lower, number, and a special character.',
        ];
    }

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    $user = $st->fetch();
    if (!$user) {
        return ['ok' => false, 'message' => 'Invalid username or code.'];
    }

    $uid = (int) $user['id'];
    $st = $pdo->prepare(
        'SELECT * FROM password_reset_otps WHERE user_id = ? AND expires_at > NOW() ORDER BY id DESC LIMIT 1'
    );
    $st->execute([$uid]);
    $row = $st->fetch();
    if (!$row || !password_verify($otp, $row['otp_hash'])) {
        return ['ok' => false, 'message' => 'Invalid or expired code. Request a new one.'];
    }

    $hash = password_hash($newPass, PASSWORD_DEFAULT);
    $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $uid]);
    $pdo->prepare('DELETE FROM password_reset_otps WHERE user_id = ?')->execute([$uid]);

    return ['ok' => true, 'message' => 'Your password was updated. You can sign in now.'];
}
