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

render_header('Registrations', $u);
flash_alert();
?>
<h1 class="h4 mb-3">Pending registrations</h1>
<?php foreach ($list as $r): ?>
<div class="card mb-3 p-3">
    <div class="row">
        <div class="col-md-8">
            <p><strong><?= h($r['name']) ?></strong> · <?= h($r['email']) ?> · <?= h($r['account_type']) ?></p>
            <p class="small mb-1"><?= h($r['address']) ?></p>
            <p class="small">TIN: <?= h($r['tin_number']) ?> · Company: <?= h($r['company_name']) ?></p>
        </div>
        <div class="col-md-4">
            <form method="post" class="vstack gap-2">
                <input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                <label class="small"><input type="checkbox" name="tin_verified"> TIN verified (BIR)</label>
                <label class="small"><input type="checkbox" name="company_verified"> Company / employment verified</label>
                <button type="submit" name="approve" class="btn btn-success btn-sm">Approve</button>
                <button type="submit" name="reject" class="btn btn-outline-danger btn-sm" onclick="return confirm('Reject this registration?');">Reject</button>
                <button type="submit" name="block_email" class="btn btn-outline-secondary btn-sm">Block email</button>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php if ($list === []): ?>
<p class="text-muted">No pending registrations.</p>
<?php endif; ?>
<?php render_footer();
