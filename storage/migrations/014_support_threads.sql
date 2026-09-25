-- Arowin Support module: multiple tickets per member (one open at a time)
-- Run: mysql -u root arowin < storage/migrations/014_support_threads.sql
-- Also auto-created at runtime by arowin_ensure_support_schema()

CREATE TABLE IF NOT EXISTS `support_threads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(64) NOT NULL,
  `full_name` VARCHAR(190) DEFAULT NULL,
  `email` VARCHAR(190) DEFAULT NULL,
  `topic` VARCHAR(60) DEFAULT NULL,
  `subject` VARCHAR(190) DEFAULT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `last_sender` ENUM('member','admin') DEFAULT NULL,
  `last_preview` VARCHAR(255) DEFAULT NULL,
  `last_message_at` TIMESTAMP NULL DEFAULT NULL,
  `waiting_on` ENUM('admin','member') NOT NULL DEFAULT 'admin',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_thread_user` (`username`),
  KEY `idx_support_thread_status` (`status`),
  KEY `idx_support_thread_waiting` (`waiting_on`),
  KEY `idx_support_thread_last` (`last_message_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `thread_id` BIGINT UNSIGNED DEFAULT NULL,
  `username` VARCHAR(64) NOT NULL,
  `inquiry_id` BIGINT UNSIGNED DEFAULT NULL,
  `sender` ENUM('member','admin') NOT NULL,
  `topic` VARCHAR(60) DEFAULT NULL,
  `subject` VARCHAR(190) DEFAULT NULL,
  `body` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_support_msg_thread` (`thread_id`),
  KEY `idx_support_msg_user` (`username`),
  KEY `idx_support_msg_inquiry` (`inquiry_id`),
  KEY `idx_support_msg_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- If upgrading from unique-username version:
-- ALTER TABLE support_threads DROP INDEX uq_support_thread_user;
