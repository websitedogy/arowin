<?php
/**
 * Enterprise SEO engine — slugs, meta generation, breadcrumbs, images, redirects.
 */
declare(strict_types=1);

/**
 * SEO-friendly slug from a title (ASCII, lowercase, hyphenated).
 */
function arowin_slugify(string $text, int $maxLen = 120): string
{
    $text = trim($text);
    if ($text === '') {
        return 'item';
    }
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($converted) && $converted !== '') {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    if ($text === '') {
        $text = 'item';
    }
    if (strlen($text) > $maxLen) {
        $text = rtrim(substr($text, 0, $maxLen), '-');
    }
    return $text;
}

/**
 * Auto meta description from body HTML/text (manual override preferred).
 */
function arowin_auto_description(string $content, int $max = 155): string
{
    $text = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? '';
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    if (mb_strlen($text) <= $max) {
        return $text;
    }
    $cut = mb_substr($text, 0, $max - 1);
    $space = mb_strrpos($cut, ' ');
    if ($space !== false && $space > (int) ($max * 0.6)) {
        $cut = mb_substr($cut, 0, $space);
    }
    return rtrim($cut, '.,;: ') . '…';
}

/**
 * Auto title: "{Page} | Arowin Trading Company"
 */
function arowin_auto_title(string $pageTitle, ?string $brand = null): string
{
    global $APP;
    $brand = $brand ?: (string) ($APP['legal_name'] ?? $APP['name'] ?? 'Arowin Trading Company');
    $pageTitle = trim($pageTitle);
    if ($pageTitle === '') {
        return $brand;
    }
    if (stripos($pageTitle, 'Arowin') !== false) {
        return $pageTitle;
    }
    return $pageTitle . ' | ' . $brand;
}

/**
 * @param list<array{name:string, url?:string}> $crumbs
 */
function arowin_set_breadcrumbs(array $crumbs): void
{
    $GLOBALS['AROWIN_BREADCRUMBS'] = $crumbs;
}

/**
 * @return list<array{name:string, url?:string}>
 */
function arowin_get_breadcrumbs(): array
{
    $crumbs = $GLOBALS['AROWIN_BREADCRUMBS'] ?? [];
    return is_array($crumbs) ? $crumbs : [];
}

/**
 * Render accessible breadcrumb nav + stash for JSON-LD.
 *
 * @param list<array{name:string, url?:string}> $crumbs
 */
function arowin_render_breadcrumbs(array $crumbs): string
{
    if ($crumbs === []) {
        return '';
    }
    arowin_set_breadcrumbs($crumbs);
    $html = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol class="breadcrumbs-list">';
    $last = count($crumbs) - 1;
    foreach ($crumbs as $i => $c) {
        $name = e((string) ($c['name'] ?? ''));
        $url = isset($c['url']) ? trim((string) $c['url']) : '';
        $html .= '<li class="breadcrumbs-item">';
        if ($i < $last && $url !== '') {
            $html .= '<a href="' . e($url) . '">' . $name . '</a>';
        } else {
            $html .= '<span aria-current="page">' . $name . '</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></nav>';
    return $html;
}

/**
 * Prefer WebP when a sibling .webp exists; always lazy-load below-the-fold images.
 *
 * @param array{class?:string, width?:int, height?:int, eager?:bool, sizes?:string} $opts
 */
function arowin_img(string $src, string $alt = '', array $opts = []): string
{
    global $BASE;
    $src = trim($src);
    if ($src === '') {
        return '';
    }
    $abs = preg_match('#^https?://#i', $src) ? $src : arowin_abs($BASE, $src);
    $webp = arowin_webp_candidate($src);
    $class = e((string) ($opts['class'] ?? ''));
    $eager = !empty($opts['eager']);
    $loading = $eager ? 'eager' : 'lazy';
    $decoding = $eager ? 'sync' : 'async';
    $fetch = $eager ? ' fetchpriority="high"' : '';
    $w = isset($opts['width']) ? ' width="' . (int) $opts['width'] . '"' : '';
    $h = isset($opts['height']) ? ' height="' . (int) $opts['height'] . '"' : '';
    $sizes = isset($opts['sizes']) ? ' sizes="' . e((string) $opts['sizes']) . '"' : '';

    if ($webp !== null) {
        $webpAbs = preg_match('#^https?://#i', $webp) ? $webp : arowin_abs($BASE, $webp);
        return '<picture>'
            . '<source type="image/webp" srcset="' . e($webpAbs) . '">'
            . '<img src="' . e($abs) . '" alt="' . e($alt) . '" class="' . $class . '"'
            . ' loading="' . $loading . '" decoding="' . $decoding . '"' . $fetch . $w . $h . $sizes . '>'
            . '</picture>';
    }

    return '<img src="' . e($abs) . '" alt="' . e($alt) . '" class="' . $class . '"'
        . ' loading="' . $loading . '" decoding="' . $decoding . '"' . $fetch . $w . $h . $sizes . '>';
}

function arowin_webp_candidate(string $src): ?string
{
    if (preg_match('#^https?://#i', $src) || str_ends_with(strtolower($src), '.webp')) {
        return null;
    }
    $webpRel = preg_replace('/\.(jpe?g|png|gif)$/i', '.webp', $src);
    if (!is_string($webpRel) || $webpRel === $src) {
        return null;
    }
    $root = dirname(__DIR__);
    $disk = $root . '/' . ltrim(str_replace('\\', '/', $webpRel), '/');
    // Also check under public/
    if (!is_file($disk)) {
        $disk2 = $root . '/public/' . ltrim(str_replace('\\', '/', preg_replace('#^/?public/#', '', $webpRel) ?? $webpRel), '/');
        if (is_file($disk2)) {
            return str_starts_with($webpRel, '/') ? $webpRel : '/' . ltrim($webpRel, '/');
        }
        return null;
    }
    return str_starts_with($webpRel, '/') ? $webpRel : '/' . ltrim($webpRel, '/');
}

/**
 * Extra schema nodes for the current page (Product, Article, etc.).
 *
 * @param list<array<string,mixed>> $nodes
 */
function arowin_add_schema(array $nodes): void
{
    $existing = $GLOBALS['AROWIN_EXTRA_SCHEMA'] ?? [];
    if (!is_array($existing)) {
        $existing = [];
    }
    foreach ($nodes as $node) {
        if (is_array($node) && $node !== []) {
            $existing[] = $node;
        }
    }
    $GLOBALS['AROWIN_EXTRA_SCHEMA'] = $existing;
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_extra_schema(): array
{
    $nodes = $GLOBALS['AROWIN_EXTRA_SCHEMA'] ?? [];
    return is_array($nodes) ? $nodes : [];
}

/**
 * Apply DB page overrides after arowin_prepare_page().
 */
function arowin_apply_seo_overrides(string $path): void
{
    if (!function_exists('arowin_seo_get_page_meta')) {
        return;
    }
    $cacheKey = 'seo_page_meta_' . md5($path);
    $meta = null;
    if (function_exists('arowin_micro_cache')) {
        $meta = arowin_micro_cache($cacheKey, 60, static function () use ($path): ?array {
            return arowin_seo_get_page_meta($path);
        });
    } else {
        $meta = arowin_seo_get_page_meta($path);
    }
    if ($meta === null) {
        return;
    }
    global $SEO, $CANONICAL, $BASE, $OG_IMAGE;

    if (!empty($meta['meta_title'])) {
        $SEO['title'] = (string) $meta['meta_title'];
        $SEO['title_short'] = (string) ($meta['og_title'] ?: $meta['meta_title']);
    }
    if (!empty($meta['meta_description'])) {
        $SEO['description'] = (string) $meta['meta_description'];
    }
    if (!empty($meta['meta_keywords'])) {
        $SEO['keywords'] = (string) $meta['meta_keywords'];
    }
    if (!empty($meta['canonical_url'])) {
        $CANONICAL = arowin_abs($BASE, (string) $meta['canonical_url']);
    }
    if (!empty($meta['robots'])) {
        $SEO['robots'] = (string) $meta['robots'];
    }
    if (!empty($meta['og_title'])) {
        $SEO['title_short'] = (string) $meta['og_title'];
    }
    if (!empty($meta['og_description'])) {
        // Keep page description; OG uses title_short path in seo-meta — stash
        $SEO['og_description_override'] = (string) $meta['og_description'];
    }
    if (!empty($meta['og_image'])) {
        $SEO['og_image'] = (string) $meta['og_image'];
        $OG_IMAGE = arowin_abs($BASE, (string) $meta['og_image']);
    }
    if (!empty($meta['og_type'])) {
        $SEO['og_type'] = (string) $meta['og_type'];
    }
    if (!empty($meta['twitter_title'])) {
        $SEO['twitter_title'] = (string) $meta['twitter_title'];
    }
    if (!empty($meta['twitter_description'])) {
        $SEO['twitter_description'] = (string) $meta['twitter_description'];
    }
    if (!empty($meta['twitter_image'])) {
        $SEO['twitter_image'] = (string) $meta['twitter_image'];
    }
    if (isset($meta['schema_json']) && is_string($meta['schema_json']) && $meta['schema_json'] !== '') {
        $decoded = json_decode($meta['schema_json'], true);
        if (is_array($decoded)) {
            if (isset($decoded['@graph']) && is_array($decoded['@graph'])) {
                arowin_add_schema($decoded['@graph']);
            } elseif (isset($decoded['@type'])) {
                arowin_add_schema([$decoded]);
            }
        }
    }
}

/**
 * Process DB redirects for the current request path. Call early after bootstrap.
 */
function arowin_process_redirects(): void
{
    if (!function_exists('arowin_seo_find_redirect') || PHP_SAPI === 'cli') {
        return;
    }
    try {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return;
        }
        global $BASE;
        $basePath = (string) (parse_url($BASE, PHP_URL_PATH) ?? '');
        $basePath = rtrim($basePath, '/');
        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath)) ?: '/';
        }
        $path = '/' . ltrim($path, '/');
        if (str_ends_with($path, '/') && $path !== '/') {
            $path = rtrim($path, '/');
        }

        $hit = arowin_seo_find_redirect($path);
        if ($hit === null && $path !== '/' && !str_ends_with($path, '/')) {
            $hit = arowin_seo_find_redirect($path . '/');
        }
        if ($hit === null) {
            return;
        }
        $to = (string) ($hit['to_path'] ?? '');
        if ($to === '') {
            return;
        }
        $code = (int) ($hit['status_code'] ?? 301);
        if ($code < 300 || $code > 399) {
            $code = 301;
        }
        $loc = preg_match('#^https?://#i', $to) ? $to : arowin_abs($BASE, $to);
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Location: ' . $loc, true, $code);
        exit;
    } catch (Throwable $e) {
        return;
    }
}

/**
 * Touch sitemap cache so next request regenerates lastmod freshness.
 */
function arowin_invalidate_sitemap(): void
{
    $dir = dirname(__DIR__) . '/storage/seo';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($dir . '/sitemap.stamp', (string) time());
}

function arowin_sitemap_stamp(): int
{
    $file = dirname(__DIR__) . '/storage/seo/sitemap.stamp';
    if (!is_file($file)) {
        return time();
    }
    return (int) @file_get_contents($file) ?: time();
}

/**
 * Related internal links for a content type (E-E-A-T / crawl paths).
 *
 * @return list<array{title:string, url:string, type:string}>
 */
function arowin_internal_links(string $type, string $excludeSlug = '', int $limit = 6): array
{
    if (!function_exists('arowin_cms_related')) {
        return [];
    }
    return arowin_cms_related($type, $excludeSlug, $limit);
}

/**
 * Render related links block.
 *
 * @param list<array{title:string, url:string, type?:string}> $links
 */
function arowin_render_internal_links(array $links, string $heading = 'Related reading'): string
{
    if ($links === []) {
        return '';
    }
    $html = '<aside class="related-links" aria-labelledby="related-links-heading">'
        . '<h2 id="related-links-heading">' . e($heading) . '</h2><ul class="related-links-list">';
    foreach ($links as $link) {
        $html .= '<li><a href="' . e((string) $link['url']) . '">' . e((string) $link['title']) . '</a></li>';
    }
    $html .= '</ul></aside>';
    return $html;
}
