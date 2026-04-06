<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$u = require_admin();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    $uid = (int) ($_POST['user_id'] ?? 0);
    $at = $_POST['account_type'] ?? '';
    $as = $_POST['account_status'] ?? '';
    $ceiling = (float) ($_POST['current_loan_ceiling'] ?? LOAN_INITIAL_CEILING);
    $maxTerm = (int) ($_POST['max_loan_term_months'] ?? 12);
    if (!in_array($at, ['basic', 'premium'], true) || !in_array($as, ['active', 'disabled'], true)) {
        flash_set('error', 'Invalid values.');
    } else {
        $st = $pdo->prepare('SELECT account_type FROM users WHERE id = ? AND role = \'user\'');
        $st->execute([$uid]);
        $prev = $st->fetchColumn();
        if ($prev === false) {
            flash_set('error', 'User not found.');
            header('Location: ' . app_url('admin/users.php'));
            exit;
        }
        if ($prev === 'basic' && $at === 'premium' && count_premium_users($pdo) >= MAX_PREMIUM_MEMBERS) {
            flash_set('error', 'Premium member limit reached.');
            header('Location: ' . app_url('admin/users.php'));
            exit;
        }
        $pdo->prepare(
            'UPDATE users SET account_type = ?, account_status = ?, current_loan_ceiling = ?, max_loan_term_months = ? WHERE id = ? AND role = \'user\''
        )->execute([$at, $as, min(LOAN_MAX_CEILING, max(LOAN_INITIAL_CEILING, $ceiling)), min(32, max(12, $maxTerm)), $uid]);
        flash_set('ok', 'User updated.');
    }
    header('Location: ' . app_url('admin/users.php'));
    exit;
}

$users = $pdo->query(
    "SELECT id, username, name, email, account_type, account_status, registration_status, current_loan_ceiling, max_loan_term_months FROM users WHERE role = 'user' ORDER BY id DESC"
)->fetchAll();

render_header('Users Management', $u);
flash_alert();
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-brand mb-2">Users Management</h1>
        <p class="text-gray-600">Manage all user accounts, account types, and loan settings.</p>
    </div>

    <div class="bg-white rounded-2xl shadow-xl border border-gray-200">
        <div class="p-8">
            <h2 class="text-2xl font-bold text-brand mb-6 flex items-center">
                <svg class="w-8 h-8 mr-3 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                All Users
            </h2>
            
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">ID</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">User Information</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Account Type</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Status</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Registration</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Activity</th>
                            <th class="text-left py-3 px-4 text-sm font-semibold text-gray-700">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $row): ?>
                            <?php
                            $uid = (int) $row['id'];
                            $c1 = $pdo->prepare('SELECT COUNT(*) FROM loans WHERE user_id = ?');
                            $c1->execute([$uid]);
                            $lc = (int) $c1->fetchColumn();
                            $c2 = $pdo->prepare('SELECT COUNT(*) FROM savings_transactions WHERE user_id = ?');
                            $c2->execute([$uid]);
                            $sc = (int) $c2->fetchColumn();
                            $c3 = $pdo->prepare('SELECT COUNT(*) FROM billing_statements WHERE user_id = ?');
                            $c3->execute([$uid]);
                            $bc = (int) $c3->fetchColumn();
                            ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="py-4 px-4 text-sm font-medium"><?= $uid ?></td>
                                <td class="py-4 px-4">
                                    <div class="font-medium text-gray-900"><?= h($row['username']) ?></div>
                                    <div class="text-sm text-gray-500"><?= h($row['email']) ?></div>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php 
                                        $typeColor = 'bg-gray-100 text-gray-800';
                                        if ($row['account_type'] === 'premium') $typeColor = 'bg-purple-100 text-purple-800';
                                        elseif ($row['account_type'] === 'basic') $typeColor = 'bg-blue-100 text-blue-800';
                                        echo $typeColor;
                                        ?>">
                                        <?= h(ucfirst($row['account_type'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php 
                                        $statusColor = 'bg-gray-100 text-gray-800';
                                        if ($row['account_status'] === 'active') $statusColor = 'bg-green-100 text-green-800';
                                        elseif ($row['account_status'] === 'disabled') $statusColor = 'bg-red-100 text-red-800';
                                        echo $statusColor;
                                        ?>">
                                        <?= h(ucfirst($row['account_status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                        <?php 
                                        $regColor = 'bg-gray-100 text-gray-800';
                                        if ($row['registration_status'] === 'approved') $regColor = 'bg-green-100 text-green-800';
                                        elseif ($row['registration_status'] === 'pending') $regColor = 'bg-yellow-100 text-yellow-800';
                                        elseif ($row['registration_status'] === 'rejected') $regColor = 'bg-red-100 text-red-800';
                                        echo $regColor;
                                        ?>">
                                        <?= h(ucfirst($row['registration_status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="flex gap-4 text-sm">
                                        <div>
                                            <span class="font-medium"><?= $lc ?></span>
                                            <span class="text-gray-500"> loans</span>
                                        </div>
                                        <div>
                                            <span class="font-medium"><?= $sc ?></span>
                                            <span class="text-gray-500"> savings</span>
                                        </div>
                                        <div>
                                            <span class="font-medium"><?= $bc ?></span>
                                            <span class="text-gray-500"> billing</span>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-4 px-4">
                                    <button onclick="toggleEditForm('user<?= $uid ?>')" 
                                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition text-sm">
                                        Edit
                                    </button>
                                </td>
                            </tr>
                            <tr id="user<?= $uid ?>" class="hidden">
                                <td colspan="7" class="bg-gray-50">
                                    <div class="p-6">
                                        <form method="post" class="space-y-6">
                                            <input type="hidden" name="user_id" value="<?= $uid ?>">
                                            
                                            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Account Type</label>
                                                    <select name="account_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        <option value="basic" <?= $row['account_type'] === 'basic' ? 'selected' : '' ?>>Basic</option>
                                                        <option value="premium" <?= $row['account_type'] === 'premium' ? 'selected' : '' ?>>Premium</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Account Status</label>
                                                    <select name="account_status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                                        <option value="active" <?= $row['account_status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                        <option value="disabled" <?= $row['account_status'] === 'disabled' ? 'selected' : '' ?>>Disabled</option>
                                                    </select>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Loan Ceiling (₱)</label>
                                                    <input type="number" name="current_loan_ceiling" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" value="<?= (float) $row['current_loan_ceiling'] ?>">
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-semibold text-gray-700 mb-2">Max Loan Term (months)</label>
                                                    <input type="number" name="max_loan_term_months" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" min="12" max="32" value="<?= (int) $row['max_loan_term_months'] ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="flex gap-3">
                                                <button type="submit" name="save_user" class="bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-6 rounded-lg transition">
                                                    Save Changes
                                                </button>
                                                <button type="button" onclick="toggleEditForm('user<?= $uid ?>')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold py-2 px-6 rounded-lg transition">
                                                    Cancel
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleEditForm(userId) {
    const formRow = document.getElementById(userId);
    formRow.classList.toggle('hidden');
}
</script>
<?php render_footer();
