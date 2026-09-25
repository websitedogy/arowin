<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
header('Location: ' . url('register'), true, 301);
exit;
