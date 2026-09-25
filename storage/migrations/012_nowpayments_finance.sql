-- NOWPayments deposits + withdrawals (production finance)
-- Safe to re-run: CREATE IF NOT EXISTS + optional ALTERs handled in PHP ensure_* helpers.

CREATE TABLE IF NOT EXISTS `wallet_deposits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `order_id` VARCHAR(80) NOT NULL,
  `amount_usd` DECIMAL(14,2) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `nowpayments_payment_id` VARCHAR(64) DEFAULT NULL,
  `nowpayments_invoice_id` VARCHAR(64) DEFAULT NULL,
  `pay_currency` VARCHAR(32) DEFAULT NULL,
  `pay_amount` DECIMAL(24,12) DEFAULT NULL,
  `pay_address` VARCHAR(128) DEFAULT NULL,
  `actually_paid` DECIMAL(24,12) DEFAULT NULL,
  `payment_url` VARCHAR(500) DEFAULT NULL,
  `note` VARCHAR(255) DEFAULT NULL,
  `raw_ipn_json` MEDIUMTEXT DEFAULT NULL,
  `credited_at` TIMESTAMP NULL DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_deposit_order` (`order_id`),
  UNIQUE KEY `uq_deposit_payment` (`nowpayments_payment_id`),
  KEY `idx_deposit_user` (`username`),
  KEY `idx_deposit_status` (`status`),
  KEY `idx_deposit_invoice` (`nowpayments_invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `wallet_withdrawals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `currency` VARCHAR(32) NOT NULL DEFAULT 'usdttrc20',
  `wallet_address` VARCHAR(128) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `wallet_tx_id` BIGINT UNSIGNED DEFAULT NULL,
  `nowpayments_batch_id` VARCHAR(64) DEFAULT NULL,
  `nowpayments_payout_id` VARCHAR(64) DEFAULT NULL,
  `tx_hash` VARCHAR(128) DEFAULT NULL,
  `admin_note` VARCHAR(255) DEFAULT NULL,
  `reviewed_by` VARCHAR(80) DEFAULT NULL,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `raw_create_json` MEDIUMTEXT DEFAULT NULL,
  `raw_ipn_json` MEDIUMTEXT DEFAULT NULL,
  `error_log` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_wd_user` (`username`),
  KEY `idx_wd_status` (`status`),
  KEY `idx_wd_batch` (`nowpayments_batch_id`),
  KEY `idx_wd_payout` (`nowpayments_payout_id`),
  KEY `idx_wd_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `nowpayments_webhook_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_type` VARCHAR(40) NOT NULL DEFAULT 'unknown',
  `external_id` VARCHAR(80) DEFAULT NULL,
  `signature_ok` TINYINT(1) NOT NULL DEFAULT 0,
  `payload_json` MEDIUMTEXT NOT NULL,
  `result_json` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_np_wh_ext` (`external_id`),
  KEY `idx_np_wh_type` (`event_type`),
  KEY `idx_np_wh_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
