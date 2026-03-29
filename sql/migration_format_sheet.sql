-- Run once on existing databases: adds Interest Earned category for savings (Format sheet)
ALTER TABLE savings_transactions
  MODIFY category ENUM('deposit','withdrawal','interest_earned') NOT NULL;
