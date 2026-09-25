<?php
/**
 * Gate: redirect guests to admin login.
 * Require admin/includes/init.php before this file.
 */
declare(strict_types=1);

if (!arowin_is_admin_logged_in()) {
    admin_redirect();
}
