<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/functions.php';

function render_header(string $title, array $user = null): void
{
    $app = APP_NAME;
    $u = $user;
    $home = $u ? ($u['role'] === 'admin' ? app_url('admin/index.php') : app_url('dashboard.php')) : app_url('index.php');
    $current_page = basename($_SERVER['PHP_SELF'], '.php');
    $show_login_button = ($current_page === 'register');
    ?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($title) ?> — Alpha Loans</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-dark: #0a1931; }
        body { font-family: 'Inter', sans-serif; }
        .bg-brand { background-color: var(--brand-dark); }
        .text-brand { color: var(--brand-dark); }
        section { scroll-margin-top: 5rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">
    <header class="bg-brand text-white">
        <nav class="flex justify-between items-center px-6 md:px-10 py-6">
            <div class="text-2xl font-extrabold tracking-tighter">
                <a href="<?= h($home) ?>" class="hover:text-blue-400 transition">
                    ALPHA<span class="text-blue-500">LOANS</span>
                </a>
            </div>
            
            <div class="flex items-center gap-6">
                <?php if ($u): ?>
                    <div class="hidden lg:flex items-center gap-6 text-sm">
                        <?php if ($u['role'] === 'admin'): ?>
                            <a href="<?= h(app_url('admin/index.php')) ?>" class="hover:text-blue-400 transition">Dashboard</a>
                            <a href="<?= h(app_url('admin/users.php')) ?>" class="hover:text-blue-400 transition">Users</a>
                            <a href="<?= h(app_url('admin/registrations.php')) ?>" class="hover:text-blue-400 transition">Registrations</a>
                            <a href="<?= h(app_url('admin/loans.php')) ?>" class="hover:text-blue-400 transition">Loans</a>
                            <a href="<?= h(app_url('admin/savings.php')) ?>" class="hover:text-blue-400 transition">Savings</a>
                            <a href="<?= h(app_url('admin/earnings.php')) ?>" class="hover:text-blue-400 transition">Earnings</a>
                        <?php else: ?>
                            <a href="<?= h(app_url('dashboard.php')) ?>" class="hover:text-blue-400 transition">Home</a>
                            <a href="<?= h(app_url('profile.php')) ?>" class="hover:text-blue-400 transition">Profile</a>
                            <a href="<?= h(app_url('loans.php')) ?>" class="hover:text-blue-400 transition">Loans</a>
                            <a href="<?= h(app_url('billing.php')) ?>" class="hover:text-blue-400 transition">Billing</a>
                            <?php if (($u['account_type'] ?? '') === 'premium'): ?>
                            <a href="<?= h(app_url('savings.php')) ?>" class="hover:text-blue-400 transition">Savings</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex items-center gap-4">
                        <span class="text-sm text-gray-300"><?= h($u['username'] ?? '') ?></span>
                        <a class="bg-white/10 hover:bg-white/20 text-white px-4 py-2 rounded-lg text-sm font-medium transition" href="<?= h(app_url('logout.php')) ?>">Logout</a>
                    </div>
                <?php else: ?>
                    <div class="flex items-center gap-6">
                        <?php if ($show_login_button): ?>
                        <a href="<?= h(app_url('login.php')) ?>" class="bg-white text-brand px-6 py-2 rounded-lg font-bold hover:bg-blue-50 transition text-sm">LOGIN</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </nav>
    </header>
    
    <main class="container-fluid px-4 pb-5 pt-8 max-w-screen-xl mx-auto">
        <div class="max-w-7xl mx-auto">
<?php
}

function render_footer(): void
{
    ?>
    </main>
    
    <footer class="bg-brand text-white py-12 px-10 text-center border-t border-gray-800">
        <p class="text-sm opacity-50">&copy; <?php echo date('Y'); ?> Alpha Loans Philippines. Licensed Lending System.</p>
    </footer>
</body>
</html>
<?php
}

function flash_alert(): void
{
    $e = flash_get('error');
    $o = flash_get('ok');
    if ($e) {
        echo '<div class="max-w-2xl mx-auto mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">' . h($e) . '</div>';
    }
    if ($o) {
        echo '<div class="max-w-2xl mx-auto mb-6 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg">' . h($o) . '</div>';
    }
}
