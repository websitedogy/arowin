<?php
/**
 * Multi-sitemap builder — pages, blog, news, products, events, gallery, videos, downloads, images, videos media.
 */
declare(strict_types=1);

/**
 * @return list<array{loc:string,lastmod?:string,changefreq?:string,priority?:string,images?:list<array{loc:string,title?:string}>,videos?:list<array{thumbnail_loc?:string,title:string,description?:string,content_loc?:string,player_loc?:string}>}>
 */
function arowin_sitemap_static_urls(): array
{
    global $SEO;
    $stamp = date('Y-m-d', arowin_sitemap_stamp());
    $out = [];
    foreach (($SEO['sections'] ?? []) as $u) {
        $out[] = [
            'loc' => (string) ($u['loc'] ?? '/'),
            'lastmod' => $stamp,
            'changefreq' => (string) ($u['changefreq'] ?? 'monthly'),
            'priority' => (string) ($u['priority'] ?? '0.5'),
        ];
    }
    // CMS hub indexes
    foreach (arowin_cms_types() as $type) {
        $out[] = [
            'loc' => arowin_cms_public_base($type),
            'lastmod' => $stamp,
            'changefreq' => 'daily',
            'priority' => '0.8',
        ];
    }
    $out[] = [
        'loc' => '/search',
        'lastmod' => $stamp,
        'changefreq' => 'weekly',
        'priority' => '0.5',
    ];
    return $out;
}

/**
 * @return list<array{loc:string,lastmod:string}>
 */
function arowin_sitemap_index_entries(): array
{
    global $BASE;
    $stamp = date('c', arowin_sitemap_stamp());
    $children = [
        'pages',
        'blog',
        'news',
        'products',
        'events',
        'gallery',
        'videos',
        'downloads',
        'images',
        'video-media',
    ];
    $out = [];
    foreach ($children as $child) {
        $out[] = [
            'loc' => arowin_abs($BASE, '/sitemap-' . $child . '.xml'),
            'lastmod' => $stamp,
        ];
    }
    return $out;
}

/**
 * Map sitemap child name → CMS type (null = static pages / aggregate).
 */
function arowin_sitemap_child_type(string $child): ?string
{
    return match ($child) {
        'blog' => 'blog',
        'news' => 'news',
        'products' => 'product',
        'events' => 'event',
        'gallery' => 'gallery',
        'videos' => 'video',
        'downloads' => 'download',
        default => null,
    };
}

/**
 * Emit sitemap index XML.
 */
function arowin_sitemap_render_index(): string
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (arowin_sitemap_index_entries() as $s) {
        $xml .= "  <sitemap>\n";
        $xml .= '    <loc>' . htmlspecialchars($s['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
        $xml .= '    <lastmod>' . htmlspecialchars($s['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</lastmod>\n";
        $xml .= "  </sitemap>\n";
    }
    $xml .= '</sitemapindex>';
    return $xml;
}

/**
 * Emit a urlset for a child sitemap.
 */
function arowin_sitemap_render_child(string $child): string
{
    global $BASE;

    $ns = 'xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';
    if ($child === 'images') {
        $ns .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
    }
    if ($child === 'video-media' || $child === 'videos') {
        $ns .= ' xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"';
    }

    $urls = [];

    if ($child === 'pages') {
        foreach (arowin_sitemap_static_urls() as $u) {
            $urls[] = $u;
        }
    } elseif ($child === 'images') {
        foreach (arowin_cms_all_published_for_sitemap() as $row) {
            $img = (string) ($row['og_image'] ?: $row['featured_image'] ?: '');
            if ($img === '') {
                continue;
            }
            $loc = arowin_cms_public_base((string) $row['type']) . '/' . $row['slug'];
            $urls[] = [
                'loc' => $loc,
                'lastmod' => arowin_sitemap_lastmod($row),
                'images' => [[
                    'loc' => preg_match('#^https?://#i', $img) ? $img : arowin_abs($BASE, $img),
                    'title' => (string) $row['title'],
                ]],
            ];
        }
        // Default OG image on homepage
        global $SEO;
        $og = (string) ($SEO['og_image'] ?? '');
        if ($og !== '') {
            $urls[] = [
                'loc' => '/',
                'lastmod' => date('Y-m-d', arowin_sitemap_stamp()),
                'images' => [[
                    'loc' => arowin_abs($BASE, $og),
                    'title' => 'Arowin Trading Company',
                ]],
            ];
        }
    } elseif ($child === 'video-media') {
        foreach (arowin_cms_all_published_for_sitemap('video') as $row) {
            $loc = arowin_cms_public_base('video') . '/' . $row['slug'];
            $thumb = (string) ($row['featured_image'] ?: $row['og_image'] ?: '');
            $content = (string) ($row['video_url'] ?? '');
            $urls[] = [
                'loc' => $loc,
                'lastmod' => arowin_sitemap_lastmod($row),
                'videos' => [[
                    'thumbnail_loc' => $thumb !== '' ? (preg_match('#^https?://#i', $thumb) ? $thumb : arowin_abs($BASE, $thumb)) : '',
                    'title' => (string) $row['title'],
                    'description' => (string) $row['title'],
                    'content_loc' => $content,
                    'player_loc' => $content,
                ]],
            ];
        }
    } else {
        $type = arowin_sitemap_child_type($child);
        if ($type) {
            foreach (arowin_cms_all_published_for_sitemap($type) as $row) {
                $entry = [
                    'loc' => arowin_cms_public_base($type) . '/' . $row['slug'],
                    'lastmod' => arowin_sitemap_lastmod($row),
                    'changefreq' => 'weekly',
                    'priority' => $type === 'product' ? '0.9' : '0.7',
                ];
                if ($type === 'video' && !empty($row['video_url'])) {
                    $thumb = (string) ($row['featured_image'] ?: '');
                    $entry['videos'] = [[
                        'thumbnail_loc' => $thumb !== '' ? arowin_abs($BASE, $thumb) : '',
                        'title' => (string) $row['title'],
                        'description' => (string) $row['title'],
                        'content_loc' => (string) $row['video_url'],
                        'player_loc' => (string) $row['video_url'],
                    ]];
                }
                $img = (string) ($row['featured_image'] ?: $row['og_image'] ?: '');
                if ($img !== '') {
                    $entry['images'] = [[
                        'loc' => preg_match('#^https?://#i', $img) ? $img : arowin_abs($BASE, $img),
                        'title' => (string) $row['title'],
                    ]];
                    if ($child !== 'images' && !str_contains($ns, 'xmlns:image')) {
                        $ns .= ' xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
                    }
                }
                $urls[] = $entry;
            }
        }
    }

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset ' . $ns . '>' . "\n";
    foreach ($urls as $u) {
        $loc = arowin_abs($BASE, (string) $u['loc']);
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($loc, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
        if (!empty($u['lastmod'])) {
            $xml .= '    <lastmod>' . htmlspecialchars((string) $u['lastmod'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</lastmod>\n";
        }
        if (!empty($u['changefreq'])) {
            $xml .= '    <changefreq>' . htmlspecialchars((string) $u['changefreq'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</changefreq>\n";
        }
        if (!empty($u['priority'])) {
            $xml .= '    <priority>' . htmlspecialchars((string) $u['priority'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</priority>\n";
        }
        foreach ($u['images'] ?? [] as $img) {
            $xml .= "    <image:image>\n";
            $xml .= '      <image:loc>' . htmlspecialchars((string) $img['loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</image:loc>\n";
            if (!empty($img['title'])) {
                $xml .= '      <image:title>' . htmlspecialchars((string) $img['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</image:title>\n";
            }
            $xml .= "    </image:image>\n";
        }
        foreach ($u['videos'] ?? [] as $vid) {
            $xml .= "    <video:video>\n";
            if (!empty($vid['thumbnail_loc'])) {
                $xml .= '      <video:thumbnail_loc>' . htmlspecialchars((string) $vid['thumbnail_loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</video:thumbnail_loc>\n";
            }
            $xml .= '      <video:title>' . htmlspecialchars((string) $vid['title'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</video:title>\n";
            $xml .= '      <video:description>' . htmlspecialchars((string) ($vid['description'] ?? $vid['title']), ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</video:description>\n";
            if (!empty($vid['content_loc'])) {
                $xml .= '      <video:content_loc>' . htmlspecialchars((string) $vid['content_loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</video:content_loc>\n";
            }
            if (!empty($vid['player_loc'])) {
                $xml .= '      <video:player_loc>' . htmlspecialchars((string) $vid['player_loc'], ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</video:player_loc>\n";
            }
            $xml .= "    </video:video>\n";
        }
        $xml .= "  </url>\n";
    }
    $xml .= '</urlset>';
    return $xml;
}

/**
 * @param array<string,mixed> $row
 */
function arowin_sitemap_lastmod(array $row): string
{
    $raw = (string) ($row['updated_at'] ?? $row['published_at'] ?? '');
    if ($raw === '') {
        return date('Y-m-d', arowin_sitemap_stamp());
    }
    $ts = strtotime($raw . ' UTC');
    return $ts ? date('Y-m-d', $ts) : date('Y-m-d', arowin_sitemap_stamp());
}
