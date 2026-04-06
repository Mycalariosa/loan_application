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
    echo '<div class="max-w-4xl mx-auto mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">' . h($e) . '</div>';
}
?>

<div class="max-w-4xl mx-auto">
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-8">
            <div class="mb-8">
                <h1 class="text-3xl font-bold text-brand mb-2">Your Profile</h1>
                <p class="text-gray-600">Update your personal and account information below.</p>
                <div class="mt-3 inline-flex items-center bg-blue-50 text-blue-700 px-4 py-2 rounded-lg">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Account type: <strong><?= h(ucfirst($u['account_type'])) ?></strong> (cannot be changed after registration)
                </div>
            </div>

            <form method="post" class="space-y-8">
                <!-- Personal Information Section -->
                <div>
                    <h2 class="text-xl font-semibold text-brand mb-6 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Personal Information
                    </h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="name">Full Name *</label>
                            <input type="text" id="name" name="name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="email">Email Address (read-only)</label>
                            <input type="email" id="email" class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50" value="<?= h($u['email']) ?>" disabled>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="address">Residential Address *</label>
                            <textarea id="address" name="address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required><?= h($form['address'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="gender">Gender</label>
                            <select id="gender" name="gender" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">— Select Gender —</option>
                                <option value="male" <?= ($form['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                                <option value="female" <?= ($form['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                                <option value="other" <?= ($form['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="birthday">Date of Birth *</label>
                            <input type="date" id="birthday" name="birthday" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['birthday'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="contact_number">Contact Number (PH) *</label>
                            <input type="tel" id="contact_number" name="contact_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['contact_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Banking Information Section -->
                <div>
                    <h2 class="text-xl font-semibold text-brand mb-6 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                        Banking Information
                    </h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="bank_name">Bank Name *</label>
                            <input type="text" id="bank_name" name="bank_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['bank_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="bank_account_number">Bank Account Number *</label>
                            <input type="text" id="bank_account_number" name="bank_account_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['bank_account_number'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="card_holder_name">Card Holder Name *</label>
                            <input type="text" id="card_holder_name" name="card_holder_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['card_holder_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="tin_number">TIN Number *</label>
                            <input type="text" id="tin_number" name="tin_number" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['tin_number'] ?? '') ?>">
                        </div>
                    </div>
                </div>

                <!-- Employment Information Section -->
                <div>
                    <h2 class="text-xl font-semibold text-brand mb-6 flex items-center">
                        <svg class="w-6 h-6 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                        Employment Information
                    </h2>
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_name">Company Name *</label>
                            <input type="text" id="company_name" name="company_name" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['company_name'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="position">Position</label>
                            <input type="text" id="position" name="position" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h($form['position'] ?? '') ?>">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_address">Company Address *</label>
                            <textarea id="company_address" name="company_address" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required><?= h($form['company_address'] ?? '') ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="company_phone">Company Phone *</label>
                            <input type="tel" id="company_phone" name="company_phone" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required value="<?= h($form['company_phone'] ?? '') ?>">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2" for="monthly_earnings">Monthly Earnings</label>
                            <input type="number" id="monthly_earnings" name="monthly_earnings" step="0.01" min="0" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= h((string) ($form['monthly_earnings'] ?? '')) ?>">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end pt-6 border-t border-gray-200">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-8 rounded-lg transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php render_footer();
