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

    <!-- Sticky Navigation -->
    <nav class="fixed top-0 left-0 right-0 bg-brand text-white px-6 md:px-10 py-4 z-50 shadow-lg">
        <div class="flex justify-between items-center">
            <div class="text-2xl font-extrabold tracking-tighter cursor-pointer" onclick="window.scrollTo(0,0)">
                ALPHA<span class="text-blue-500">LOANS</span>
            </div>
            
            <div class="hidden lg:flex items-center gap-8 text-[11px] uppercase tracking-[0.2em] font-semibold">
                <a href="#home" class="hover:text-blue-400 transition">Home</a>
                <a href="#loan-features" class="hover:text-blue-400 transition">Loan Mechanics</a>
                <a href="#membership" class="hover:text-blue-400 transition">Membership</a>
                <a href="#about-us" class="hover:text-blue-400 transition">About</a>
                
                <span class="h-4 w-[1px] bg-gray-600"></span>

                <a href="<?php echo app_url('login.php'); ?>" class="bg-white text-brand px-6 py-2 rounded-full font-bold hover:bg-blue-50 transition shadow-xl">LOGIN</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <!-- Hero Section -->
    <header id="home" class="bg-brand text-white min-h-[90vh] flex flex-col relative overflow-hidden pt-16">
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
            <div class="border border-gray-300 bg-gray-800 p-10 rounded-3xl hover:bg-blue-600 hover:border-blue-600 transition-all duration-300 group">
                <h3 class="text-2xl font-bold mb-2 text-white group-hover:text-white">Basic</h3>
                <p class="text-gray-300 text-sm mb-6 group-hover:text-blue-100">Unlimited Slots Available</p>
                <ul class="space-y-4 text-gray-300 mb-10 group-hover:text-white">
                    <li class="flex items-center gap-2">✅ Standard Loan Access</li>
                    <li class="flex items-center gap-2">✅ Monthly Billing Summary</li>
                    <li class="flex items-center gap-2 opacity-50">❌ Savings Account</li>
                    <li class="flex items-center gap-2 opacity-50">❌ Money Back Dividends</li>
                </ul>
                <a href="register.php?type=basic" class="block w-full py-3 bg-white text-gray-800 rounded-xl font-bold hover:bg-blue-50 transition text-center">Join Basic</a>
            </div>
            <div class="border border-gray-300 bg-gray-800 p-10 rounded-3xl hover:bg-blue-600 hover:border-blue-600 transition-all duration-300 group transform scale-105">
                <div class="flex justify-between items-start mb-2">
                    <h3 class="text-2xl font-bold text-white group-hover:text-white">Premium</h3>
                    <span class="bg-blue-600 text-white text-[10px] font-black px-2 py-1 rounded group-hover:bg-white group-hover:text-blue-600">50 SLOTS ONLY</span>
                </div>
                <p class="text-gray-300 text-sm mb-6 group-hover:text-blue-100">Exclusive Benefits</p>
                <ul class="space-y-4 text-gray-300 mb-10 group-hover:text-white">
                    <li class="flex items-center gap-2">✅ All Basic Features</li>
                    <li class="flex items-center gap-2">✅ **Savings Account (Max 100k)**</li>
                    <li class="flex items-center gap-2">✅ **2% Yearly Company Dividends**</li>
                    <li class="flex items-center gap-2">✅ Earned Money Back</li>
                </ul>
                <a href="register.php?type=premium" class="block w-full py-3 bg-white text-gray-800 rounded-xl font-bold hover:bg-blue-50 transition text-center">Get Premium</a>
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


