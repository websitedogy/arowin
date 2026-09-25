-- Income engine: referral 5% + matching 1:1 (10% of $50 unit)

ALTER TABLE `wallet_transactions`
  MODIFY COLUMN `type` ENUM(
    'deposit',
    'transfer_in',
    'transfer_out',
    'withdraw',
    'package_buy',
    'referral_income',
    'matching_income'
  ) NOT NULL;

CREATE TABLE IF NOT EXISTS `income_payouts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `type` ENUM('referral_income','matching_income') NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `source_username` VARCHAR(60) DEFAULT NULL,
  `package_code` VARCHAR(20) DEFAULT NULL,
  `pairs` INT UNSIGNED NOT NULL DEFAULT 0,
  `ref_key` VARCHAR(120) NOT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_income_ref` (`ref_key`),
  KEY `idx_income_user_type` (`username`, `type`),
  KEY `idx_income_source` (`source_username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matching_pair_state` (
  `username` VARCHAR(60) NOT NULL,
  `paid_pairs` INT UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
