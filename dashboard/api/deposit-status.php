<?php
/**
 * Deposit status poll — refreshes from NOWPayments and returns QR/status.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/wallet-repository.php';
require_once dirname(__DIR__, 2) . '/includes/deposit-repository.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function arowin_deposit_status_json(array $payload, int $code = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($code);
        header('Content-Type: application/json; charset=UTF-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if (!arowin_is_logged_in()) {
    arowin_deposit_status_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
}

$username = (string) ($_SESSION['arowin_uid'] ?? '');
$orderId = trim((string) ($_GET['order_id'] ?? $_POST['order_id'] ?? ''));

if ($orderId === '') {
    $raw = file_get_contents('php://input') ?: '';
    $data = json_decode($raw, true);
    if (is_array($data)) {
        $orderId = trim((string) ($data['order_id'] ?? ''));
    }
}

if ($orderId === '') {
    arowin_deposit_status_json(['ok' => false, 'message' => 'order_id required.'], 400);
}

try {
    $result = arowin_deposit_refresh_status($orderId, $username);
    arowin_deposit_status_json($result, !empty($result['ok']) ? 200 : 404);
} catch (Throwable $e) {
    error_log('Deposit status error: ' . $e->getMessage());
    arowin_deposit_status_json(['ok' => false, 'message' => 'Status check failed.'], 500);
}
