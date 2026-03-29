<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$u = require_login();
if (($u['role'] ?? '') !== 'user' || ($u['registration_status'] ?? '') !== 'pending') {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

render_header('Registration pending', $u);
flash_alert();
?>
<div class="alert alert-info">
    <p class="mb-0">Your registration is <strong>pending</strong>. An administrator will review your documents and account details.
    You will be able to use loans and other features after approval.</p>
</div>
<?php render_footer();
