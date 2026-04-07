<?php
declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/auth.php';

session_boot();

if (current_user()) {
    $u = current_user();
    $dest = (($u['role'] ?? '') === 'admin') ? app_url('admin/index.php') : app_url('dashboard.php');
    header('Location: ' . $dest);
    exit;
}

$admin_username = $_COOKIE['admin_username'] ?? '';
$admin_remember = !empty($admin_username);
?>
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alpha Loans | Professional Lending & Savings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --brand-dark: #0a1931; }
        body { font-family: 'Inter', sans-serif; }
        .bg-brand { background-color: var(--brand-dark); }
        .text-brand { color: var(--brand-dark); }
        section { scroll-margin-top: 5rem; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <header id="home" class="bg-brand text-white min-h-[90vh] flex flex-col relative overflow-hidden">
        <nav class="flex justify-between items-center px-6 md:px-10 py-8 z-50">
            <div class="text-2xl font-extrabold tracking-tighter cursor-pointer" onclick="window.scrollTo(0,0)">
                ALPHA<span class="text-blue-500">LOANS</span>
            </div>
            
            <div class="hidden lg:flex items-center gap-8 text-[11px] uppercase tracking-[0.2em] font-semibold">
                <a href="#home" class="hover:text-blue-400 transition">Home</a>
                <a href="#loan-features" class="hover:text-blue-400 transition">Loan Mechanics</a>
                <a href="#membership" class="hover:text-blue-400 transition">Membership</a>
                <a href="#about-us" class="hover:text-blue-400 transition">About</a>
                
                <span class="h-4 w-[1px] bg-gray-600"></span>

                <button onclick="document.getElementById('adminModal').classList.remove('hidden')" class="text-gray-400 hover:text-white transition cursor-pointer">Admin Portal</button>
                <a href="<?php echo app_url('login.php'); ?>" class="bg-white text-brand px-6 py-2 rounded-full font-bold hover:bg-blue-50 transition shadow-xl">LOGIN</a>
            </div>
        </nav>

        <div class="flex-grow flex flex-col justify-center px-10 md:px-24 z-10">
            <h1 class="text-6xl md:text-8xl font-black leading-none mb-6 tracking-tighter">
                Smart.<br>Fast.<br><span class="text-blue-500 text-outline">Transparent.</span>
            </h1>
            <p class="max-w-2xl text-lg text-gray-400 mb-10 leading-relaxed">
                Get an initial loan of up to *₱10,000* with a fixed *3% interest* deducted upfront. 
                Choose flexible terms from 1 to 12 months and grow your limit up to *₱50,000* through consistent on-time payments.
            </p>
            <div class="flex flex-wrap gap-6">
                <a href="<?php echo app_url('register.php'); ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-12 py-4 rounded-full font-bold transition transform hover:scale-105 shadow-2xl">
                    APPLY FOR A LOAN
                </a>
                <div class="flex items-center gap-3 text-sm font-bold tracking-widest text-gray-300">
                    <span class="w-8 h-[1px] bg-gray-500"></span>
                    SECURE & VERIFIED
                </div>
            </div>
        </div>

        <div class="absolute bottom-10 right-10 border border-gray-700 bg-brand/50 backdrop-blur-md p-6 rounded-2xl z-10 hidden md:block">
            <p class="text-xs text-blue-400 font-bold uppercase mb-2">Current Rate</p>
            <p class="text-3xl font-bold">3.0% <span class="text-sm font-normal text-gray-400">Fixed</span></p>
        </div>
    </header>

    <section id="loan-features" class="py-24 px-10 md:px-24 bg-white relative z-20 -mt-10 rounded-t-[3rem] shadow-2xl">
        <div class="grid lg:grid-cols-2 gap-16 items-center">
            <div>
                <h4 class="text-blue-600 font-bold uppercase tracking-widest text-xs mb-4">Loan Mechanics</h4>
                <h2 class="text-4xl md:text-5xl font-bold text-brand mb-8 leading-tight">Borrow with Confidence.</h2>
                <div class="space-y-6">
                    <div class="flex gap-4">
                        <div class="bg-blue-50 p-3 rounded-lg text-blue-600 font-bold">01</div>
                        <div>
                            <h5 class="font-bold text-lg">Initial Loan Limit</h5>
                            <p class="text-gray-500">Borrow between ₱5,000 to ₱10,000 initially (in increments of ₱1,000).</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="bg-blue-50 p-3 rounded-lg text-blue-600 font-bold">02</div>
                        <div>
                            <h5 class="font-bold text-lg">Instant Interest Deduction</h5>
                            <p class="text-gray-500">A flat 3% interest is charged and deducted immediately from your released amount.</p>
                        </div>
                    </div>
                    <div class="flex gap-4">
                        <div class="bg-blue-50 p-3 rounded-lg text-blue-600 font-bold">03</div>
                        <div>
                            <h5 class="font-bold text-lg">Payment Terms</h5>
                            <p class="text-gray-500">Choose from 1, 3, 6, or 12 months. Pay on time to increase your limit up to ₱50,000.</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-100 p-8 rounded-3xl border border-gray-200">
                <h4 class="font-bold text-brand mb-6">Quick Calculator Preview</h4>
                <div class="space-y-4">
                    <div class="flex justify-between border-b pb-2"><span>Borrowed Amount:</span> <span class="font-bold">₱10,000.00</span></div>
                    <div class="flex justify-between border-b pb-2 text-red-500"><span>3% Interest (Deducted):</span> <span class="font-bold">- ₱300.00</span></div>
                    <div class="flex justify-between pt-2 text-xl font-bold text-blue-600"><span>Released Amount:</span> <span>₱9,700.00</span></div>
                    <p class="text-[10px] text-gray-400 mt-4 italic">*Release is subject to Admin approval and document verification (COE, Billing, ID).</p>
                </div>
            </div>
        </div>
    </section>

    <section id="membership" class="py-24 px-10 md:px-24 bg-brand text-white">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold mb-4">Choose Your Membership</h2>
            <p class="text-gray-400">Unlock advanced features with our Premium tier.</p>
        </div>
        <div class="grid md:grid-cols-2 gap-8 max-w-5xl mx-auto">
            <div class="border border-gray-700 p-10 rounded-3xl hover:border-blue-500 transition">
                <h3 class="text-2xl font-bold mb-2">Basic</h3>
                <p class="text-blue-400 text-sm mb-6">Unlimited Slots Available</p>
                <ul class="space-y-4 text-gray-300 mb-10">
                    <li class="flex items-center gap-2">✅ Standard Loan Access</li>
                    <li class="flex items-center gap-2">✅ Monthly Billing Summary</li>
                    <li class="flex items-center gap-2 opacity-30">❌ Savings Account</li>
                    <li class="flex items-center gap-2 opacity-30">❌ Money Back Dividends</li>
                </ul>
                <button class="w-full py-3 border border-white rounded-xl font-bold hover:bg-white hover:text-brand transition">Join Basic</button>
            </div>
            <div class="bg-blue-600 p-10 rounded-3xl shadow-2xl transform scale-105">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-2xl font-bold">Premium</h3>
                    <span class="bg-white text-blue-600 text-[10px] font-black px-2 py-1 rounded">50 SLOTS ONLY</span>
                </div>
                <p class="text-blue-100 text-sm mb-6">Exclusive Benefits</p>
                <ul class="space-y-4 text-white mb-10 font-medium">
                    <li class="flex items-center gap-2">✅ All Basic Features</li>
                    <li class="flex items-center gap-2">✅ **Savings Account (Max 100k)**</li>
                    <li class="flex items-center gap-2">✅ **2% Yearly Company Dividends**</li>
                    <li class="flex items-center gap-2">✅ Earned Money Back</li>
                </ul>
                <button class="w-full py-3 bg-white text-blue-600 rounded-xl font-bold hover:bg-blue-50 transition">Get Premium</button>
            </div>
        </div>
    </section>

    <section id="about-us" class="py-24 px-10 md:px-24 bg-white">
        <div class="max-w-4xl mx-auto text-center">
            <h2 class="text-3xl font-bold text-brand mb-12">Application Requirements</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-8">
                <div class="p-4">
                    <div class="text-blue-600 text-3xl mb-2">📄</div>
                    <p class="text-xs font-bold uppercase">COE</p>
                </div>
                <div class="p-4">
                    <div class="text-blue-600 text-3xl mb-2">🪪</div>
                    <p class="text-xs font-bold uppercase">Valid Primary ID</p>
                </div>
                <div class="p-4">
                    <div class="text-blue-600 text-3xl mb-2">🏠</div>
                    <p class="text-xs font-bold uppercase">Proof of Billing</p>
                </div>
                <div class="p-4">
                    <div class="text-blue-600 text-3xl mb-2">🏦</div>
                    <p class="text-xs font-bold uppercase">Bank Details</p>
                </div>
            </div>
            <p class="mt-12 text-sm text-gray-400 italic">
                Note: All registrations are "Pending" until Admin verifies your TIN with BIR and confirms employment with your HR.
            </p>
        </div>
    </section>

    <footer class="bg-brand text-white py-12 px-10 text-center border-t border-gray-800">
        <p class="text-sm opacity-50">&copy; <?php echo date('Y'); ?> Alpha Loans Philippines. Licensed Lending System.</p>
    </footer>

    <!-- Admin Login Modal -->
    <div id="adminModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-md w-full p-8 animate-fade-in">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-bold text-brand">Admin Login</h2>
                <button onclick="document.getElementById('adminModal').classList.add('hidden')" class="text-gray-400 hover:text-gray-600 text-2xl">×</button>
            </div>
            
            <form id="adminLoginForm" method="POST" action="<?php echo app_url('admin.php'); ?>" class="space-y-4">
                <div>
                    <label for="admin_email" class="block text-sm font-semibold text-gray-700 mb-2">Email or Username</label>
                    <input type="text" id="admin_email" name="email" required placeholder="Enter your email or username" value="<?php echo htmlspecialchars($admin_username, ENT_QUOTES); ?>" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                </div>

                <div>
                    <label for="admin_password" class="block text-sm font-semibold text-gray-700 mb-2">Password</label>
                    <input type="password" id="admin_password" name="password" required placeholder="Enter your password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition">
                </div>

                <div class="flex items-center">
                    <input type="checkbox" id="admin_remember" name="remember" class="w-4 h-4 text-blue-600 rounded" <?php echo $admin_remember ? 'checked' : ''; ?>>
                    <label for="admin_remember" class="ml-2 text-sm text-gray-600">Remember me</label>
                </div>

                <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg transition transform hover:scale-105 shadow-lg">
                    LOGIN
                </button>

                <div class="text-center">
                    <a href="<?php echo app_url('forgot_password.php'); ?>" class="text-blue-600 hover:text-blue-700 text-sm font-medium">Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Close modal when clicking outside of it
        document.getElementById('adminModal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('adminModal').classList.add('hidden');
            }
        });
    </script>

    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
    </style>

