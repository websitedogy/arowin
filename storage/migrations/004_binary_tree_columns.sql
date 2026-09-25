-- Binary genealogy placement columns
ALTER TABLE `node_registrations`
  ADD COLUMN `binary_parent_id` VARCHAR(60) NULL DEFAULT NULL AFTER `preferred_leg`,
  ADD COLUMN `binary_placement` ENUM('left','right') NULL DEFAULT NULL AFTER `binary_parent_id`,
  ADD KEY `idx_binary_parent` (`binary_parent_id`);
