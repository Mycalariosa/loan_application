<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function render_header(string $title, array $user = null): void
{
    $app = APP_NAME;
    $u = $user;
    $home = $u ? ($u['role'] === 'admin' ? app_url('admin/index.php') : app_url('dashboard.php')) : app_url('index.php');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title) ?> — <?= h($app) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= h(app_url('assets/css/app.css')) ?>" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand" href="<?= h($home) ?>"><?= h($app) ?></a>
        <?php if ($u): ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <?php if ($u['role'] === 'admin'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/index.php')) ?>">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/users.php')) ?>">Users</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/registrations.php')) ?>">Registrations</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/loans.php')) ?>">Loans</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/savings.php')) ?>">Savings</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('admin/earnings.php')) ?>">Earnings</a></li>
                <?php else: ?>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('dashboard.php')) ?>">Home</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('profile.php')) ?>">Profile</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('loans.php')) ?>">Loans</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('billing.php')) ?>">Billing</a></li>
                    <?php if (($u['account_type'] ?? '') === 'premium'): ?>
                    <li class="nav-item"><a class="nav-link" href="<?= h(app_url('savings.php')) ?>">Savings</a></li>
                    <?php endif; ?>
                <?php endif; ?>
            </ul>
            <span class="navbar-text text-white me-3"><?= h($u['username'] ?? '') ?></span>
            <a class="btn btn-outline-light btn-sm" href="<?= h(app_url('logout.php')) ?>">Logout</a>
        </div>
        <?php endif; ?>
    </div>
</nav>
<main class="container pb-5">
<?php
}

function render_footer(): void
{
    ?>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
}

function flash_alert(): void
{
    $e = flash_get('error');
    $o = flash_get('ok');
    if ($e) {
        echo '<div class="alert alert-danger">' . h($e) . '</div>';
    }
    if ($o) {
        echo '<div class="alert alert-success">' . h($o) . '</div>';
    }
}
