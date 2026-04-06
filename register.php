<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

session_boot();
if (current_user()) {
    header('Location: ' . app_url('dashboard.php'));
    exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accountType = $_POST['account_type'] ?? '';
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $name = trim((string) ($_POST['name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $birthday = trim((string) ($_POST['birthday'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $contact = trim((string) ($_POST['contact_number'] ?? ''));
    $bankName = trim((string) ($_POST['bank_name'] ?? ''));
    $bankAcct = trim((string) ($_POST['bank_account_number'] ?? ''));
    $cardHolder = trim((string) ($_POST['card_holder_name'] ?? ''));
    $tin = trim((string) ($_POST['tin_number'] ?? ''));
    $companyName = trim((string) ($_POST['company_name'] ?? ''));
    $companyAddr = trim((string) ($_POST['company_address'] ?? ''));
    $companyPhone = trim((string) ($_POST['company_phone'] ?? ''));
    $position = trim((string) ($_POST['position'] ?? ''));
    $earnings = trim((string) ($_POST['monthly_earnings'] ?? ''));

    if (!in_array($accountType, ['basic', 'premium'], true)) {
        $errors[] = 'Account type is required.';
    }
    if (!valid_username($username)) {
        $errors[] = 'Username must be at least 6 characters.';
    }
    if (!valid_password($password)) {
        $errors[] = 'Password must be at least 8 characters and include upper, lower, number, and a special character.';
    }
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($address === '') {
        $errors[] = 'Address is required.';
    }
    if ($birthday === '') {
        $errors[] = 'Birthday is required.';
    } else {
        try {
            $age = calculate_age($birthday);
            if ($age < 18) {
                $errors[] = 'You must be at least 18 years old.';
            }
        } catch (Throwable $e) {
            $errors[] = 'Invalid birthday.';
            $age = 0;
        }
    }
    if (!isset($age)) {
        $age = calculate_age($birthday);
    }
    if (!valid_email($email)) {
        $errors[] = 'Valid email is required.';
    }
    if (!valid_ph_phone($contact)) {
        $errors[] = 'Enter a valid Philippines mobile number (+639XXXXXXXXX or 09XXXXXXXXX).';
    }
    if ($bankName === '' || $bankAcct === '' || $cardHolder === '') {
        $errors[] = 'Bank name, account number, and card holder name are required.';
    }
    if ($tin === '') {
        $errors[] = 'TIN number is required.';
    }
    if ($companyName === '' || $companyAddr === '' || $companyPhone === '') {
        $errors[] = 'Company name, address, and phone are required.';
    }

    $files = ['proof_of_billing', 'valid_id', 'coe'];
    $uploaded = [];
    foreach ($files as $f) {
        if (!isset($_FILES[$f]) || $_FILES[$f]['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'All uploads are required (Proof of Billing, Valid ID, COE).';
            break;
        }
        $tmp = $_FILES[$f]['tmp_name'];
        $mime = function_exists('mime_content_type') ? mime_content_type($tmp) : '';
        if ($mime === '' && class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $mime = $fi->file($tmp) ?: '';
        }
        $okMime = str_starts_with((string) $mime, 'image/') || $mime === 'application/pdf';
        if (!$okMime) {
            $errors[] = 'Uploads must be images or PDF.';
            break;
        }
        $uploaded[$f] = $_FILES[$f];
    }

    $pdo = db();
    if (valid_email($email) && is_email_blocked($pdo, $email)) {
        $errors[] = 'This email address cannot be used for registration.';
    }
    if (valid_email($email) && email_registered($pdo, $email)) {
        $errors[] = 'Email is already registered.';
    }
    $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ? LIMIT 1');
    $st->execute([$username]);
    if ($st->fetchColumn()) {
        $errors[] = 'Username is already taken.';
    }

    if ($accountType === 'premium' && count_premium_users($pdo) >= MAX_PREMIUM_MEMBERS) {
        $errors[] = 'Premium membership is full (maximum ' . MAX_PREMIUM_MEMBERS . ' members). Choose Basic or try later.';
    }

    if ($errors === []) {
        if (!is_dir(UPLOAD_PATH)) {
            mkdir(UPLOAD_PATH, 0775, true);
        }
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO users (role, username, password_hash, account_type, name, address, gender, birthday, age, email, contact_number,
                 bank_name, bank_account_number, card_holder_name, tin_number, company_name, company_address, company_phone, position, monthly_earnings,
                 registration_status, verified_tag, account_status, current_loan_ceiling, max_loan_term_months)
                 VALUES (\'user\',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',0,\'active\',?,12)'
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $earnVal = $earnings === '' ? null : (float) $earnings;
            $st->execute([
                $username,
                $hash,
                $accountType,
                $name,
                $address,
                $gender ?: null,
                $birthday,
                $age,
                $email,
                normalize_ph_phone($contact),
                $bankName,
                $bankAcct,
                $cardHolder,
                $tin,
                $companyName,
                $companyAddr,
                $companyPhone,
                $position ?: null,
                $earnVal,
                LOAN_INITIAL_CEILING,
            ]);
            $uid = (int) $pdo->lastInsertId();
            $userDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . $uid;
            mkdir($userDir, 0775, true);

            $map = ['proof_of_billing' => 'proof_of_billing', 'valid_id' => 'valid_id', 'coe' => 'coe'];
            foreach ($map as $field => $docType) {
                $ext = pathinfo($uploaded[$field]['name'], PATHINFO_EXTENSION);
                $safe = $docType . '_' . bin2hex(random_bytes(4)) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                $dest = $userDir . DIRECTORY_SEPARATOR . $safe;
                if (!move_uploaded_file($uploaded[$field]['tmp_name'], $dest)) {
                    throw new RuntimeException('File upload failed.');
                }
                $rel = 'uploads/' . $uid . '/' . $safe;
                $pdo->prepare('INSERT INTO registration_documents (user_id, doc_type, file_path) VALUES (?,?,?)')
                    ->execute([$uid, $docType, $rel]);
            }
            $pdo->commit();
            flash_set('ok', 'Registration submitted. Your status is pending until an administrator approves your application.');
            header('Location: ' . app_url('login.php'));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Registration failed. Please try again.';
        }
    }
}

render_header('Register');
flash_alert();
foreach ($errors as $e) {
    echo '<div class="alert alert-danger">' . h($e) . '</div>';
}
?>
<div class="max-w-5xl mx-auto lg:max-w-4xl">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-6 sm:p-8">

            <h1 class="text-2xl sm:text-3xl font-bold text-brand mb-6 sm:mb-8 text-center">Create Alpha Loans Account</h1>

            <form method="post" enctype="multipart/form-data" class="space-y-8">
                <!-- Account Type Selection -->
                <div class="mb-8">
                    <h2 class="text-xl font-semibold text-brand mb-4">Choose Your Membership</h2>
                    <div class="grid md:grid-cols-2 gap-6 max-w-3xl mx-auto">
                        <div id="basic-card" class="border-2 border-gray-300 p-6 rounded-xl hover:border-blue-500 transition cursor-pointer">
                            <h3 class="text-lg font-bold mb-2">Basic</h3>
                            <p class="text-gray-600 text-sm mb-4">Standard Loan Access</p>
                            <ul class="space-y-2 text-gray-600">
                                <li class="flex items-center gap-2">✅ Standard Loan Access</li>
                                <li class="flex items-center gap-2">✅ Monthly Billing Summary</li>
                                <li class="flex items-center gap-2 opacity-30">❌ Savings Account</li>
                                <li class="flex items-center gap-2 opacity-30">❌ Money Back Dividends</li>
                            </ul>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="account_type" value="basic" class="mr-2" required>
                                <span class="font-medium">Join Basic</span>
                            </label>
                        </div>
                        
                        <div id="premium-card" class="border-2 border-gray-300 p-6 rounded-xl hover:border-blue-500 transition cursor-pointer">
                            <div class="flex justify-between items-start mb-2">
                                <h3 class="text-xl font-bold">Premium</h3>
                                <span class="bg-gray-100 text-gray-600 text-xs font-black px-2 py-1 rounded">50 SLOTS ONLY</span>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">Exclusive Benefits</p>
                            <ul class="space-y-3 text-gray-600 font-medium">
                                <li class="flex items-center gap-2">✅ All Basic Features</li>
                                <li class="flex items-center gap-2">✅ <strong>Savings Account (Max 100k)</strong></li>
                                <li class="flex items-center gap-2">✅ <strong>2% Yearly Company Dividends</strong></li>
                                <li class="flex items-center gap-2">✅ Earned Money Back</li>
                            </ul>
                            <label class="flex items-center cursor-pointer">
                                <input type="radio" name="account_type" value="premium" class="mr-2" required>
                                <span class="font-medium">Get Premium</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Personal Information -->
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="username">Username *</label>
                        <input type="text" id="username" name="username" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required minlength="6" value="<?= h($_POST['username'] ?? '') ?>" placeholder="Choose a username (min 6 characters)">
                        <div class="text-sm text-gray-500 mt-1">Email allowed for username</div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="password">Password *</label>
                        <input type="password" id="password" name="password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required autocomplete="new-password" placeholder="Create a strong password">
                        <div class="text-sm text-gray-500 mt-1">Min 8 characters with uppercase, lowercase, number, and special character</div>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="name">Full name *</label>
                        <input type="text" id="name" name="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['name'] ?? '') ?>" placeholder="Enter your full name">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="address">Address *</label>
                        <textarea id="address" name="address" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required rows="3" placeholder="Enter your complete address"><?= h($_POST['address'] ?? '') ?></textarea>
                    </div>
                </div>

                <div class="grid md:grid-cols-3 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="gender">Gender</label>
                        <select id="gender" name="gender" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">—</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="birthday">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['birthday'] ?? '') ?>">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['email'] ?? '') ?>" placeholder="your.email@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="contact_number">Contact number (PH) *</label>
                        <input type="tel" id="contact_number" name="contact_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required placeholder="09XXXXXXXXX" value="<?= h($_POST['contact_number'] ?? '') ?>">
                    </div>
                </div>

                <!-- Employment & Bank Details -->
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="bank_name">Bank name</label>
                        <input type="text" id="bank_name" name="bank_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['bank_name'] ?? '') ?>" placeholder="Bank of the Philippines, BPI, etc.">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="bank_account_number">Bank account number</label>
                        <input type="text" id="bank_account_number" name="bank_account_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['bank_account_number'] ?? '') ?>" placeholder="Your account number">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="card_holder_name">Card holder's name</label>
                        <input type="text" id="card_holder_name" name="card_holder_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['card_holder_name'] ?? '') ?>" placeholder="Name as it appears on your card">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="tin_number">TIN number</label>
                        <input type="text" id="tin_number" name="tin_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['tin_number'] ?? '') ?>" placeholder="Tax Identification Number">
                    </div>
                </div>

                <!-- Company Details (Premium Only) -->
                <div id="company-details" class="space-y-6">
                    <div class="grid md:grid-cols-2 gap-8">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_name">Company name</label>
                            <input type="text" id="company_name" name="company_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h($_POST['company_name'] ?? '') ?>" placeholder="Your company name">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_address">Company address</label>
                            <textarea id="company_address" name="company_address" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required rows="3" placeholder="Complete company address"><?= h($_POST['company_address'] ?? '') ?></textarea>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_phone">Company phone</label>
                            <input type="tel" id="company_phone" name="company_phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h($_POST['company_phone'] ?? '') ?>" placeholder="HR contact number">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="position">Position</label>
                            <input type="text" id="position" name="position" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h($_POST['position'] ?? '') ?>" placeholder="Your job position">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="monthly_earnings">Monthly earnings</label>
                            <input type="number" id="monthly_earnings" name="monthly_earnings" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" step="0.01" min="0" value="<?= h($_POST['monthly_earnings'] ?? '') ?>" placeholder="Your monthly income">
                        </div>
                    </div>
                </div>

                <!-- Document Uploads -->
                <div class="space-y-6">
                    <h2 class="text-xl font-semibold text-brand mb-4">Required Documents</h2>
                    <div class="grid md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="proof_of_billing">Proof of billing</label>
                            <input type="file" id="proof_of_billing" name="proof_of_billing" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required>
                            <div class="text-sm text-gray-500 mt-1">Utility bill, bank statement, etc.</div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="valid_id">Valid ID (primary)</label>
                            <input type="file" id="valid_id" name="valid_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required>
                            <div class="text-sm text-gray-500 mt-1">Driver's license, passport, national ID, etc.</div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="coe">COE (Certificate of Employment)</label>
                            <input type="file" id="coe" name="coe" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required>
                            <div class="text-sm text-gray-500 mt-1">From your current employer</div>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex items-center justify-between">
                    <a href="<?= h(app_url('login.php')) ?>" class="text-blue-600 hover:text-blue-700 transition text-sm">Already have an account? Sign in</a>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition">Create Account</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const basicCard = document.getElementById('basic-card');
    const premiumCard = document.getElementById('premium-card');
    const basicRadio = document.querySelector('input[name="account_type"][value="basic"]');
    const premiumRadio = document.querySelector('input[name="account_type"][value="premium"]');
    
    function updateCardSelection() {
        if (basicRadio.checked) {
            basicCard.classList.add('bg-blue-600', 'text-white', 'shadow-2xl', 'transform', 'scale-105');
            basicCard.classList.remove('border-gray-300');
            
            premiumCard.classList.remove('bg-blue-600', 'text-white', 'shadow-2xl', 'transform', 'scale-105');
            premiumCard.classList.add('border-gray-300');
            
            // Update text colors for selected Basic card
            basicCard.querySelector('h3').classList.remove('text-gray-700');
            basicCard.querySelector('h3').classList.add('text-white');
            basicCard.querySelector('p').classList.remove('text-gray-600');
            basicCard.querySelector('p').classList.add('text-blue-100');
            basicCard.querySelectorAll('li').forEach(li => {
                li.classList.remove('text-gray-600');
                li.classList.add('text-white');
            });
            basicCard.querySelector('span').classList.remove('font-medium');
            basicCard.querySelector('span').classList.add('font-bold');
            
            // Reset Premium card colors
            premiumCard.querySelector('h3').classList.remove('text-white');
            premiumCard.querySelector('h3').classList.add('text-gray-700');
            premiumCard.querySelector('p').classList.remove('text-blue-100');
            premiumCard.querySelector('p').classList.add('text-gray-600');
            premiumCard.querySelectorAll('li').forEach(li => {
                li.classList.remove('text-white');
                li.classList.add('text-gray-600');
            });
            premiumCard.querySelector('span').classList.remove('font-bold');
            premiumCard.querySelector('span').classList.add('font-medium');
            
        } else if (premiumRadio.checked) {
            premiumCard.classList.add('bg-blue-600', 'text-white', 'shadow-2xl', 'transform', 'scale-105');
            premiumCard.classList.remove('border-gray-300');
            
            basicCard.classList.remove('bg-blue-600', 'text-white', 'shadow-2xl', 'transform', 'scale-105');
            basicCard.classList.add('border-gray-300');
            
            // Update text colors for selected Premium card
            premiumCard.querySelector('h3').classList.remove('text-gray-700');
            premiumCard.querySelector('h3').classList.add('text-white');
            premiumCard.querySelector('p').classList.remove('text-gray-600');
            premiumCard.querySelector('p').classList.add('text-blue-100');
            premiumCard.querySelectorAll('li').forEach(li => {
                li.classList.remove('text-gray-600');
                li.classList.add('text-white');
            });
            premiumCard.querySelector('span').classList.remove('font-medium');
            premiumCard.querySelector('span').classList.add('font-bold');
            
            // Reset Basic card colors
            basicCard.querySelector('h3').classList.remove('text-white');
            basicCard.querySelector('h3').classList.add('text-gray-700');
            basicCard.querySelector('p').classList.remove('text-blue-100');
            basicCard.querySelector('p').classList.add('text-gray-600');
            basicCard.querySelectorAll('li').forEach(li => {
                li.classList.remove('text-white');
                li.classList.add('text-gray-600');
            });
            basicCard.querySelector('span').classList.remove('font-bold');
            basicCard.querySelector('span').classList.add('font-medium');
        }
    }
    
    // Make entire cards clickable
    basicCard.addEventListener('click', function() {
        basicRadio.checked = true;
        updateCardSelection();
    });
    
    premiumCard.addEventListener('click', function() {
        premiumRadio.checked = true;
        updateCardSelection();
    });
    
    // Handle radio button changes
    basicRadio.addEventListener('change', updateCardSelection);
    premiumRadio.addEventListener('change', updateCardSelection);
    
    // Initialize with current selection
    updateCardSelection();
});
</script>

<?php render_footer(); ?>
