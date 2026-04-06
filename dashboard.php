<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

$u = require_approved_user();
if (($u['role'] ?? '') === 'admin') {
    header('Location: ' . app_url('admin/index.php'));
    exit;
}

$pdo = db();
$uid = (int) $u['id'];

$st = $pdo->prepare('SELECT COALESCE(SUM(principal_remaining),0) FROM loans WHERE user_id = ? AND status IN (\'active\',\'approved\')');
$st->execute([$uid]);
$outstanding = (float) $st->fetchColumn();

$st = $pdo->prepare('SELECT balance FROM savings_accounts WHERE user_id = ?');
$st->execute([$uid]);
$sav = $st->fetch();
$savingsBal = $sav ? (float) $sav['balance'] : 0.0;

render_header('Dashboard', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <!-- Welcome Section -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Welcome back, <?= h($u['name']) ?>!</h1>
        <p class="text-gray-600">Manage your loans and account settings from your personal dashboard.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <!-- Account Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                </div>
                <?php if ($u['verified_tag']): ?>
                    <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full">VERIFIED</span>
                <?php endif; ?>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Account Type</h3>
            <p class="text-2xl font-bold text-brand mb-4"><?= h(ucfirst($u['account_type'])) ?></p>
            <div class="space-y-1 text-sm text-gray-600">
                <p>Loan ceiling: <span class="font-semibold">₱<?= number_format((float) $u['current_loan_ceiling'], 2) ?></span></p>
                <p>Max term: <span class="font-semibold"><?= (int) $u['max_loan_term_months'] ?> months</span></p>
            </div>
        </div>

        <!-- Outstanding Loan Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Outstanding Loan</h3>
            <p class="text-2xl font-bold text-red-600 mb-4">₱<?= number_format($outstanding, 2) ?></p>
            <a href="<?= h(app_url('loans.php')) ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                View Details
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <!-- Savings Card (Premium Only) -->
        <?php if ($u['account_type'] === 'premium'): ?>
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Savings Balance</h3>
            <p class="text-2xl font-bold text-green-600 mb-4">₱<?= number_format($savingsBal, 2) ?></p>
            <a href="<?= h(app_url('savings.php')) ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                Manage Savings
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-brand mb-6">Quick Actions</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <a href="<?= h(app_url('loans.php')) ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Apply for Loan
            </a>
            <a href="<?= h(app_url('billing.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-lg transition text-center">
                View Billing
            </a>
            <a href="<?= h(app_url('profile.php')) ?>" class="bg-gray-100 hover:bg-gray-200 text-gray-800 font-semibold py-3 px-6 rounded-lg transition text-center">
                Update Profile
            </a>
            <?php if ($u['account_type'] === 'premium'): ?>
            <a href="<?= h(app_url('savings.php')) ?>" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Manage Savings
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php render_footer();
