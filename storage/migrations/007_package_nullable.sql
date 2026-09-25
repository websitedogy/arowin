-- Packages are assigned only after purchase — not on signup
ALTER TABLE `node_registrations`
  MODIFY COLUMN `node_package` ENUM(
    '50',
    '150',
    '350',
    '750',
    '1550',
    '3150',
    '6350',
    '12750'
  ) NULL DEFAULT NULL;

-- Clear auto-assigned $750 defaults (no purchase flow existed)
UPDATE `node_registrations`
SET `node_package` = NULL
WHERE `node_package` = '750';
