-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 15, 2026 at 06:44 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `loan_app`
--

-- --------------------------------------------------------

--
-- Table structure for table `billing_statements`
--

CREATE TABLE `billing_statements` (
  `id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `period_year` smallint(5) UNSIGNED NOT NULL,
  `period_month` tinyint(3) UNSIGNED NOT NULL,
  `date_generated` date NOT NULL,
  `due_date` date NOT NULL,
  `loaned_amount` decimal(12,2) NOT NULL,
  `received_amount` decimal(12,2) NOT NULL,
  `monthly_principal` decimal(12,2) NOT NULL,
  `interest_display` decimal(12,2) NOT NULL,
  `penalty_percent` decimal(5,2) NOT NULL DEFAULT 0.00,
  `penalty_amount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_due` decimal(12,2) NOT NULL,
  `status` enum('pending','completed','overdue') NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blocked_emails`
--

CREATE TABLE `blocked_emails` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_earnings`
--

CREATE TABLE `company_earnings` (
  `id` int(10) UNSIGNED NOT NULL,
  `year_year` smallint(5) UNSIGNED NOT NULL,
  `total_income` decimal(14,2) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `loans`
--

CREATE TABLE `loans` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `requested_amount` decimal(12,2) NOT NULL,
  `term_months` smallint(5) UNSIGNED NOT NULL,
  `interest_rate_percent` decimal(5,2) NOT NULL DEFAULT 3.00,
  `interest_amount` decimal(12,2) NOT NULL,
  `received_amount` decimal(12,2) NOT NULL,
  `principal_remaining` decimal(12,2) NOT NULL,
  `status` enum('pending','approved','rejected','active','completed','defaulted') NOT NULL DEFAULT 'pending',
  `admin_reject_reason` text DEFAULT NULL,
  `money_released_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loans`
--

INSERT INTO `loans` (`id`, `user_id`, `requested_amount`, `term_months`, `interest_rate_percent`, `interest_amount`, `received_amount`, `principal_remaining`, `status`, `admin_reject_reason`, `money_released_at`, `created_at`) VALUES
(1, 2, 5000.00, 1, 3.00, 150.00, 4850.00, 5000.00, 'pending', NULL, NULL, '2026-04-05 19:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `loan_transactions`
--

CREATE TABLE `loan_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `loan_id` int(10) UNSIGNED DEFAULT NULL,
  `txn_no` int(10) UNSIGNED NOT NULL,
  `transaction_id` varchar(32) NOT NULL,
  `txn_type` enum('new_loan','limit_increase','term_increase') NOT NULL,
  `old_ceiling` decimal(12,2) DEFAULT NULL,
  `new_ceiling` decimal(12,2) DEFAULT NULL,
  `old_term_months` smallint(5) UNSIGNED DEFAULT NULL,
  `new_term_months` smallint(5) UNSIGNED DEFAULT NULL,
  `status` enum('pending','rejected','approved') NOT NULL DEFAULT 'pending',
  `admin_reject_reason` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `loan_transactions`
--

INSERT INTO `loan_transactions` (`id`, `user_id`, `loan_id`, `txn_no`, `transaction_id`, `txn_type`, `old_ceiling`, `new_ceiling`, `old_term_months`, `new_term_months`, `status`, `admin_reject_reason`, `created_at`) VALUES
(1, 2, 1, 1, 'LNdb5a56a59ccf87b9', 'new_loan', NULL, NULL, NULL, NULL, 'pending', NULL, '2026-04-05 19:21:12');

-- --------------------------------------------------------

--
-- Table structure for table `money_back_transactions`
--

CREATE TABLE `money_back_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `year_year` smallint(5) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `transaction_id` varchar(32) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `email_to` varchar(190) DEFAULT NULL,
  `subject` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `is_sent` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_otps`
--

CREATE TABLE `password_reset_otps` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `otp_hash` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_documents`
--

CREATE TABLE `registration_documents` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `doc_type` enum('proof_of_billing','valid_id','coe') NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `uploaded_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `savings_accounts`
--

CREATE TABLE `savings_accounts` (
  `user_id` int(10) UNSIGNED NOT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `zero_since` datetime DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `savings_transactions`
--

CREATE TABLE `savings_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `txn_no` int(10) UNSIGNED NOT NULL,
  `transaction_id` varchar(32) NOT NULL,
  `category` enum('deposit','withdrawal','interest_earned') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `status` enum('pending','failed','rejected','completed') NOT NULL DEFAULT 'pending',
  `admin_note` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `processed_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `role` enum('user','admin') NOT NULL DEFAULT 'user',
  `username` varchar(190) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `account_type` enum('basic','premium') NOT NULL,
  `name` varchar(255) NOT NULL,
  `address` text NOT NULL,
  `gender` varchar(32) DEFAULT NULL,
  `birthday` date NOT NULL,
  `age` smallint(5) UNSIGNED NOT NULL,
  `email` varchar(190) NOT NULL,
  `contact_number` varchar(32) NOT NULL,
  `bank_name` varchar(190) NOT NULL,
  `bank_account_number` varchar(64) NOT NULL,
  `card_holder_name` varchar(190) NOT NULL,
  `tin_number` varchar(64) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `company_address` text NOT NULL,
  `company_phone` varchar(32) NOT NULL,
  `position` varchar(190) DEFAULT NULL,
  `monthly_earnings` decimal(12,2) DEFAULT NULL,
  `registration_status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `verified_tag` tinyint(1) NOT NULL DEFAULT 0,
  `tin_verified` tinyint(1) NOT NULL DEFAULT 0,
  `company_verified` tinyint(1) NOT NULL DEFAULT 0,
  `account_status` enum('active','disabled') NOT NULL DEFAULT 'active',
  `current_loan_ceiling` decimal(12,2) NOT NULL DEFAULT 10000.00,
  `max_loan_term_months` smallint(5) UNSIGNED NOT NULL DEFAULT 12,
  `savings_last_nonzero_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `rejected_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `role`, `username`, `password_hash`, `account_type`, `name`, `address`, `gender`, `birthday`, `age`, `email`, `contact_number`, `bank_name`, `bank_account_number`, `card_holder_name`, `tin_number`, `company_name`, `company_address`, `company_phone`, `position`, `monthly_earnings`, `registration_status`, `verified_tag`, `tin_verified`, `company_verified`, `account_status`, `current_loan_ceiling`, `max_loan_term_months`, `savings_last_nonzero_at`, `created_at`, `updated_at`, `rejected_at`) VALUES
(1, 'admin', 'adminuser', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'premium', 'System Admin', 'N/A', NULL, '1990-01-01', 35, 'admin@localhost.test', '09171234567', 'N/A', '0', 'System Admin', '000-000-000', 'N/A', 'N/A', '00000000000', NULL, NULL, 'approved', 1, 0, 0, 'active', 10000.00, 12, NULL, '2026-04-05 19:10:30', '2026-04-05 19:10:30', NULL),
(2, 'user', 'basic_demo', '$2y$10$N62Mpr61aHum/Sib7s/VYuOp3nyLug2blozLN8HEm7zffK4MO4Wly', 'basic', 'Demo User (basic)', '123 Demo Street, Quezon City', NULL, '1995-06-15', 30, 'lariosa.myca21@gmail.com', '09171234567', 'Demo Bank', '1234567890', 'Demo User', '123-456-789-000', 'Demo Company Inc.', '456 Business Ave, Makati', '0287654321', NULL, NULL, 'approved', 1, 0, 0, 'active', 10000.00, 12, NULL, '2026-04-05 19:19:15', '2026-04-06 21:45:01', NULL),
(3, 'user', 'premium_demo', '$2y$10$nopSdnODaK11F3eSrLLghO9E8aIdjhZhX6exSpHuPY.wBZphmD/TC', 'premium', 'Demo User (premium)', '123 Demo Street, Quezon City', NULL, '1995-06-15', 30, 'lariosa.myca21+premium@gmail.com', '09171234567', 'Demo Bank', '1234567890', 'Demo User', '123-456-789-000', 'Demo Company Inc.', '456 Business Ave, Makati', '0287654321', NULL, NULL, 'approved', 1, 0, 0, 'active', 10000.00, 12, NULL, '2026-04-05 19:19:15', '2026-04-05 19:38:39', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `billing_statements`
--
ALTER TABLE `billing_statements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bill_loan_period` (`loan_id`,`period_year`,`period_month`),
  ADD KEY `idx_bill_user` (`user_id`);

--
-- Indexes for table `blocked_emails`
--
ALTER TABLE `blocked_emails`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_blocked_email` (`email`);

--
-- Indexes for table `company_earnings`
--
ALTER TABLE `company_earnings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_earn_year` (`year_year`);

--
-- Indexes for table `loans`
--
ALTER TABLE `loans`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_loans_user` (`user_id`),
  ADD KEY `idx_loans_status` (`status`);

--
-- Indexes for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_loan_txn_id` (`transaction_id`),
  ADD UNIQUE KEY `uq_loan_user_txnno` (`user_id`,`txn_no`),
  ADD KEY `loan_id` (`loan_id`),
  ADD KEY `idx_loan_txn_user` (`user_id`);

--
-- Indexes for table `money_back_transactions`
--
ALTER TABLE `money_back_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mbt_txn` (`transaction_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_mbt_year` (`year_year`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notif_user` (`user_id`);

--
-- Indexes for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pwd_reset_user_exp` (`user_id`,`expires_at`);

--
-- Indexes for table `registration_documents`
--
ALTER TABLE `registration_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_regdocs_user` (`user_id`);

--
-- Indexes for table `savings_accounts`
--
ALTER TABLE `savings_accounts`
  ADD PRIMARY KEY (`user_id`);

--
-- Indexes for table `savings_transactions`
--
ALTER TABLE `savings_transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sav_txn_id` (`transaction_id`),
  ADD UNIQUE KEY `uq_sav_user_txnno` (`user_id`,`txn_no`),
  ADD KEY `idx_sav_user_cat` (`user_id`,`category`),
  ADD KEY `idx_sav_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_users_username` (`username`),
  ADD UNIQUE KEY `uq_users_email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `billing_statements`
--
ALTER TABLE `billing_statements`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blocked_emails`
--
ALTER TABLE `blocked_emails`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_earnings`
--
ALTER TABLE `company_earnings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `loans`
--
ALTER TABLE `loans`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `money_back_transactions`
--
ALTER TABLE `money_back_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `registration_documents`
--
ALTER TABLE `registration_documents`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `savings_transactions`
--
ALTER TABLE `savings_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `billing_statements`
--
ALTER TABLE `billing_statements`
  ADD CONSTRAINT `billing_statements_ibfk_1` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `billing_statements_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loans`
--
ALTER TABLE `loans`
  ADD CONSTRAINT `loans_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `loan_transactions`
--
ALTER TABLE `loan_transactions`
  ADD CONSTRAINT `loan_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `loan_transactions_ibfk_2` FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `money_back_transactions`
--
ALTER TABLE `money_back_transactions`
  ADD CONSTRAINT `money_back_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_reset_otps`
--
ALTER TABLE `password_reset_otps`
  ADD CONSTRAINT `password_reset_otps_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registration_documents`
--
ALTER TABLE `registration_documents`
  ADD CONSTRAINT `registration_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `savings_accounts`
--
ALTER TABLE `savings_accounts`
  ADD CONSTRAINT `savings_accounts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
-- Add profile picture column to existing users table
ALTER TABLE `users` 
ADD COLUMN `profile_picture_path` varchar(500) DEFAULT NULL AFTER `monthly_earnings`;

--
-- Constraints for table `savings_transactions`
--
ALTER TABLE `savings_transactions`
  ADD CONSTRAINT `savings_transactions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
