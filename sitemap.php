<?php
/**
 * Dynamic sitemap index (pages, products, blog, news, images, videos, …)
 */
declare(strict_types=1);

$AROWIN_API_MODE = true;
require __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/cms-repository.php';
require_once __DIR__ . '/includes/sitemap-builder.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: application/xml; charset=UTF-8');
header('Cache-Control: public, max-age=1800');
header('X-Robots-Tag: noindex');

echo arowin_sitemap_render_index();
