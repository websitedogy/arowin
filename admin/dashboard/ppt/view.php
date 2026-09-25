<?php
/**
 * Admin — serve PPT/PDF for inline preview (history or live).
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/ppt-storage.php';

$id = trim((string) ($_GET['id'] ?? ''), " \t\n\r\0\x0B/");
$file = null;

if ($id !== '') {
    $file = arowin_ppt_file_for_history_id($id);
} else {
    $current = arowin_ppt_current();
    if ($current !== null) {
        $path = arowin_ppt_dir() . '/' . $current['stored_name'];
        if (is_file($path)) {
            $file = [
                'path' => $path,
                'original_name' => $current['original_name'],
            ];
        }
    }
}

if ($file === null) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'PDF not found.';
    exit;
}

$safeName = str_replace(['"', "\r", "\n"], '', $file['original_name']);
header('Content-Type: application/pdf');
header('Content-Length: ' . (string) filesize($file['path']));
header('Content-Disposition: inline; filename="' . $safeName . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
header('X-Frame-Options: SAMEORIGIN');
readfile($file['path']);
exit;
