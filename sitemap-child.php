<?php
/**
 * Child sitemap router — /sitemap-{type}.xml
 * Expects $SITEMAP_CHILD set by rewrite or query ?type=
 */
declare(strict_types=1);

$AROWIN_API_MODE = true;
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cms-repository.php';
require_once __DIR__ . '/includes/sitemap-builder.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

$child = (string) ($SITEMAP_CHILD ?? ($_GET['type'] ?? 'pages'));
$child = strtolower(preg_replace('/[^a-z0-9\-]/', '', $child) ?? 'pages');
$allowed = [
    'pages', 'blog', 'news', 'products', 'events', 'gallery',
    'videos', 'downloads', 'images', 'video-media',
];
if (!in_array($child, $allowed, true)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Sitemap not found';
    exit;
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=1800');
header('X-Robots-Tag: noindex');

echo arowin_sitemap_render_child($child);
