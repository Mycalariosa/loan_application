<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

session_boot();

if (current_user()) {
    header('Location: ' . app_url('/'));
    exit;
}

$error = '';
$email = $_COOKIE['admin_username'] ?? '';
$remember = !empty($email);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!empty($email) && !empty($password)) {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id, username, email, password_hash, role FROM users WHERE (email = ? OR username = ?) AND role = 'admin' LIMIT 1");
        $stmt->execute([$email, $email]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($password, $admin['password_hash'])) {
            login_user($admin);

            if ($remember) {
                setcookie('admin_username', $admin['username'], time() + (30 * 24 * 60 * 60), '/', '', false, true);
            } else {
                setcookie('admin_username', '', time() - 3600, '/', '', false, true);
            }

            header('Location: ' . app_url('admin/index.php'));
            exit;
        } else {
            $error = 'Invalid email or password';
        }
    } else {
        $error = 'Please fill in all fields';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Alpha Loans</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-dark: #0a1931; }
        body { font-family: 'Inter', sans-serif; }
        .bg-brand { background-color: var(--brand-dark); }
        .text-brand { color: var(--brand-dark); }
    </style>
</head>
<body class="bg-gray-50">

    <div class="min-h-screen flex items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <a href="<?php echo app_url('index.php'); ?>" class="text-2xl font-extrabold tracking-tighter inline-block">
                    ALPHA<span class="text-blue-500">LOANS</span>
                </a>
                <p class="text-gray-500 text-sm mt-2">Admin Portal</p>
            </div>

            <div class="bg-white rounded-2xl shadow-2xl p-8 border border-gray-200">
                <h1 class="text-3xl font-bold text-brand mb-2">Admin Login</h1>
                <p class="text-gray-500 text-sm mb-8">Enter your credentials to access the admin panel</p>

                <?php if (!empty($error)): ?>
                <div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                    <?php echo htmlspecialchars($error, ENT_QUOTES); ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6">
                    <div>
                        <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">Email or Username</label>
                        <input 
                            type="text" 
                            id="email" 
                            name="email" 
                            required 
                            placeholder="Enter your email or username" 
                            value="<?php echo htmlspecialchars($email, ENT_QUOTES); ?>"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                        >
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            required 
                            placeholder="Enter your password" 
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition"
                        >
                    </div>

                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                id="remember" 
                                name="remember" 
                                class="w-4 h-4 text-blue-600 rounded"
                                <?php echo $remember ? 'checked' : ''; ?>
                            >
                            <label for="remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                        </div>
                        <a href="<?php echo app_url('forgot_password.php'); ?>" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                            Forgot Password?
                        </a>
                    </div>

                    <button 
                        type="submit" 
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition shadow-lg"
                    >
                        LOGIN
                    </button>
                </form>
            </div>

            <div class="text-center mt-8">
                <p class="text-xs text-gray-400">
                    <a href="<?php echo app_url('index.php'); ?>" class="text-blue-600 hover:text-blue-700">← Back to Home</a>
                </p>
                <p class="text-xs text-gray-400 mt-4">
                    &copy; <?php echo date('Y'); ?> Alpha Loans Philippines. Licensed Lending System.
                </p>
            </div>
        </div>
    </div>

</body>
</html>