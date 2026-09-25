-- Power IDs + purchased package (active unit is always $50 after split)
ALTER TABLE `node_registrations`
  ADD COLUMN `power_owner` VARCHAR(60) NULL DEFAULT NULL AFTER `node_package`,
  ADD COLUMN `purchased_package` ENUM(
    '50','150','350','750','1550','3150','6350','12750'
  ) NULL DEFAULT NULL AFTER `power_owner`;

CREATE INDEX `idx_reg_power_owner` ON `node_registrations` (`power_owner`);
