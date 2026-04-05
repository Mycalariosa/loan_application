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
<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card p-4">
            <h1 class="h4 mb-3">Sign in</h1>
            <form method="post" class="vstack gap-3">
                <div>
                    <label class="form-label">Username</label>
                    <input type="text" name="username" class="form-control" required autocomplete="username">
                </div>
                <div>
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <p class="mb-0 small"><a href="<?= h(app_url('forgot_password.php')) ?>">Forgot password?</a></p>
                <button type="submit" class="btn btn-primary">Login</button>
                <p class="mb-0"><a href="<?= h(app_url('register.php')) ?>">Create an account</a></p>
            </form>
        </div>
    </div>
</div>
<?php render_footer();
