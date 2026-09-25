-- Expand node package tiers: $50 … $12750
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
  ) NOT NULL DEFAULT '750';
