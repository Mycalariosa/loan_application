<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

session_boot();
if (current_user()) {
    $u = current_user();
    header('Location: ' . (($u['role'] ?? '') === 'admin' ? app_url('admin/index.php') : app_url('dashboard.php')));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim((string) ($_POST['username'] ?? ''));
    $pass = (string) ($_POST['password'] ?? '');
    if ($user === '' || $pass === '') {
        $error = 'Enter username and password.';
    } else {
        $pdo = db();
        $st = $pdo->prepare('SELECT * FROM users WHERE username = ? LIMIT 1');
        $st->execute([$user]);
        $row = $st->fetch();
        if (!$row || !password_verify($pass, $row['password_hash'])) {
            $error = 'Invalid credentials.';
        } elseif (($row['registration_status'] ?? '') === 'rejected') {
            $error = 'Your registration was rejected. You may register again after 30 days if your record was removed, or use a different email.';
        } else {
            login_user($row);
            if (($row['role'] ?? '') === 'user' && ($row['registration_status'] ?? '') === 'pending') {
                header('Location: ' . app_url('pending.php'));
                exit;
            }
            if (($row['role'] ?? '') === 'admin') {
                header('Location: ' . app_url('admin/index.php'));
                exit;
            }
            header('Location: ' . app_url('dashboard.php'));
            exit;
        }
    }
}

render_header('Login');
flash_alert();
if ($error) {
    echo '<div class="alert alert-danger">' . h($error) . '</div>';
}
?>

<div class="max-w-lg mx-auto lg:max-w-md">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-6 sm:p-8">

            <h1 class="text-2xl sm:text-3xl font-bold text-brand mb-6 sm:mb-8 text-center">Sign in to Alpha Loans</h1>

            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">Username</label>
                    <input type="text" id="username" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autocomplete="username" placeholder="Enter your username">
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Password</label>
                    <input type="password" id="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autocomplete="current-password" placeholder="Enter your password">
                </div>

                <div class="text-center mb-6">
                    <a href="<?= h(app_url('forgot_password.php')) ?>" class="text-blue-600 hover:text-blue-700 transition text-sm">Forgot password?</a>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">Sign in</button>

                <div class="text-center mt-6">
                    <a href="<?= h(app_url('register.php')) ?>" class="text-blue-600 hover:text-blue-700 transition text-sm">Create an account</a>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_footer();
