-- Spendable wallet balance + transaction history
ALTER TABLE `node_registrations`
  ADD COLUMN `wallet_balance` DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER `status`;

CREATE TABLE IF NOT EXISTS `wallet_transactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `type` ENUM('deposit','transfer_in','transfer_out','withdraw','package_buy') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `balance_after` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `counterparty` VARCHAR(60) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wallet_user_created` (`username`, `created_at`),
  KEY `idx_wallet_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
