-- Allow many User IDs to share the same email (and mobile).
-- Mobile uniqueness was app-level only; email had UNIQUE KEY uq_reg_email.

ALTER TABLE `node_registrations`
  DROP INDEX `uq_reg_email`;

ALTER TABLE `node_registrations`
  ADD INDEX `idx_reg_email` (`email`);
