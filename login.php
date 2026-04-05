<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/password_reset.php';
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
    $pwAction = (string) ($_POST['pw_action'] ?? '');
    if ($pwAction === 'send_otp') {
        $pdo = db();
        $r = password_reset_try_send_otp($pdo, (string) ($_POST['pw_username'] ?? ''));
        if ($r['ok']) {
            if (isset($r['user']['username'])) {
                $_SESSION['pw_reset_username'] = $r['user']['username'];
            }
            flash_set('ok', $r['message']);
        } else {
            flash_set('error', $r['message']);
        }
        header('Location: ' . app_url('login.php?forgot=1'));
        exit;
    }
    if ($pwAction === 'reset_password') {
        $pdo = db();
        $r = password_reset_apply(
            $pdo,
            (string) ($_POST['pw_username'] ?? ''),
            (string) ($_POST['pw_otp'] ?? ''),
            (string) ($_POST['pw_new'] ?? ''),
            (string) ($_POST['pw_new2'] ?? '')
        );
        if ($r['ok']) {
            unset($_SESSION['pw_reset_username']);
            flash_set('ok', $r['message']);
            header('Location: ' . app_url('login.php'));
            exit;
        }
        flash_set('error', $r['message']);
        header('Location: ' . app_url('login.php?forgot=1'));
        exit;
    }

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

$showForgotPanel = isset($_SESSION['pw_reset_username']) || (isset($_GET['forgot']) && $_GET['forgot'] === '1');
$showResetStep2 = isset($_SESSION['pw_reset_username']);
$pwResetUsername = (string) ($_SESSION['pw_reset_username'] ?? '');

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
                <button type="submit" class="btn btn-primary">Login</button>
                <p class="mb-0"><a href="<?= h(app_url('register.php')) ?>">Create an account</a></p>
            </form>

            <div class="border rounded p-3 mt-3 bg-white small">
                <div class="form-check mb-0">
                    <input class="form-check-input" type="checkbox" id="pwForgotToggle" <?= $showForgotPanel ? ' checked' : '' ?>>
                    <label class="form-check-label" for="pwForgotToggle">Forgot password — reset using email code</label>
                </div>
                <div id="pwForgotPanel" class="mt-3<?= $showForgotPanel ? '' : ' d-none' ?>">
                    <p class="text-muted mb-2">We email a one-time code to the address on your account.</p>
                    <form method="post" class="vstack gap-2 mb-0">
                        <input type="hidden" name="pw_action" value="send_otp">
                        <div>
                            <label class="form-label mb-0">Username</label>
                            <input type="text" name="pw_username" class="form-control form-control-sm" required
                                   autocomplete="username" value="<?= h($pwResetUsername) ?>"
                                   placeholder="Your username">
                        </div>
                        <button type="submit" class="btn btn-outline-secondary btn-sm align-self-start">Send code to email</button>
                    </form>
                    <?php if ($showResetStep2): ?>
                        <hr class="my-3">
                        <form method="post" class="vstack gap-2">
                            <input type="hidden" name="pw_action" value="reset_password">
                            <input type="hidden" name="pw_username" value="<?= h($pwResetUsername) ?>">
                            <div>
                                <label class="form-label mb-0">Code from email</label>
                                <input type="text" name="pw_otp" class="form-control form-control-sm" required
                                       inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code"
                                       placeholder="6-digit code">
                            </div>
                            <div>
                                <label class="form-label mb-0">New password</label>
                                <input type="password" name="pw_new" class="form-control form-control-sm" required
                                       autocomplete="new-password">
                            </div>
                            <div>
                                <label class="form-label mb-0">Confirm new password</label>
                                <input type="password" name="pw_new2" class="form-control form-control-sm" required
                                       autocomplete="new-password">
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Set new password</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var cb = document.getElementById('pwForgotToggle');
    var panel = document.getElementById('pwForgotPanel');
    if (!cb || !panel) return;
    cb.addEventListener('change', function () {
        panel.classList.toggle('d-none', !cb.checked);
    });
})();
</script>
<?php render_footer();
