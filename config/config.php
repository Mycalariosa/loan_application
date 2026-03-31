<?php
declare(strict_types=1);

/**
 * Default admin (after sql/schema.sql): username adminuser, password: password
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
define('DB_NAME', 'schema');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

define('SESSION_NAME', 'loanapp_sess');

/** Web path to app (no trailing slash), e.g. /loan_application for XAMPP htdocs/loan_application */
define('APP_BASE', '/loan_application');

function app_url(string $path): string
{
    return rtrim(APP_BASE, '/') . '/' . ltrim($path, '/');
}
