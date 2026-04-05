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

if (isset($_GET['restart']) && $_GET['restart'] === '1') {
    unset($_SESSION['pw_reset_username']);
    header('Location: ' . app_url('forgot_password.php'));
    exit;
}

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

        header('Location: ' . app_url('forgot_password.php'));
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
        header('Location: ' . app_url('forgot_password.php'));
        exit;
    }

    header('Location: ' . app_url('forgot_password.php'));
    exit;
}

$step2 = isset($_SESSION['pw_reset_username']);
$pwResetUsername = (string) ($_SESSION['pw_reset_username'] ?? '');

render_header('Reset password');
flash_alert();
?>

<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4 p-md-5">

                <p class="text-muted small text-uppercase mb-1" style="letter-spacing: 0.06em;">
                    Account recovery
                </p>

                <h1 class="h3 mb-3">Reset your password</h1>

                <div class="d-flex gap-2 mb-4 small">
                    <span class="badge rounded-pill <?= $step2 ? 'bg-light text-muted border' : 'bg-primary' ?>">
                        1. Request code
                    </span>
                    <span class="text-muted">→</span>
                    <span class="badge rounded-pill <?= $step2 ? 'bg-primary' : 'bg-light text-muted border' ?>">
                        2. Code & new password
                    </span>
                </div>

                <?php if (!$step2): ?>
                    <p class="text-muted mb-4">
                        Enter your username. We’ll email a one-time code to the address on your account.
                    </p>

                    <form method="post" class="vstack gap-3">
                        <input type="hidden" name="pw_action" value="send_otp">

                        <div>
                            <label class="form-label fw-semibold" for="fp-user">Username</label>
                            <input type="text" id="fp-user" name="pw_username" class="form-control" required
                                   autocomplete="username" value="<?= h($pwResetUsername) ?>"
                                   placeholder="Your username">
                        </div>

                        <button type="submit" class="btn btn-primary">Send code to email</button>
                    </form>

                <?php else: ?>
                    <p class="text-muted mb-2">
                        Check your email for the 6-digit code, then set a new password for:
                    </p>

                    <p class="fw-semibold mb-4"><?= h($pwResetUsername) ?></p>

                    <form method="post" class="vstack gap-3" id="fp-reset-form" novalidate>
                        <input type="hidden" name="pw_action" value="reset_password">
                        <input type="hidden" name="pw_username" value="<?= h($pwResetUsername) ?>">
                        <input type="hidden" name="pw_otp" id="fp-otp-hidden" value="">

                        <!-- OTP -->
                        <div class="otp-verify-block">
                            <div class="otp-heading">Verification code</div>
                            <p class="otp-hint mb-0">
                                Enter the 6-digit code from your email. It expires in <?= (int) PASSWORD_RESET_OTP_MINUTES ?> minutes.
                            </p>

                            <div class="otp-verify-group mt-2">
                                <?php for ($d = 0; $d < 6; $d++): ?>
                                    <input type="text"
                                           class="form-control otp-digit"
                                           inputmode="numeric"
                                           maxlength="1"
                                           autocomplete="<?= $d === 0 ? 'one-time-code' : 'off' ?>"
                                           data-otp-index="<?= $d ?>"
                                           id="fp-otp-<?= $d ?>">
                                <?php endfor; ?>
                            </div>

                            <div id="fp-otp-error" class="text-danger d-none text-center mt-2"></div>
                        </div>

                        <!-- PASSWORD BLOCK (HIDDEN INITIALLY) -->
                        <div id="password-block" style="display:none;" class="vstack gap-3">

                            <div>
                                <label class="form-label fw-semibold" for="fp-new">New password</label>
                                <input type="password" id="fp-new" name="pw_new" class="form-control" required
                                       autocomplete="new-password">
                                <div class="form-text">
                                    At least 8 characters with upper, lower, number, and a special character.
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold" for="fp-new2">Confirm new password</label>
                                <input type="password" id="fp-new2" name="pw_new2" class="form-control" required
                                       autocomplete="new-password">
                            </div>

                            <button type="submit" class="btn btn-success">
                                Update password
                            </button>

                        </div>
                    </form>

                    <p class="mt-3 mb-0 small">
                        <a href="<?= h(app_url('forgot_password.php?restart=1')) ?>" class="text-decoration-none">
                            ← Start over with a different username
                        </a>
                    </p>
                <?php endif; ?>

                <p class="mt-4 mb-0 text-center">
                    <a href="<?= h(app_url('login.php')) ?>" class="text-decoration-none">Back to sign in</a>
                </p>

            </div>
        </div>
    </div>
</div>

<?php if ($step2): ?>
<script>
(function () {
    var form = document.getElementById('fp-reset-form');
    var cells = document.querySelectorAll('.otp-digit');
    var hidden = document.getElementById('fp-otp-hidden');
    var errBox = document.getElementById('fp-otp-error');
    var pwBlock = document.getElementById('password-block');

    function sync() {
        var s = '';

        cells.forEach(function (c) {
            var v = (c.value || '').replace(/\D/g, '').slice(0, 1);
            c.value = v;
            s += v;
        });

        if (hidden) hidden.value = s;

        // SHOW PASSWORD BLOCK ONLY WHEN OTP IS COMPLETE
        if (pwBlock) {
            if (s.length === 6) {
                pwBlock.style.display = 'block';
            } else {
                pwBlock.style.display = 'none';
            }
        }
    }

    cells.forEach(function (cell, i) {
        cell.addEventListener('input', function () {
            var v = cell.value.replace(/\D/g, '').slice(-1);
            cell.value = v;

            sync();

            if (errBox) errBox.classList.add('d-none');

            if (v && i < cells.length - 1) {
                cells[i + 1].focus();
            }
        });

        cell.addEventListener('keydown', function (e) {
            if (e.key === 'Backspace' && !cell.value && i > 0) {
                cells[i - 1].focus();
                cells[i - 1].value = '';
                sync();
                e.preventDefault();
            }
        });
    });

    form.addEventListener('paste', function (e) {
        var t = e.target;
        if (!t.classList.contains('otp-digit')) return;

        e.preventDefault();

        var text = (e.clipboardData || window.clipboardData).getData('text') || '';
        var digits = text.replace(/\D/g, '').slice(0, 6);

        for (var j = 0; j < 6; j++) {
            cells[j].value = digits[j] || '';
        }

        sync();

        var focusIdx = Math.min(digits.length, 5);
        cells[focusIdx].focus();
    });

    form.addEventListener('submit', function (e) {
        sync();

        if (!hidden || hidden.value.length !== 6) {
            e.preventDefault();

            if (errBox) {
                errBox.textContent = 'Enter all 6 digits from your email.';
                errBox.classList.remove('d-none');
            }

            return;
        }
    });

    if (cells[0]) cells[0].focus();
})();
</script>
<?php endif; ?>

<?php render_footer(); ?>