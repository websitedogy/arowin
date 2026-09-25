<?php
// Compatibility shim for scaffold path
declare(strict_types=1);
require __DIR__ . '/../../includes/bootstrap.php';
header('Location: ' . url('legal-terms'), true, 301);
exit;
