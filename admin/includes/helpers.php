<?php
/**
 * Admin asset / URL helpers
 */
declare(strict_types=1);

if (!function_exists('admin_asset')) {
    /**
     * Versioned URL for files under admin/assets/
     */
    function admin_asset(string $path): string
    {
        global $BASE;
        $path = ltrim($path, '/');
        $href = arowin_abs($BASE, '/admin/assets/' . $path);
        $full = dirname(__DIR__) . '/assets/' . $path;
        if (is_file($full)) {
            $href .= '?v=' . filemtime($full);
        }
        return $href;
    }
}

if (!function_exists('admin_url')) {
    /**
     * Absolute admin path helper (e.g. admin_url('/dashboard')).
     */
    function admin_url(string $path = ''): string
    {
        global $BASE;
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return arowin_abs($BASE, '/admin/');
        }
        return arowin_abs($BASE, '/admin/' . trim($path, '/') . '/');
    }
}

if (!function_exists('admin_redirect')) {
    /**
     * Reliable 303 redirect (survives output buffers / headers-already-sent).
     */
    function admin_redirect(string $path = ''): void
    {
        $url = admin_url($path);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Location: ' . $url, true, 303);
            exit;
        }

        $safe = htmlspecialchars($url, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">'
            . '<meta http-equiv="refresh" content="0;url=' . $safe . '">'
            . '<title>Redirecting…</title></head><body>'
            . '<p><a href="' . $safe . '">Continue to admin</a></p>'
            . '</body></html>';
        exit;
    }
}
