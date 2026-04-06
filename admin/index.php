<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$u = require_admin();
$pdo = db();

$pendingReg = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='user' AND registration_status='pending'")->fetchColumn();
$pendingLoans = (int) $pdo->query("SELECT COUNT(*) FROM loans WHERE status='pending'")->fetchColumn();
$pendingSav = (int) $pdo->query("SELECT COUNT(*) FROM savings_transactions WHERE status='pending' AND category='withdrawal'")->fetchColumn();

render_header('Admin Dashboard', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <!-- Welcome Section -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Admin Dashboard</h1>
        <p class="text-gray-600">Manage loan applications, user registrations, and system operations.</p>
    </div>

    <!-- Stats Grid -->
    <div class="grid md:grid-cols-3 gap-6 mb-8">
        <!-- Pending Registrations Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <span class="bg-yellow-100 text-yellow-800 text-xs font-bold px-3 py-1 rounded-full">PENDING</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Pending Registrations</h3>
            <p class="text-4xl font-bold text-yellow-600 mb-4"><?= $pendingReg ?></p>
            <a href="<?= h(app_url('admin/registrations.php')) ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                Review Applications
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <!-- Pending Loans Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <span class="bg-blue-100 text-blue-800 text-xs font-bold px-3 py-1 rounded-full">REVIEW</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Pending Loans</h3>
            <p class="text-4xl font-bold text-blue-600 mb-4"><?= $pendingLoans ?></p>
            <a href="<?= h(app_url('admin/loans.php')) ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                Review Loans
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>

        <!-- Pending Withdrawals Card -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6 hover:shadow-2xl transition">
            <div class="flex items-center justify-between mb-4">
                <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
                <span class="bg-green-100 text-green-800 text-xs font-bold px-3 py-1 rounded-full">WITHDRAWALS</span>
            </div>
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Pending Withdrawals</h3>
            <p class="text-4xl font-bold text-green-600 mb-4"><?= $pendingSav ?></p>
            <a href="<?= h(app_url('admin/savings.php')) ?>" class="inline-flex items-center text-blue-600 hover:text-blue-700 font-medium text-sm">
                Process Withdrawals
                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </a>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 p-6">
        <h2 class="text-xl font-bold text-brand mb-6">Admin Quick Actions</h2>
        <div class="grid md:grid-cols-4 gap-4">
            <a href="<?= h(app_url('admin/registrations.php')) ?>" class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Review Registrations
            </a>
            <a href="<?= h(app_url('admin/loans.php')) ?>" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Manage Loans
            </a>
            <a href="<?= h(app_url('admin/savings.php')) ?>" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Process Savings
            </a>
            <a href="<?= h(app_url('admin/users.php')) ?>" class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-3 px-6 rounded-lg transition text-center">
                Manage Users
            </a>
        </div>
    </div>

    <!-- Info Notice -->
    <div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="flex items-start">
            <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <h3 class="text-sm font-semibold text-blue-800 mb-1">Admin Notice</h3>
                <p class="text-sm text-blue-700">Admins cannot submit loan applications or personal savings deposits/withdrawals from the member UI. Use the admin panels above to manage all user requests.</p>
            </div>
        </div>
    </div>
</div>
<?php render_footer();
