<?php
declare(strict_types=1);

/**
 * Default admin (after sql/loan_app.sql): username adminuser, password: password
 */
define('APP_NAME', 'Loan Application');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . DIRECTORY_SEPARATOR . 'uploads');
define('MAX_PREMIUM_MEMBERS', 50);
define('LOAN_INITIAL_CEILING', 10000);
define('LOAN_MAX_CEILING', 50000);
define('LOAN_MIN_AMOUNT', 5000);
define('LOAN_INTEREST_PERCENT', 3.0);
define('PENALTY_PERCENT', 2.0);
define('SAVINGS_MAX_BALANCE', 100000);
define('SAVINGS_MIN_TXN', 100);
define('SAVINGS_MAX_DEPOSIT_TXN', 1000);
define('SAVINGS_WITHDRAW_MIN', 500);
define('SAVINGS_WITHDRAW_MAX', 5000);
define('SAVINGS_WITHDRAW_MAX_PER_DAY', 5);
define('MONEY_BACK_PERCENT', 2.0);

define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'loan_app');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

/**
 * SMTP for password-reset OTP.
 * Gmail: use MAIL_SMTP_USER = full address. For MAIL_SMTP_PASS you MUST use a 16-character
 * App Password (Google Account → Security → 2-Step Verification → App passwords), not your normal Gmail password.
 */
define('MAIL_FROM_ADDR', 'loanapply10@gmail.com');
define('MAIL_FROM_NAME', APP_NAME);
define('MAIL_SMTP_HOST', 'smtp.gmail.com');
define('MAIL_SMTP_PORT', 587);
define('MAIL_SMTP_ENCRYPTION', 'tls');
define('MAIL_SMTP_USER', 'loanapply10@gmail.com');
define('MAIL_SMTP_PASS', 'nxuu mdmf qdtg ftdv');
define('MAIL_SMTP_VERIFY_PEER', true);

define('PASSWORD_RESET_OTP_MINUTES', 15);
define('PASSWORD_RESET_MAX_PER_HOUR', 3);

define('SESSION_NAME', 'loanapp_sess');

/** Web path to app (no trailing slash), e.g. /loan_application for XAMPP htdocs/loan_application */
define('APP_BASE', '/loan_application');

function app_url(string $path): string
{
    return rtrim(APP_BASE, '/') . '/' . ltrim($path, '/');
}
