<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$u = require_admin();
$pdo = db();
delete_expired_rejected_registrations($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'user' AND registration_status = 'pending'");
    $st->execute([$uid]);
    $row = $st->fetch();
    if (isset($_POST['approve']) && $row) {
        $tin = isset($_POST['tin_verified']) ? 1 : 0;
        $comp = isset($_POST['company_verified']) ? 1 : 0;
        $pdo->prepare(
            'UPDATE users SET registration_status = \'approved\', verified_tag = 1, tin_verified = ?, company_verified = ? WHERE id = ?'
        )->execute([$tin, $comp, $uid]);
        $pdo->prepare(
            'INSERT INTO notifications (user_id, email_to, subject, body) VALUES (?,?,?,?)'
        )->execute([
            $uid,
            $row['email'],
            'Registration approved',
            'Your registration has been approved. You can now sign in and use the system.',
        ]);
        flash_set('ok', 'Registration approved.');
    }
    if (isset($_POST['reject']) && $row) {
        $pdo->prepare(
            'UPDATE users SET registration_status = \'rejected\', rejected_at = NOW() WHERE id = ?'
        )->execute([$uid]);
        $pdo->prepare(
            'INSERT INTO notifications (user_id, email_to, subject, body) VALUES (?,?,?,?)'
        )->execute([
            $uid,
            $row['email'],
            'Registration update',
            'Your registration could not be approved. If documents were not valid, please contact support.',
        ]);
        flash_set('ok', 'Registration rejected. Record will be removed after 30 days.');
    }
    if (isset($_POST['block_email']) && $row) {
        $pdo->prepare('INSERT IGNORE INTO blocked_emails (email, reason) VALUES (?,?)')->execute([$row['email'], 'Blocked by admin']);
        flash_set('ok', 'Email blocked for future registrations.');
    }
    header('Location: ' . app_url('admin/registrations.php'));
    exit;
}

$list = $pdo->query(
    "SELECT * FROM users WHERE role = 'user' AND registration_status = 'pending' ORDER BY created_at ASC"
)->fetchAll();

render_header('Registration Management', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Registration Management</h1>
        <p class="text-gray-600">Review and approve pending user registrations.</p>
    </div>

    <?php if ($list === []): ?>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-8">
            <div class="text-center py-8">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">No Pending Registrations</h3>
                <p class="text-gray-600">All user registrations have been processed.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($list as $r): ?>
            <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
                <div class="p-8">
                    <div class="flex items-start justify-between mb-6">
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-gray-900 mb-2"><?= h($r['name']) ?></h3>
                            <div class="flex items-center gap-4 text-sm text-gray-600 mb-4">
                                <span class="flex items-center">
                                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    <?= h($r['email']) ?>
                                </span>
                                <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <?= h(ucfirst($r['account_type'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-500">
                            Applied: <?= h(format_sheet_date($r['created_at'])) ?>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-8 mb-6">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                </svg>
                                Personal Information
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Address:</span>
                                    <span class="font-medium"><?= h($r['address']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Contact:</span>
                                    <span class="font-medium"><?= h($r['contact_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Birthday:</span>
                                    <span class="font-medium"><?= h($r['birthday']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Gender:</span>
                                    <span class="font-medium"><?= h(ucfirst($r['gender'] ?? 'Not specified')) ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                                Employment Information
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Company:</span>
                                    <span class="font-medium"><?= h($r['company_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Position:</span>
                                    <span class="font-medium"><?= h($r['position'] ?? 'Not specified') ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Company Address:</span>
                                    <span class="font-medium"><?= h($r['company_address']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Company Phone:</span>
                                    <span class="font-medium"><?= h($r['company_phone']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Monthly Earnings:</span>
                                    <span class="font-medium">₱<?= number_format((float) $r['monthly_earnings'], 2) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid md:grid-cols-2 gap-8 mb-6">
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                Banking Information
                            </h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Bank Name:</span>
                                    <span class="font-medium"><?= h($r['bank_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Account Number:</span>
                                    <span class="font-medium"><?= h($r['bank_account_number']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Card Holder:</span>
                                    <span class="font-medium"><?= h($r['card_holder_name']) ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">TIN Number:</span>
                                    <span class="font-medium"><?= h($r['tin_number']) ?></span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Verification Status
                            </h4>
                            <form method="post" class="space-y-4">
                                <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                                
                                <div class="space-y-3">
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="checkbox" name="tin_verified" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">TIN verified (BIR)</span>
                                    </label>
                                    <label class="flex items-center space-x-3 cursor-pointer">
                                        <input type="checkbox" name="company_verified" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                                        <span class="text-sm font-medium text-gray-700">Company / employment verified</span>
                                    </label>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-6 border-t border-gray-200">
                        <button type="submit" name="approve" form="approval-form-<?= (int) $r['id'] ?>" 
                                class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Approve Registration
                        </button>
                        <button type="submit" name="reject" form="approval-form-<?= (int) $r['id'] ?>" 
                                onclick="return confirm('Are you sure you want to reject this registration?');"
                                class="bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Reject Registration
                        </button>
                        <button type="submit" name="block_email" form="approval-form-<?= (int) $r['id'] ?>" 
                                class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                            <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                            </svg>
                            Block Email
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php render_footer();
