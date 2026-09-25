<?php
/**
 * Admin support poll — waiting counts for live desk refresh
 */
declare(strict_types=1);

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/dashboard/support/includes/support.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$snap = arowin_support_admin_poll_snapshot();
echo json_encode([
    'ok'        => true,
    'waiting'   => $snap['waiting'],
    'open'      => $snap['open'],
    'latest_id' => $snap['latest_id'],
    'latest_at' => $snap['latest_at'],
], JSON_UNESCAPED_SLASHES);
