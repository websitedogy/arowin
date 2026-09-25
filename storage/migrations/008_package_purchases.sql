-- Package purchase requests (USDT proof → admin approve)
CREATE TABLE IF NOT EXISTS `package_purchases` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `package_code` ENUM('50','150','350','750','1550','3150','6350','12750') NOT NULL,
  `amount_usdt` INT UNSIGNED NOT NULL,
  `network` VARCHAR(20) NOT NULL DEFAULT 'TRC20',
  `tx_hash` VARCHAR(120) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `admin_note` VARCHAR(255) DEFAULT NULL,
  `reviewed_by` VARCHAR(60) DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_purchase_tx` (`tx_hash`),
  KEY `idx_purchase_user_status` (`username`, `status`),
  KEY `idx_purchase_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
