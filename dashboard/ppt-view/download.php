<?php
/**
 * Authenticated PPT/PDF serve — inline for viewer, ?download=1 for attachment.
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

if (!arowin_is_logged_in()) {
    header('Location: ' . url('login'), true, 303);
    exit;
}

$metaFile = dirname(__DIR__, 2) . '/storage/ppt/current.json';
if (!is_file($metaFile)) {
    http_response_code(404);
    echo 'No presentation file uploaded.';
    exit;
}

$meta = json_decode((string) file_get_contents($metaFile), true);
$stored = is_array($meta) ? basename((string) ($meta['stored_name'] ?? '')) : '';
$orig = is_array($meta) ? (string) ($meta['original_name'] ?? 'arowin-presentation') : 'arowin-presentation';
$path = dirname(__DIR__, 2) . '/storage/ppt/' . $stored;

if ($stored === '' || !is_file($path)) {
    http_response_code(404);
    echo 'Presentation file missing.';
    exit;
}

$ext = strtolower(pathinfo($stored, PATHINFO_EXTENSION));
$mime = match ($ext) {
    'pdf' => 'application/pdf',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    default => 'application/octet-stream',
};

$safeName = str_replace(['"', "\r", "\n"], '', $orig);

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
$forceDownload = isset($_GET['download']) && (string) $_GET['download'] === '1';
$disposition = $forceDownload ? 'attachment' : 'inline';
header('Content-Disposition: ' . $disposition . '; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
if (!$forceDownload) {
    header('X-Frame-Options: SAMEORIGIN');
}
readfile($path);
exit;
