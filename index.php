<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
session_boot();
if (current_user()) {
    $u = current_user();
    $dest = (($u['role'] ?? '') === 'admin') ? app_url('admin/index.php') : app_url('dashboard.php');
    header('Location: ' . $dest);
    exit;
}
header('Location: ' . app_url('login.php'));
exit;
