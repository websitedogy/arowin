-- Profile photo + login history
ALTER TABLE `node_registrations`
  ADD COLUMN `profile_photo` VARCHAR(255) DEFAULT NULL AFTER `usdt_wallet`;

CREATE TABLE IF NOT EXISTS `login_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `logged_in_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_user` (`username`),
  KEY `idx_login_at` (`logged_in_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
