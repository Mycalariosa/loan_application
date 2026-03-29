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
<div class="card p-4 mb-4">
    <h1 class="h4 mb-3">Create account</h1>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <div class="col-md-6">
            <label class="form-label">Account type *</label>
            <select name="account_type" class="form-select" required>
                <option value="">Choose…</option>
                <option value="basic">Basic — loans only</option>
                <option value="premium">Premium — loans, savings, money back (max <?= (int) MAX_PREMIUM_MEMBERS ?> members)</option>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Username * (min 6 characters; email allowed)</label>
            <input type="text" name="username" class="form-control" required minlength="6" value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Password *</label>
            <input type="password" name="password" class="form-control" required autocomplete="new-password">
            <div class="form-text">Min 8 characters, uppercase, lowercase, number, special character.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Full name *</label>
            <input type="text" name="name" class="form-control" required value="<?= h($_POST['name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Address *</label>
            <textarea name="address" class="form-control" rows="2" required><?= h($_POST['address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="other">Other</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Birthday *</label>
            <input type="date" name="birthday" class="form-control" required value="<?= h($_POST['birthday'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" required value="<?= h($_POST['email'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Contact number (PH) *</label>
            <input type="text" name="contact_number" class="form-control" required placeholder="09XXXXXXXXX" value="<?= h($_POST['contact_number'] ?? '') ?>">
        </div>
        <div class="col-12"><hr><h2 class="h6">Bank details *</h2></div>
        <div class="col-md-4">
            <label class="form-label">Bank name</label>
            <input type="text" name="bank_name" class="form-control" required value="<?= h($_POST['bank_name'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Bank account number</label>
            <input type="text" name="bank_account_number" class="form-control" required value="<?= h($_POST['bank_account_number'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Card holder's name</label>
            <input type="text" name="card_holder_name" class="form-control" required value="<?= h($_POST['card_holder_name'] ?? '') ?>">
            <div class="form-text">Ensure the card holder name matches your bank records to avoid transaction interruptions.</div>
        </div>
        <div class="col-md-6">
            <label class="form-label">TIN number *</label>
            <input type="text" name="tin_number" class="form-control" required value="<?= h($_POST['tin_number'] ?? '') ?>">
        </div>
        <div class="col-12"><hr><h2 class="h6">Employment *</h2></div>
        <div class="col-md-6">
            <label class="form-label">Company name</label>
            <input type="text" name="company_name" class="form-control" required value="<?= h($_POST['company_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Company address</label>
            <textarea name="company_address" class="form-control" rows="2" required><?= h($_POST['company_address'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label">Company phone (prefer HR / employment verification)</label>
            <input type="text" name="company_phone" class="form-control" required value="<?= h($_POST['company_phone'] ?? '') ?>">
            <div class="form-text">Use a number that reaches HR to confirm employment when possible.</div>
        </div>
        <div class="col-md-3">
            <label class="form-label">Position</label>
            <input type="text" name="position" class="form-control" value="<?= h($_POST['position'] ?? '') ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label">Monthly earnings</label>
            <input type="number" name="monthly_earnings" class="form-control" step="0.01" min="0" value="<?= h($_POST['monthly_earnings'] ?? '') ?>">
        </div>
        <div class="col-12"><hr><h2 class="h6">Uploads *</h2></div>
        <div class="col-md-4">
            <label class="form-label">Proof of billing</label>
            <input type="file" name="proof_of_billing" class="form-control" accept="image/*,application/pdf" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Valid ID (primary)</label>
            <input type="file" name="valid_id" class="form-control" accept="image/*,application/pdf" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">COE (Certificate of Employment)</label>
            <input type="file" name="coe" class="form-control" accept="image/*,application/pdf" required>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-primary">Submit registration</button>
            <a class="btn btn-link" href="<?= h(app_url('login.php')) ?>">Back to login</a>
        </div>
    </form>
</div>
<?php render_footer();
