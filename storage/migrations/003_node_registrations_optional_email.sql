-- Allow optional email on register form (multiple NULL emails ok under UNIQUE)
ALTER TABLE `node_registrations`
  MODIFY `email` VARCHAR(190) NULL DEFAULT NULL;

ALTER TABLE `node_registrations`
  ADD KEY `idx_reg_phone` (`phone`);
