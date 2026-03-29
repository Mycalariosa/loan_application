<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$u = require_approved_user();
if (($u['role'] ?? '') === 'admin') {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

$pdo = db();
$uid = (int) $u['id'];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim((string) ($_POST['name'] ?? ''));
    $address = trim((string) ($_POST['address'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $birthday = trim((string) ($_POST['birthday'] ?? ''));
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

    if ($name === '' || $address === '' || $birthday === '') {
        $errors[] = 'Name, address, and birthday are required.';
    }
    if (!valid_ph_phone($contact)) {
        $errors[] = 'Invalid Philippines contact number.';
    }
    if ($bankName === '' || $bankAcct === '' || $cardHolder === '' || $tin === '') {
        $errors[] = 'Bank and TIN fields are required.';
    }
    if ($companyName === '' || $companyAddr === '' || $companyPhone === '') {
        $errors[] = 'Company fields are required.';
    }

    if ($errors === []) {
        $age = calculate_age($birthday);
        $earnVal = $earnings === '' ? null : (float) $earnings;
        $pdo->prepare(
            'UPDATE users SET name=?, address=?, gender=?, birthday=?, age=?, contact_number=?,
             bank_name=?, bank_account_number=?, card_holder_name=?, tin_number=?,
             company_name=?, company_address=?, company_phone=?, position=?, monthly_earnings=?
             WHERE id=?'
        )->execute([
            $name, $address, $gender ?: null, $birthday, $age, normalize_ph_phone($contact),
            $bankName, $bankAcct, $cardHolder, $tin,
            $companyName, $companyAddr, $companyPhone, $position ?: null, $earnVal,
            $uid,
        ]);
        flash_set('ok', 'Profile updated.');
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$uid]);
        $_SESSION['user'] = $st->fetch();
        unset($_SESSION['user']['password_hash']);
        header('Location: ' . app_url('profile.php'));
        exit;
    }
}

$u = require_approved_user();
$form = array_merge($u, $_POST);

render_header('Profile', $u);
flash_alert();
foreach ($errors as $e) {
    echo '<div class="alert alert-danger">' . h($e) . '</div>';
}
?>
<div class="card p-4">
    <h1 class="h5 mb-3">Your profile</h1>
    <p class="text-muted small">Account type <strong><?= h($u['account_type']) ?></strong> cannot be changed after registration.</p>
    <form method="post" class="row g-3">
        <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required value="<?= h($form['name'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" required><?= h($form['address'] ?? '') ?></textarea></div>
        <div class="col-md-4">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select">
                <option value="">—</option>
                <option value="male" <?= ($form['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                <option value="female" <?= ($form['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                <option value="other" <?= ($form['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
            </select>
        </div>
        <div class="col-md-4"><label class="form-label">Birthday</label><input type="date" name="birthday" class="form-control" required value="<?= h($form['birthday'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Email (read-only)</label><input type="email" class="form-control" value="<?= h($u['email']) ?>" disabled></div>
        <div class="col-md-6"><label class="form-label">Contact (PH)</label><input type="text" name="contact_number" class="form-control" required value="<?= h($form['contact_number'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Bank name</label><input type="text" name="bank_name" class="form-control" required value="<?= h($form['bank_name'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Bank account #</label><input type="text" name="bank_account_number" class="form-control" required value="<?= h($form['bank_account_number'] ?? '') ?>"></div>
        <div class="col-md-4"><label class="form-label">Card holder name</label><input type="text" name="card_holder_name" class="form-control" required value="<?= h($form['card_holder_name'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">TIN</label><input type="text" name="tin_number" class="form-control" required value="<?= h($form['tin_number'] ?? '') ?>"></div>
        <div class="col-md-6"><label class="form-label">Company name</label><input type="text" name="company_name" class="form-control" required value="<?= h($form['company_name'] ?? '') ?>"></div>
        <div class="col-12"><label class="form-label">Company address</label><textarea name="company_address" class="form-control" rows="2" required><?= h($form['company_address'] ?? '') ?></textarea></div>
        <div class="col-md-6"><label class="form-label">Company phone</label><input type="text" name="company_phone" class="form-control" required value="<?= h($form['company_phone'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Position</label><input type="text" name="position" class="form-control" value="<?= h($form['position'] ?? '') ?>"></div>
        <div class="col-md-3"><label class="form-label">Monthly earnings</label><input type="number" name="monthly_earnings" class="form-control" step="0.01" value="<?= h((string) ($form['monthly_earnings'] ?? '')) ?>"></div>
        <div class="col-12"><button class="btn btn-primary" type="submit">Save</button></div>
    </form>
</div>
<?php render_footer();
