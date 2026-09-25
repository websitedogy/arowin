<?php
/**
 * Dynamic robots.txt — Search Console ready crawl rules
 */
declare(strict_types=1);

$AROWIN_API_MODE = true;
require __DIR__ . '/includes/bootstrap.php';

while (ob_get_level() > 0) {
    ob_end_clean();
}

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: public, max-age=86400');

$sitemap = arowin_abs($BASE, '/sitemap.xml');
$host = (string) (parse_url($BASE, PHP_URL_HOST) ?? '');

echo "# Arowin Trading Company (AROWIN) — robots.txt\n";
echo "# Official brand: Arowin / AROWIN / Arowin Trading Company\n";
echo "# Host: {$BASE}/\n\n";

echo "User-agent: *\n";
echo "Allow: /\n";
echo "Allow: /blog\n";
echo "Allow: /news\n";
echo "Allow: /products\n";
echo "Allow: /events\n";
echo "Allow: /gallery\n";
echo "Allow: /videos\n";
echo "Allow: /downloads\n";
echo "Allow: /search\n";
echo "Allow: /register\n";
echo "Allow: /login\n";
echo "Allow: /about\n";
echo "Allow: /faqs\n";
echo "Allow: /binary-plan\n";
echo "Allow: /usdt-matching\n";
echo "Allow: /contact\n\n";

echo "# Private / app areas\n";
echo "Disallow: /admin/\n";
echo "Disallow: /dashboard/\n";
echo "Disallow: /api/\n";
echo "Disallow: /config/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /scripts/\n";
echo "Disallow: /backups/\n";
echo "Disallow: /storage/\n";
echo "Disallow: /logs/\n";
echo "Disallow: /vendor/\n";
echo "Disallow: /offline\n";
echo "Disallow: /*.json$\n\n";

echo "# Googlebot\n";
echo "User-agent: Googlebot\n";
echo "Allow: /\n";
echo "Disallow: /admin/\n";
echo "Disallow: /dashboard/\n\n";

echo "Host: {$host}\n";
echo "Sitemap: {$sitemap}\n";
