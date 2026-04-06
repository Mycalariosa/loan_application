<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';
require_once dirname(__DIR__) . '/includes/money_back.php';

$u = require_admin();
$pdo = db();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute'])) {
    $year = (int) ($_POST['year'] ?? 0);
    $income = (float) ($_POST['total_income'] ?? 0);
    if ($year < 2000 || $year > 2100 || $income <= 0) {
        $errors[] = 'Enter a valid year and positive total income.';
    }
    $chk = $pdo->prepare('SELECT COUNT(*) FROM money_back_transactions WHERE year_year = ?');
    $chk->execute([$year]);
    if ((int) $chk->fetchColumn() > 0) {
        $errors[] = 'Money back for this year was already distributed (see records).';
    }
    if ($errors === []) {
        try {
            distribute_money_back($pdo, $year, $income);
            flash_set('ok', 'Company earnings saved and money back distributed to Premium members (2% pool / head).');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }
        header('Location: ' . app_url('admin/earnings.php'));
        exit;
    }
}

$earnings = $pdo->query('SELECT * FROM company_earnings ORDER BY year_year DESC')->fetchAll();
$mb = $pdo->query('SELECT * FROM money_back_transactions ORDER BY id DESC LIMIT 50')->fetchAll();

render_header('Earnings Management', $u);
flash_alert();
foreach ($errors as $e) {
    echo '<div class="max-w-6xl mx-auto mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg">' . h($e) . '</div>';
}
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Earnings Management</h1>
        <p class="text-gray-600">Manage company earnings and distribute money back to Premium members.</p>
    </div>

    <!-- Money Back Distribution Form -->
    <div class="bg-white rounded-2xl shadow-xl border border-gray-200 mb-8">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                <svg class="w-8 h-8 mr-3 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                Record Year & Distribute Money Back
            </h2>
            
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                <div class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mt-0.5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-blue-800 mb-1">Distribution Formula</h3>
                        <p class="text-sm text-blue-700">Formula per member: (Total income for the year × 2%) ÷ (number of Premium members). Credited to each Premium member's savings.</p>
                    </div>
                </div>
            </div>

            <form method="post" class="space-y-6">
                <input type="hidden" name="distribute" value="1">
                
                <div class="grid md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="year">Year</label>
                        <input type="number" id="year" name="year" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= (int) date('Y') ?>" required>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2" for="total_income">Total Company Income (₱)</label>
                        <input type="number" id="total_income" name="total_income" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" step="0.01" min="1" required>
                    </div>
                </div>
                
                <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white font-semibold py-3 px-8 rounded-lg transition">
                    <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    Save & Distribute Money Back
                </button>
            </form>
        </div>
    </div>

    <div class="grid lg:grid-cols-2 gap-8">
        <!-- Recorded Earnings -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Recorded Earnings
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Year</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Total Income</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($earnings as $e): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm font-medium"><?= (int) $e['year_year'] ?></td>
                                <td class="py-3 px-4 text-sm font-semibold text-green-600">₱<?= number_format((float) $e['total_income'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($earnings === []): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <p class="text-gray-500">No earnings recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Money Back Transactions -->
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
            <div class="p-8">
                <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                    <svg class="w-8 h-8 mr-3 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                    </svg>
                    Recent Money Back Transactions
                </h2>
                
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Year</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">User ID</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Amount</th>
                                <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Transaction ID</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($mb as $m): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-3 px-4 text-sm font-medium"><?= (int) $m['year_year'] ?></td>
                                <td class="py-3 px-4 text-sm"><?= (int) $m['user_id'] ?></td>
                                <td class="py-3 px-4 text-sm font-semibold text-purple-600">₱<?= number_format((float) $m['amount'], 2) ?></td>
                                <td class="py-3 px-4 text-sm font-mono"><?= h($m['transaction_id']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($mb === []): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        <p class="text-gray-500">No money back transactions yet.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php render_footer();
