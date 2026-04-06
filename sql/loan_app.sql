-- Loan Application System — MySQL 5.7+ / MariaDB (single database)
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS loan_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE loan_app;

DROP TABLE IF EXISTS money_back_transactions;
DROP TABLE IF EXISTS company_earnings;
DROP TABLE IF EXISTS billing_statements;
DROP TABLE IF EXISTS loan_transactions;
DROP TABLE IF EXISTS loans;
DROP TABLE IF EXISTS savings_transactions;
DROP TABLE IF EXISTS savings_accounts;
DROP TABLE IF EXISTS registration_documents;
DROP TABLE IF EXISTS blocked_emails;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS password_reset_otps;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role ENUM('user','admin') NOT NULL DEFAULT 'user',
  username VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  account_type ENUM('basic','premium') NOT NULL,
  name VARCHAR(255) NOT NULL,
  address TEXT NOT NULL,
  gender VARCHAR(32) NULL,
  birthday DATE NOT NULL,
  age SMALLINT UNSIGNED NOT NULL,
  email VARCHAR(190) NOT NULL,
  contact_number VARCHAR(32) NOT NULL,
  bank_name VARCHAR(190) NOT NULL,
  bank_account_number VARCHAR(64) NOT NULL,
  card_holder_name VARCHAR(190) NOT NULL,
  tin_number VARCHAR(64) NOT NULL,
  company_name VARCHAR(255) NOT NULL,
  company_address TEXT NOT NULL,
  company_phone VARCHAR(32) NOT NULL,
  position VARCHAR(190) NULL,
  monthly_earnings DECIMAL(12,2) NULL,
  registration_status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  verified_tag TINYINT(1) NOT NULL DEFAULT 0,
  tin_verified TINYINT(1) NOT NULL DEFAULT 0,
  company_verified TINYINT(1) NOT NULL DEFAULT 0,
  account_status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  current_loan_ceiling DECIMAL(12,2) NOT NULL DEFAULT 10000.00,
  max_loan_term_months SMALLINT UNSIGNED NOT NULL DEFAULT 12,
  savings_last_nonzero_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  rejected_at DATETIME NULL,
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blocked_emails (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  reason VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_blocked_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE registration_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  doc_type ENUM('proof_of_billing','valid_id','coe') NOT NULL,
  file_path VARCHAR(500) NOT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_regdocs_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE savings_accounts (
  user_id INT UNSIGNED PRIMARY KEY,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  zero_since DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE savings_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  txn_no INT UNSIGNED NOT NULL,
  transaction_id VARCHAR(32) NOT NULL,
  category ENUM('deposit','withdrawal','interest_earned') NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('pending','failed','rejected','completed') NOT NULL DEFAULT 'pending',
  admin_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_sav_txn_id (transaction_id),
  UNIQUE KEY uq_sav_user_txnno (user_id, txn_no),
  KEY idx_sav_user_cat (user_id, category),
  KEY idx_sav_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  requested_amount DECIMAL(12,2) NOT NULL,
  term_months SMALLINT UNSIGNED NOT NULL,
  interest_rate_percent DECIMAL(5,2) NOT NULL DEFAULT 3.00,
  interest_amount DECIMAL(12,2) NOT NULL,
  received_amount DECIMAL(12,2) NOT NULL,
  principal_remaining DECIMAL(12,2) NOT NULL,
  status ENUM('pending','approved','rejected','active','completed','defaulted') NOT NULL DEFAULT 'pending',
  admin_reject_reason TEXT NULL,
  money_released_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_loans_user (user_id),
  KEY idx_loans_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loan_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  loan_id INT UNSIGNED NULL,
  txn_no INT UNSIGNED NOT NULL,
  transaction_id VARCHAR(32) NOT NULL,
  txn_type ENUM('new_loan','limit_increase','term_increase') NOT NULL,
  old_ceiling DECIMAL(12,2) NULL,
  new_ceiling DECIMAL(12,2) NULL,
  old_term_months SMALLINT UNSIGNED NULL,
  new_term_months SMALLINT UNSIGNED NULL,
  status ENUM('pending','rejected','approved') NOT NULL DEFAULT 'pending',
  admin_reject_reason TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE SET NULL,
  UNIQUE KEY uq_loan_txn_id (transaction_id),
  UNIQUE KEY uq_loan_user_txnno (user_id, txn_no),
  KEY idx_loan_txn_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE billing_statements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  loan_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  period_year SMALLINT UNSIGNED NOT NULL,
  period_month TINYINT UNSIGNED NOT NULL,
  date_generated DATE NOT NULL,
  due_date DATE NOT NULL,
  loaned_amount DECIMAL(12,2) NOT NULL,
  received_amount DECIMAL(12,2) NOT NULL,
  monthly_principal DECIMAL(12,2) NOT NULL,
  interest_display DECIMAL(12,2) NOT NULL,
  penalty_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
  penalty_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_due DECIMAL(12,2) NOT NULL,
  status ENUM('pending','completed','overdue') NOT NULL DEFAULT 'pending',
  paid_at DATETIME NULL,
  FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  KEY idx_bill_loan_period (loan_id, period_year, period_month),
  KEY idx_bill_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE company_earnings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_year SMALLINT UNSIGNED NOT NULL,
  total_income DECIMAL(14,2) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_earn_year (year_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE money_back_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  year_year SMALLINT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  transaction_id VARCHAR(32) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uq_mbt_txn (transaction_id),
  KEY idx_mbt_year (year_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  email_to VARCHAR(190) NULL,
  subject VARCHAR(255) NOT NULL,
  body TEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_sent TINYINT(1) NOT NULL DEFAULT 0,
  KEY idx_notif_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_otps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_pwd_reset_user_exp (user_id, expires_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed admin: username adminuser / password: password (change immediately in production)
INSERT INTO users (role, username, password_hash, account_type, name, address, gender, birthday, age, email, contact_number, bank_name, bank_account_number, card_holder_name, tin_number, company_name, company_address, company_phone, position, monthly_earnings, registration_status, verified_tag, account_status)
VALUES (
  'admin',
  'adminuser',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'premium',
  'System Admin',
  'N/A',
  NULL,
  '1990-01-01',
  35,
  'admin@localhost.test',
  '09171234567',
  'N/A',
  '0',
  'System Admin',
  '000-000-000',
  'N/A',
  'N/A',
  '00000000000',
  NULL,
  NULL,
  'approved',
  1,
  'active'
);

-- Sample Basic User: username basicuser / password: password123
INSERT INTO users (role, username, password_hash, account_type, name, address, gender, birthday, age, email, contact_number, bank_name, bank_account_number, card_holder_name, tin_number, company_name, company_address, company_phone, position, monthly_earnings, registration_status, verified_tag, account_status)
VALUES (
  'user',
  'basicuser',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'basic',
  'Juan Dela Cruz',
  '123 Main Street, Quezon City, Metro Manila',
  'male',
  '1990-05-15',
  34,
  'juan.delacruz@email.com',
  '09123456789',
  'Banco de Oro',
  '1234567890',
  'Juan Dela Cruz',
  '123456789012',
  'ABC Company Philippines',
  '456 Business Ave, Makati City, Metro Manila',
  '0287654321',
  'Software Developer',
  45000.00,
  'approved',
  1,
  'active'
);

-- Sample Premium User: username premiumuser / password: password123
INSERT INTO users (role, username, password_hash, account_type, name, address, gender, birthday, age, email, contact_number, bank_name, bank_account_number, card_holder_name, tin_number, company_name, company_address, company_phone, position, monthly_earnings, registration_status, verified_tag, account_status)
VALUES (
  'user',
  'premiumuser',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'premium',
  'Maria Santos',
  '789 Oak Avenue, Cebu City, Cebu',
  'female',
  '1988-09-22',
  36,
  'maria.santos@email.com',
  '09876543210',
  'Bank of the Philippine Islands',
  '9876543210',
  'Maria Santos',
  '987654321098',
  'XYZ Technologies Inc.',
  '321 Tech Boulevard, Cebu Business Park, Cebu City',
  '0321234567',
  'Project Manager',
  75000.00,
  'approved',
  1,
  'active'
);

-- Create savings account for premium user
INSERT INTO savings_accounts (user_id, balance, zero_since, updated_at)
VALUES (
  (SELECT id FROM users WHERE username = 'premiumuser'),
  25000.00,
  NULL,
  CURRENT_TIMESTAMP
);
