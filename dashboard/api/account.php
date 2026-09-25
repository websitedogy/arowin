<?php
/**
 * Dashboard account API — photo / password / operator / login history
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$AROWIN_API_MODE = true;
require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once dirname(__DIR__, 2) . '/includes/profile-repository.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('X-Content-Type-Options: nosniff');

function arowin_account_json(array $payload, int $status = 200): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
    }
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function arowin_account_device_label(string $ua): string
{
    if ($ua === '') {
        return 'Unknown device';
    }
    $browser = 'Browser';
    if (stripos($ua, 'Edg/') !== false) {
        $browser = 'Edge';
    } elseif (stripos($ua, 'Chrome/') !== false) {
        $browser = 'Chrome';
    } elseif (stripos($ua, 'Firefox/') !== false) {
        $browser = 'Firefox';
    } elseif (stripos($ua, 'Safari/') !== false) {
        $browser = 'Safari';
    }

    $os = 'Desktop';
    if (stripos($ua, 'Android') !== false) {
        $os = 'Android';
    } elseif (stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false) {
        $os = 'iOS';
    } elseif (stripos($ua, 'Windows') !== false) {
        $os = 'Windows';
    } elseif (stripos($ua, 'Mac OS') !== false || stripos($ua, 'Macintosh') !== false) {
        $os = 'macOS';
    } elseif (stripos($ua, 'Linux') !== false) {
        $os = 'Linux';
    }

    return $browser . ' · ' . $os;
}

function arowin_account_format_dt(string $dt): string
{
    if (function_exists('arowin_format_india_time')) {
        return arowin_format_india_time($dt, 'd M Y, h:i A');
    }
    if ($dt === '') {
        return '—';
    }
    try {
        $d = new DateTimeImmutable($dt, new DateTimeZone('UTC'));
        return $d->setTimezone(new DateTimeZone('Asia/Kolkata'))->format('d M Y, h:i A') . ' IST';
    } catch (Throwable $e) {
        return $dt;
    }
}

if (!arowin_is_logged_in()) {
    arowin_account_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
}

$username = (string) ($_SESSION['arowin_uid'] ?? '');
if ($username === '') {
    arowin_account_json(['ok' => false, 'message' => 'Please sign in again.'], 401);
}

$action = (string) ($_POST['action'] ?? $_GET['action'] ?? '');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET' && $action === 'login_history') {
        $rows = arowin_login_history($username, 10);
        $items = [];
        foreach ($rows as $row) {
            $ua = (string) ($row['user_agent'] ?? '');
            $items[] = [
                'username' => arowin_normalize_sponsor_id($username),
                'ip'       => (string) ($row['ip_address'] ?? '—'),
                'device'   => arowin_account_device_label($ua),
                'when'     => (string) ($row['logged_in_at'] ?? ''),
                'when_fmt' => arowin_account_format_dt((string) ($row['logged_in_at'] ?? '')),
            ];
        }
        arowin_account_json([
            'ok' => true,
            'username' => arowin_normalize_sponsor_id($username),
            'items' => $items,
        ]);
    }

    if ($method === 'GET' && $action === 'operator') {
        $profile = arowin_get_operator_profile($username);
        if (!$profile) {
            arowin_account_json(['ok' => false, 'message' => 'Profile not found.'], 404);
        }
        arowin_account_json([
            'ok' => true,
            'profile' => [
                'username'      => (string) $profile['username'],
                'full_name'     => (string) $profile['full_name'],
                'email'         => (string) ($profile['email'] ?? ''),
                'phone'         => (string) ($profile['phone'] ?? ''),
                'usdt_wallet'   => (string) ($profile['usdt_wallet'] ?? ''),
                'country'       => (string) ($profile['country'] ?? ''),
                'sponsor_id'    => (string) ($profile['sponsor_id'] ?? ''),
                'preferred_leg' => (string) ($profile['preferred_leg'] ?? ''),
                'node_package'  => (string) ($profile['node_package'] ?? ''),
                'status'        => (string) ($profile['status'] ?? ''),
                'profile_photo' => arowin_profile_photo_url(
                    $profile['profile_photo'] !== null ? (string) $profile['profile_photo'] : null
                ),
            ],
        ]);
    }

    require_once dirname(__DIR__, 2) . '/includes/security-repository.php';

    if ($method === 'GET' && $action === 'security_status') {
        arowin_account_json(['ok' => true, 'security' => arowin_security_status($username)]);
    }

    if ($method !== 'POST') {
        arowin_account_json(['ok' => false, 'message' => 'Unsupported request.'], 405);
    }

    if ($action === 'upload_photo') {
        $result = arowin_upload_profile_photo($username, $_FILES['photo'] ?? []);
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'change_password') {
        $result = arowin_change_password(
            $username,
            (string) ($_POST['current_password'] ?? ''),
            (string) ($_POST['new_password'] ?? ''),
            (string) ($_POST['confirm_password'] ?? '')
        );
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'save_operator') {
        $result = arowin_update_operator_config($username, [
            'full_name'   => (string) ($_POST['full_name'] ?? ''),
            'email'       => (string) ($_POST['email'] ?? ''),
            'phone'       => (string) ($_POST['phone'] ?? ''),
            'usdt_wallet' => (string) ($_POST['usdt_wallet'] ?? ''),
            'country'     => (string) ($_POST['country'] ?? ''),
        ]);
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'enable_signin') {
        $result = arowin_security_enable_signin($username);
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'enable_2fa') {
        $result = arowin_security_enable_2fa(
            $username,
            (string) ($_POST['pin'] ?? ''),
            (string) ($_POST['confirm_pin'] ?? '')
        );
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'disable_2fa') {
        $result = arowin_security_disable_2fa($username, (string) ($_POST['pin'] ?? ''));
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'enable_biometric') {
        $result = arowin_security_enable_biometric(
            $username,
            (string) ($_POST['credential_id'] ?? ''),
            (string) ($_POST['public_key'] ?? '')
        );
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    if ($action === 'disable_biometric') {
        $result = arowin_security_disable_biometric($username);
        arowin_account_json($result, !empty($result['ok']) ? 200 : 422);
    }

    arowin_account_json(['ok' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('account API: ' . $e->getMessage());
    arowin_account_json([
        'ok' => false,
        'message' => env('APP_DEBUG', false)
            ? ('Error: ' . $e->getMessage())
            : 'Something went wrong. Please try again.',
    ], 500);
}
