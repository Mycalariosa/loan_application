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
$selectedAccountType = $_GET['type'] ?? '';
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

    // Profile picture is handled after registration (in user profile)
    $profilePicturePath = null;

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
                 bank_name, bank_account_number, card_holder_name, tin_number, company_name, company_address, company_phone, position, monthly_earnings, profile_picture_path,
                 registration_status, verified_tag, account_status, current_loan_ceiling, max_loan_term_months)
                 VALUES (\'user\',?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,\'pending\',0,\'active\',?,12)'
            );
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $earnVal = $earnings === '' ? null : (float) $earnings;
            
            // Profile picture will be null during registration (added later in user profile)
            $profilePicturePath = null;
            
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
                $profilePicturePath,
                LOAN_INITIAL_CEILING,
            ]);
            $uid = (int) $pdo->lastInsertId();
            
            // Create organized directory structure for user documents
            $userDir = UPLOAD_PATH . DIRECTORY_SEPARATOR . 'documents' . DIRECTORY_SEPARATOR . $uid;
            mkdir($userDir, 0775, true);

            $map = ['proof_of_billing' => 'proof_of_billing', 'valid_id' => 'valid_id', 'coe' => 'coe'];
            foreach ($map as $field => $docType) {
                $ext = pathinfo($uploaded[$field]['name'], PATHINFO_EXTENSION);
                $safe = $docType . '_' . bin2hex(random_bytes(8)) . '.' . preg_replace('/[^a-zA-Z0-9]/', '', $ext);
                $dest = $userDir . DIRECTORY_SEPARATOR . $safe;
                if (!move_uploaded_file($uploaded[$field]['tmp_name'], $dest)) {
                    throw new RuntimeException('File upload failed.');
                }
                $rel = 'documents/' . $uid . '/' . $safe;
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
                                <input type="radio" name="account_type" value="basic" class="mr-2" required <?php echo $selectedAccountType === 'basic' ? 'checked' : ''; ?>>
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
                                <input type="radio" name="account_type" value="premium" class="mr-2" required <?php echo $selectedAccountType === 'premium' ? 'checked' : ''; ?>>
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
                        <input type="date" id="birthday" name="birthday" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['birthday'] ?? '') ?>" max="<?= date('Y-m-d', strtotime('-18 years')) ?>" onchange="checkAgeValidation()">
                        <div id="age_validation" class="hidden mt-2 p-2 rounded text-sm"></div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">Email *</label>
                        <input type="email" id="email" name="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['email'] ?? '') ?>" placeholder="your.email@example.com">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="contact_number">Contact number (PH) *</label>
                        <input type="tel" id="contact_number" name="contact_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required placeholder="09123456789" value="<?= h($_POST['contact_number'] ?? '') ?>">
                        <div class="text-sm text-gray-500 mt-1">Format: 11-digit PH mobile number (09XXXXXXXXX)</div>
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
                        <input type="text" id="bank_account_number" name="bank_account_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['bank_account_number'] ?? '') ?>" placeholder="1234567890">
                        <div class="text-sm text-gray-500 mt-1">Format: 10-12 digit account number</div>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="card_holder_name">Card holder's name</label>
                        <input type="text" id="card_holder_name" name="card_holder_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['card_holder_name'] ?? '') ?>" placeholder="Name as it appears on your card">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="tin_number">TIN number</label>
                        <input type="text" id="tin_number" name="tin_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($_POST['tin_number'] ?? '') ?>" placeholder="000-000-000-000">
                        <div class="text-sm text-gray-500 mt-1">Format: 12-digit TIN (000-000-000-000)</div>
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
                            <input type="tel" id="company_phone" name="company_phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h($_POST['company_phone'] ?? '') ?>" placeholder="02-1234-5678">
                        <div class="text-sm text-gray-500 mt-1">Format: Landline or mobile number</div>
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
                            <input type="file" id="proof_of_billing" name="proof_of_billing" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required onchange="previewFile('proof_of_billing')">
                            <div class="text-sm text-gray-500 mt-1">Utility bill, bank statement, etc.</div>
                            <div id="proof_of_billing_preview" class="mt-3 hidden">
                                <div class="border rounded-lg p-2 bg-gray-50">
                                    <div id="proof_of_billing_preview_content" class="max-h-48 overflow-hidden"></div>
                                    <div class="text-xs text-gray-600 mt-1" id="proof_of_billing_info"></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="valid_id">Valid Government ID (primary) *</label>
                            <input type="file" id="valid_id" name="valid_id" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required onchange="previewFile('valid_id'); validateIDFile('valid_id')">
                            <div class="mb-2">
                                <button type="button" onclick="toggleIDList()" class="text-sm font-medium text-blue-800 hover:text-blue-600 transition flex items-center">
                                    <span id="idListToggle">Show Acceptable IDs</span>
                                    <svg id="idListArrow" class="w-4 h-4 ml-1 transform transition-transform" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                                <div id="idListContent" class="hidden mt-2 bg-blue-50 border border-blue-200 rounded p-3">
                                    <ul class="text-xs text-blue-700 space-y-1">
                                        <li>Driver's License (LTO)</li>
                                        <li>Passport (Department of Foreign Affairs)</li>
                                        <li>National ID (Philippine Identification System)</li>
                                        <li>UMID (Unified Multi-Purpose ID)</li>
                                        <li>SSS ID</li>
                                        <li>GSIS ID</li>
                                        <li>PRC License</li>
                                        <li>Postal ID</li>
                                    </ul>
                                </div>
                            </div>
                            <div class="text-sm text-gray-500 mb-2">Must show your photo, full name, and ID number clearly</div>
                            <div id="valid_id_validation" class="hidden mt-2 p-2 rounded text-sm"></div>
                            <div id="valid_id_preview" class="mt-3 hidden">
                                <div class="border rounded-lg p-2 bg-gray-50">
                                    <div id="valid_id_preview_content" class="max-h-48 overflow-hidden"></div>
                                    <div class="text-xs text-gray-600 mt-1" id="valid_id_info"></div>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="coe">COE (Certificate of Employment)</label>
                            <input type="file" id="coe" name="coe" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" accept="image/*,application/pdf" required onchange="previewFile('coe')">
                            <div class="text-sm text-gray-500 mt-1">From your current employer</div>
                            <div id="coe_preview" class="mt-3 hidden">
                                <div class="border rounded-lg p-2 bg-gray-50">
                                    <div id="coe_preview_content" class="max-h-48 overflow-hidden"></div>
                                    <div class="text-xs text-gray-600 mt-1" id="coe_info"></div>
                                </div>
                            </div>
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
    
    // Toggle ID list visibility
    window.toggleIDList = function() {
        const content = document.getElementById('idListContent');
        const toggle = document.getElementById('idListToggle');
        const arrow = document.getElementById('idListArrow');
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            toggle.textContent = 'Hide Acceptable IDs';
            arrow.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            toggle.textContent = 'Show Acceptable IDs';
            arrow.classList.remove('rotate-180');
        }
    };
    
    // Check age validation when user changes birthday
    window.checkAgeValidation = function() {
        const birthdayInput = document.getElementById('birthday');
        const validationDiv = document.getElementById('age_validation');
        
        if (birthdayInput.value) {
            const birthday = new Date(birthdayInput.value);
            const today = new Date();
            
            // Calculate age
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            
            // Check if user is at least 18
            if (age < 18) {
                validationDiv.innerHTML = `
                    <div class="text-red-600 text-xs mt-1">
                        Not legal age. Must be 18+ years old (Current: ${age})
                    </div>
                `;
                validationDiv.classList.remove('hidden');
                birthdayInput.classList.add('border-red-500');
            } else {
                validationDiv.classList.add('hidden');
                birthdayInput.classList.remove('border-red-500');
            }
        } else {
            validationDiv.classList.add('hidden');
            birthdayInput.classList.remove('border-red-500');
        }
    };
    
    // Form submission validation
    document.querySelector('form').addEventListener('submit', function(e) {
        const birthdayInput = document.getElementById('birthday');
        const validationDiv = document.getElementById('age_validation');
        
        if (birthdayInput.value) {
            const birthday = new Date(birthdayInput.value);
            const today = new Date();
            
            // Calculate age
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            
            // Check if user is at least 18
            if (age < 18) {
                e.preventDefault(); // Stop form submission
                validationDiv.innerHTML = `
                    <div class="text-red-600 text-xs mt-1">
                        Not legal age. Must be 18+ years old (Current: ${age})
                    </div>
                `;
                validationDiv.classList.remove('hidden');
                birthdayInput.classList.add('border-red-500');
                birthdayInput.focus();
                return false;
            } else {
                validationDiv.classList.add('hidden');
                birthdayInput.classList.remove('border-red-500');
            }
        }
    });
    
    // Age validation function
    window.validateAge = function() {
        const birthdayInput = document.getElementById('birthday');
        const validationDiv = document.getElementById('age_validation');
        
        if (birthdayInput.value) {
            const birthday = new Date(birthdayInput.value);
            const today = new Date();
            
            // Calculate age
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            
            // Clear previous validation
            validationDiv.classList.add('hidden');
            
            // Check if user is at least 18
            if (age < 18) {
                validationDiv.innerHTML = `
                    <div class="text-red-600 text-xs mt-1">
                        Not legal age. Must be 18+ years old (Current: ${age})
                    </div>
                `;
                validationDiv.classList.remove('hidden');
                birthdayInput.classList.add('border-red-500');
                birthdayInput.classList.remove('border-green-500');
            } else if (age >= 18 && age <= 120) {
                validationDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 text-green-800 p-2 rounded">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Age verified: ${age} years old
                        </div>
                    </div>
                `;
                validationDiv.classList.remove('hidden');
                birthdayInput.classList.add('border-green-500');
                birthdayInput.classList.remove('border-red-500');
            } else if (age > 120) {
                validationDiv.innerHTML = `
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-2 rounded">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            Please verify your birth date. Age: ${age} years
                        </div>
                    </div>
                `;
                validationDiv.classList.remove('hidden');
                birthdayInput.classList.add('border-yellow-500');
                birthdayInput.classList.remove('border-green-500', 'border-red-500');
            }
        } else {
            validationDiv.classList.add('hidden');
            birthdayInput.classList.remove('border-red-500', 'border-green-500', 'border-yellow-500');
        }
    };
    
    // ID file validation function
    window.validateIDFile = function(fieldId) {
        const fileInput = document.getElementById(fieldId);
        const validationDiv = document.getElementById(fieldId + '_validation');
        
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            const fileName = file.name.toLowerCase();
            
            // Clear previous validation
            validationDiv.classList.add('hidden');
            
            // Check for common ID indicators in filename
            const validIDPatterns = [
                'driver', 'license', 'lto', 'passport', 'national', 'id', 'umid', 
                'sss', 'gsis', 'prc', 'postal', 'philippine', 'identification'
            ];
            
            // Check for suspicious patterns (random pictures)
            const suspiciousPatterns = [
                'img', 'photo', 'picture', 'image', 'selfie', 'snapshot', 
                'download', 'screenshot', 'whatsapp', 'facebook', 'instagram'
            ];
            
            let isValid = false;
            let warningMessage = '';
            
            // Check if filename suggests it's a valid ID
            for (let pattern of validIDPatterns) {
                if (fileName.includes(pattern)) {
                    isValid = true;
                    break;
                }
            }
            
            // Check for suspicious patterns
            for (let pattern of suspiciousPatterns) {
                if (fileName.includes(pattern)) {
                    isValid = false;
                    warningMessage = 'This appears to be a regular photo, not a government ID. Please upload a valid government-issued ID.';
                    break;
                }
            }
            
            // If filename doesn't give clear indication, show warning
            if (!isValid && !warningMessage) {
                warningMessage = 'Please ensure this is a valid government ID (Driver\'s License, Passport, National ID, etc.)';
                isValid = false;
            }
            
            // Show validation result
            if (!isValid) {
                validationDiv.innerHTML = `
                    <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 p-2 rounded">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            ${warningMessage}
                        </div>
                    </div>
                `;
                validationDiv.classList.remove('hidden');
            } else {
                validationDiv.innerHTML = `
                    <div class="bg-green-50 border border-green-200 text-green-800 p-2 rounded">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            Valid government ID detected
                        </div>
                    </div>
                `;
                validationDiv.classList.remove('hidden');
            }
        } else {
            validationDiv.classList.add('hidden');
        }
    };
    
    // File preview function
    window.previewFile = function(fieldId) {
        const fileInput = document.getElementById(fieldId);
        const previewDiv = document.getElementById(fieldId + '_preview');
        const previewContent = document.getElementById(fieldId + '_preview_content');
        const fileInfo = document.getElementById(fieldId + '_info');
        
        if (fileInput.files && fileInput.files[0]) {
            const file = fileInput.files[0];
            const fileName = file.name;
            const fileSize = (file.size / 1024 / 1024).toFixed(2) + ' MB';
            
            // Show file info
            fileInfo.innerHTML = `<strong>${fileName}</strong> (${fileSize})`;
            
            // Clear previous preview
            previewContent.innerHTML = '';
            
            if (file.type.startsWith('image/')) {
                // Show image preview
                const img = document.createElement('img');
                img.src = URL.createObjectURL(file);
                img.className = 'max-w-full h-auto mx-auto rounded';
                img.style.maxHeight = '192px';
                img.style.objectFit = 'contain';
                previewContent.appendChild(img);
            } else if (file.type === 'application/pdf') {
                // Show PDF preview with icon
                const pdfDiv = document.createElement('div');
                pdfDiv.className = 'text-center py-8';
                pdfDiv.innerHTML = `
                    <svg class="w-16 h-16 mx-auto text-red-500 mb-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm font-medium text-gray-700">PDF Document</p>
                    <p class="text-xs text-gray-500">Click to view if supported</p>
                `;
                previewContent.appendChild(pdfDiv);
                
                // Add click to open PDF
                pdfDiv.style.cursor = 'pointer';
                pdfDiv.onclick = function() {
                    window.open(URL.createObjectURL(file), '_blank');
                };
            } else {
                // Show generic file icon
                const fileDiv = document.createElement('div');
                fileDiv.className = 'text-center py-8';
                fileDiv.innerHTML = `
                    <svg class="w-16 h-16 mx-auto text-gray-400 mb-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z" clip-rule="evenodd"></path>
                    </svg>
                    <p class="text-sm font-medium text-gray-700">File</p>
                    <p class="text-xs text-gray-500">${file.type || 'Unknown type'}</p>
                `;
                previewContent.appendChild(fileDiv);
            }
            
            // Show preview container
            previewDiv.classList.remove('hidden');
        } else {
            // Hide preview if no file selected
            previewDiv.classList.add('hidden');
        }
    };
});
</script>

<?php render_footer(); ?>
