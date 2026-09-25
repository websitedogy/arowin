<?php
/**
 * Legacy alias — /admin/purchases → /admin/dashboard/purchases
 */
declare(strict_types=1);

require __DIR__ . '/includes/init.php';

admin_redirect('/dashboard/purchases');
