<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/smtp_mail.php';

function mail_is_configured(): bool
{
    if (MAIL_SMTP_HOST === '' || MAIL_SMTP_USER === '' || MAIL_FROM_ADDR === '') {
        return false;
    }
    $pass = MAIL_SMTP_PASS;
    if ($pass === '' || strcasecmp($pass, 'REPLACE_WITH_GMAIL_APP_PASSWORD') === 0) {
        return false;
    }
    return true;
}

/**
 * @throws RuntimeException on failure
 */
function send_password_reset_otp_email(string $toEmail, string $username, string $otp): void
{
    if (!mail_is_configured()) {
        throw new RuntimeException('Email is not configured. Set SMTP settings in config/config.php.');
    }

    $subject = APP_NAME . ' — password reset code';
    $body = "Hello,\r\n\r\n";
    $body .= "Your password reset code is: {$otp}\r\n\r\n";
    $body .= 'It expires in ' . PASSWORD_RESET_OTP_MINUTES . " minutes.\r\n\r\n";
    $body .= "If you did not request this, ignore this email.\r\n\r\n";
    $body .= '— ' . APP_NAME . "\r\n";

    smtp_mail_send(
        MAIL_SMTP_HOST,
        MAIL_SMTP_PORT,
        MAIL_SMTP_ENCRYPTION,
        MAIL_SMTP_USER,
        MAIL_SMTP_PASS,
        MAIL_FROM_ADDR,
        MAIL_FROM_NAME,
        $toEmail,
        $subject,
        $body,
        MAIL_SMTP_VERIFY_PEER
    );
}
