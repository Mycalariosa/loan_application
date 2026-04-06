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
    unset($_SESSION['otp_request_time']); // Clear OTP request time
    header('Location: ' . app_url('forgot_password.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pwAction = (string) ($_POST['pw_action'] ?? '');

    // Handle AJAX OTP validation
    if ($pwAction === 'validate_otp') {
        header('Content-Type: application/json');
        $pdo = db();
        $result = password_reset_validate_otp(
            $pdo,
            (string) ($_POST['username'] ?? ''),
            (string) ($_POST['otp'] ?? '')
        );
        echo json_encode($result);
        exit;
    }

    if ($pwAction === 'send_otp') {
        $pdo = db();
        $r = password_reset_try_send_otp($pdo, (string) ($_POST['pw_username'] ?? ''));

        if ($r['ok']) {
            if (isset($r['user']['username'])) {
                $_SESSION['pw_reset_username'] = $r['user']['username'];
                // Store OTP request time for countdown
                $_SESSION['otp_request_time'] = time();
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

        // For AJAX requests, return JSON instead of redirecting
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
            header('Content-Type: application/json');
            echo json_encode($r);
            exit;
        }

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

<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-8">

            <p class="text-gray-500 text-sm uppercase mb-2 tracking-wider">
                Account recovery
            </p>

            <h1 class="text-3xl font-bold text-brand mb-6">Reset your password</h1>

            <div class="flex gap-2 mb-6 text-sm">
                <span class="<?= $step2 ? 'bg-gray-100 text-gray-600 border border-gray-300' : 'bg-blue-600 text-white' ?> px-3 py-1 rounded-full font-medium">
                    1. Request code
                </span>
                <span class="text-gray-400">→</span>
                <span class="<?= $step2 ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600 border border-gray-300' ?> px-3 py-1 rounded-full font-medium">
                    2. Code & new password
                </span>
            </div>

                <?php if (!$step2): ?>
                    <p class="text-gray-600 mb-6">
                        Enter your username. We'll email a one-time code to the address on your account.
                    </p>

                    <form method="post" class="space-y-4">
                        <input type="hidden" name="pw_action" value="send_otp">

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="fp-user">Username</label>
                            <input type="text" id="fp-user" name="pw_username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required
                                   autocomplete="username" value="<?= h($pwResetUsername) ?>"
                                   placeholder="Your username">
                        </div>

                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition">Send code to email</button>
                    </form>

                <?php else: ?>
                    <form method="post" class="space-y-6" id="fp-reset-form" novalidate>
                        <input type="hidden" name="pw_action" value="reset_password">
                        <input type="hidden" name="pw_username" value="<?= h($pwResetUsername) ?>">
                        <input type="hidden" name="pw_otp" id="fp-otp-hidden" value="">

                        <!-- OTP -->
                        <div class="otp-verify-block" id="otp-section">
                            <div class="text-lg font-semibold text-brand mb-3">Verification code</div>
                            <p class="text-gray-600 mb-4">
                                Enter 6-digit code from your email. 
                                <span id="otp-countdown" class="text-blue-600 font-semibold"></span>
                            </p>

                            <div class="otp-verify-group flex gap-2 justify-center mb-4" id="otp-input-group">
                                <?php for ($d = 0; $d < 6; $d++): ?>
                                    <input type="text"
                                           class="w-12 h-12 text-center text-lg border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           inputmode="numeric"
                                           maxlength="1"
                                           autocomplete="<?= $d === 0 ? 'one-time-code' : 'off' ?>"
                                           data-otp-index="<?= $d ?>"
                                           id="fp-otp-<?= $d ?>">
                                <?php endfor; ?>
                            </div>

                            <div id="fp-otp-error" class="text-red-600 text-center mt-3 hidden"></div>
                        </div>

                        <!-- REQUEST NEW CODE BUTTON (HIDDEN INITIALLY) -->
                        <div id="request-new-code" class="hidden mt-4">
                            <button type="button" class="w-full border-2 border-blue-600 text-blue-600 bg-white hover:bg-blue-50 py-3 px-4 rounded-lg font-semibold transition" id="btn-request-new">
                                Request new verification code
                            </button>
                        </div>

                        <!-- PASSWORD BLOCK (HIDDEN INITIALLY) -->
                        <div id="password-block" class="hidden space-y-4">

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2" for="fp-new">New password</label>
                                <input type="password" id="fp-new" name="pw_new" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required
                                       autocomplete="new-password">
                                <div class="text-sm text-gray-500 mt-1">
                                    At least 8 characters with upper, lower, number, and a special character.
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2" for="fp-new2">Confirm new password</label>
                                <input type="password" id="fp-new2" name="pw_new2" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required
                                       autocomplete="new-password">
                            </div>

                            <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-4 rounded-lg transition">
                                Update password
                            </button>

                        </div>
                    </form>

                    <p class="mt-6 text-sm">
                        <a href="<?= h(app_url('forgot_password.php?restart=1')) ?>" class="text-blue-600 hover:text-blue-700 transition">
                            ← Start over with a different username
                        </a>
                    </p>
                <?php endif; ?>

                <p class="mt-8 text-center">
                    <a href="<?= h(app_url('login.php')) ?>" class="text-blue-600 hover:text-blue-700 transition">Back to sign in</a>
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
    var countdownEl = document.getElementById('otp-countdown');
    var otpSection = document.getElementById('otp-section');
    var username = document.querySelector('input[name="pw_username"]').value;
    
    var otpValidated = false;
    var countdownInterval;
    // Calculate time left based on when OTP was requested
    var otpRequestTime = <?= (int) ($_SESSION['otp_request_time'] ?? 0) ?> * 1000; // Convert to milliseconds
    var totalTime = <?= PASSWORD_RESET_OTP_MINUTES ?> * 60 * 1000; // Total time in milliseconds
    var elapsed = Date.now() - otpRequestTime;
    var initialTimeLeft = Math.max(0, Math.floor((totalTime - elapsed) / 1000)); // Convert back to seconds
    var timeLeft = initialTimeLeft;
    
    // Debug: Log initial values
    console.log('OTP Request Time:', otpRequestTime);
    console.log('Total Time:', totalTime);
    console.log('Elapsed:', elapsed);
    console.log('Initial Time Left:', initialTimeLeft);

    // Countdown timer
    function updateCountdown() {
        console.log('Current timeLeft:', timeLeft); // Debug log
        
        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            countdownEl.textContent = 'Code expired.';
            countdownEl.className = 'text-red-600 font-semibold';
            
            // Clear OTP inputs
            cells.forEach(function(cell) {
                cell.value = '';
                cell.disabled = true;
            });
            
            // Show request new code button
            if (errBox) {
                errBox.textContent = 'Your verification code has expired. Request a new one below.';
                errBox.classList.remove('hidden');
            }
            
            // Show request new code button
            var requestNewBtn = document.getElementById('request-new-code');
            if (requestNewBtn) {
                requestNewBtn.classList.remove('hidden');
            }
            
            pwBlock.style.display = 'none';
            return;
        }

        var minutes = Math.floor(timeLeft / 60);
        var seconds = timeLeft % 60;
        countdownEl.textContent = 'Expires in ' + minutes + ':' + (seconds < 10 ? '0' : '') + seconds;
        
        timeLeft--;
    }

    // Start countdown
    updateCountdown();
    countdownInterval = setInterval(updateCountdown, 1000);
    
    // If initial timeLeft is 0 or negative, but OTP was recently requested, 
    // recalculate based on server time
    if (initialTimeLeft <= 0 && <?= (int) ($_SESSION['otp_request_time'] ?? 0) ?> > 0) {
        // Make a server request to get the actual time left
        var formData = new FormData();
        formData.append('pw_action', 'validate_otp');
        formData.append('username', username);
        formData.append('otp', '000000'); // Dummy OTP to check expiry
        
        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.expires_in && data.expires_in > 0) {
                // Reset countdown with server time
                clearInterval(countdownInterval);
                timeLeft = data.expires_in * 60;
                updateCountdown();
                countdownInterval = setInterval(updateCountdown, 1000);
            }
        })
        .catch(function(error) {
            console.error('Error checking OTP expiry:', error);
        });
    }

    function sync() {
        var s = '';

        cells.forEach(function (c) {
            var v = (c.value || '').replace(/\D/g, '').slice(0, 1);
            c.value = v;
            s += v;
        });

        if (hidden) hidden.value = s;

        // Validate OTP when complete
        if (s.length === 6 && !otpValidated) {
            validateOTP(s);
        } else if (s.length < 6) {
            otpValidated = false;
            pwBlock.style.display = 'none';
        }
    }

    function validateOTP(otp) {
        var formData = new FormData();
        formData.append('pw_action', 'validate_otp');
        formData.append('username', username);
        formData.append('otp', otp);

        fetch('', {
            method: 'POST',
            body: formData
            if (data.ok) {
                otpValidated = true;
                if (errBox) errBox.classList.add('hidden');
                
                // Hide entire OTP section and show password block
                if (otpSection) otpSection.style.display = 'none';
                
                // Disable OTP inputs to prevent changes
                cells.forEach(function(cell) {
                    cell.disabled = true;
                    cell.readOnly = true;
                });
                
                pwBlock.style.display = 'block';
                
                // Update countdown with actual time left from server
                if (data.expires_in && countdownEl) {
                    timeLeft = data.expires_in * 60;
                }
            } else {
                otpValidated = false;
                if (errBox) {
                    errBox.textContent = data.message;
                    errBox.classList.remove('hidden');
                }
                pwBlock.style.display = 'none';
                
                // Clear all OTP inputs for re-typing
                cells.forEach(function(cell) {
                    cell.value = '';
                    cell.classList.remove('border-red-500', 'ring-red-500', 'ring-2', 'ring-red-500');
                });
                hidden.value = '';
                
                // Focus back to first input
                if (cells[0]) {
                    cells[0].focus();
                }
            }
        })
        .catch(function(error) {
            console.error('OTP validation error:', error);
            if (errBox) {
                errBox.textContent = 'Validation failed. Try again.';
                errBox.classList.remove('d-none');
            }
        });
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

        if (!otpValidated) {
            e.preventDefault();
            
            if (errBox) {
                errBox.textContent = 'Please wait for OTP validation to complete.';
                errBox.classList.remove('d-none');
            }
            
            return;
        }

        // Handle password submission via AJAX
        e.preventDefault();
        
        // Validate passwords before sending
        var newPass = document.getElementById('fp-new').value;
        var newPass2 = document.getElementById('fp-new2').value;
        
        if (newPass !== newPass2) {
            if (errBox) {
                errBox.textContent = 'New passwords do not match.';
                errBox.classList.remove('hidden');
            }
            
            // Shake password fields for error
            var pwInputs = document.querySelectorAll('#fp-new, #fp-new2');
            pwInputs.forEach(function(input) {
                input.classList.add('border-red-500', 'ring-red-500', 'ring-2');
                setTimeout(function() {
                    input.classList.remove('border-red-500', 'ring-red-500', 'ring-2');
                }, 500);
            });
            return;
        }
        
        // Validate password strength
        if (newPass.length < 8 || !/[A-Z]/.test(newPass) || !/[a-z]/.test(newPass) || !/\d/.test(newPass) || !/[^A-Za-z0-9]/.test(newPass)) {
            if (errBox) {
                errBox.textContent = 'Password must be at least 8 characters with upper, lower, number, and a special character.';
                errBox.classList.remove('hidden');
            }
            
            // Shake password fields for error
            var pwInputs = document.querySelectorAll('#fp-new, #fp-new2');
            pwInputs.forEach(function(input) {
                input.classList.add('border-red-500', 'ring-red-500', 'ring-2');
                setTimeout(function() {
                    input.classList.remove('border-red-500', 'ring-red-500', 'ring-2');
                }, 500);
            });
            return;
        }
        
        var formData = new FormData(form);
        // Add AJAX header to trigger JSON response
        formData.append('ajax', '1');
        
        fetch('', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            console.log('Password reset response:', data); // Debug log
            if (data.ok) {
                // Show success message before redirecting
                if (errBox) {
                    errBox.textContent = 'You successfully changed your password! Redirecting to login page...';
                    errBox.classList.remove('d-none');
                    errBox.classList.remove('text-danger');
                    errBox.classList.add('text-success');
                }
                
                // Hide password fields and show success
                pwBlock.style.display = 'none';
                
                // Redirect after 2 seconds
                setTimeout(function() {
                    window.location.href = '<?= app_url('login.php') ?>';
                }, 2000);
            } else {
                // Error - show message without redirecting
                if (errBox) {
                    errBox.textContent = data.message;
                    errBox.classList.remove('d-none');
                    errBox.classList.remove('text-success');
                    errBox.classList.add('text-danger');
                }
                
                // Shake password fields for error
                var pwInputs = document.querySelectorAll('#fp-new, #fp-new2');
                pwInputs.forEach(function(input) {
                    input.classList.add('is-invalid');
                    setTimeout(function() {
                        input.classList.remove('is-invalid');
                    }, 500);
                });
            }
        })
        .catch(function(error) {
            console.error('Password reset error:', error);
            if (errBox) {
                errBox.textContent = 'An error occurred. Please try again.';
                errBox.classList.remove('d-none');
            }
        });
    });

    if (cells[0]) cells[0].focus();

    // Handle request new code button
    var requestNewBtn = document.getElementById('btn-request-new');
    if (requestNewBtn) {
        requestNewBtn.addEventListener('click', function() {
            var formData = new FormData();
            formData.append('pw_action', 'send_otp');
            formData.append('pw_username', username);
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(function(response) {
                return response.text();
            })
            .then(function(data) {
                // Reload page to show new OTP
                window.location.reload();
            })
            .catch(function(error) {
                console.error('Request new code error:', error);
                if (errBox) {
                    errBox.textContent = 'Failed to request new code. Please try again.';
                    errBox.classList.remove('d-none');
                }
            });
        });
    }
})();
</script>
<?php endif; ?>

<?php render_footer(); ?>