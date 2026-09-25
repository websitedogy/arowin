<?php
/**
 * Unified CMS for blog, news, products, events, gallery, videos, downloads.
 */
declare(strict_types=1);

/** @return list<string> */
function arowin_cms_types(): array
{
    return ['blog', 'news', 'product', 'event', 'gallery', 'video', 'download'];
}

function arowin_cms_type_label(string $type): string
{
    return match ($type) {
        'blog' => 'Blog',
        'news' => 'News',
        'product' => 'Products',
        'event' => 'Events',
        'gallery' => 'Gallery',
        'video' => 'Videos',
        'download' => 'Downloads',
        default => ucfirst($type),
    };
}

function arowin_cms_public_base(string $type): string
{
    return match ($type) {
        'blog' => '/blog',
        'news' => '/news',
        'product' => '/products',
        'event' => '/events',
        'gallery' => '/gallery',
        'video' => '/videos',
        'download' => '/downloads',
        default => '/' . $type,
    };
}

function arowin_ensure_cms_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once __DIR__ . '/perf.php';
    }
    arowin_schema_once('cms', 1, static function (): void {
    $pdo = arowin_db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS cms_entries (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        type VARCHAR(32) NOT NULL,
        slug VARCHAR(180) NOT NULL,
        title VARCHAR(255) NOT NULL,
        excerpt TEXT NULL,
        body MEDIUMTEXT NULL,
        featured_image VARCHAR(512) NULL,
        gallery_json MEDIUMTEXT NULL,
        video_url VARCHAR(512) NULL,
        video_embed TEXT NULL,
        download_url VARCHAR(512) NULL,
        download_mime VARCHAR(128) NULL,
        download_size INT UNSIGNED NULL,
        event_start DATETIME NULL,
        event_end DATETIME NULL,
        event_location VARCHAR(255) NULL,
        price DECIMAL(12,2) NULL,
        currency CHAR(3) NOT NULL DEFAULT 'USD',
        availability VARCHAR(32) NULL DEFAULT 'InStock',
        rating_value DECIMAL(3,2) NULL,
        rating_count INT UNSIGNED NULL DEFAULT 0,
        sku VARCHAR(64) NULL,
        meta_title VARCHAR(255) NULL,
        meta_description VARCHAR(512) NULL,
        meta_keywords VARCHAR(512) NULL,
        canonical_url VARCHAR(512) NULL,
        robots VARCHAR(128) NULL,
        og_title VARCHAR(255) NULL,
        og_description VARCHAR(512) NULL,
        og_image VARCHAR(512) NULL,
        status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
        published_at DATETIME NULL,
        author_name VARCHAR(120) NULL,
        sort_order INT NOT NULL DEFAULT 0,
        view_count INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_cms_type_slug (type, slug),
        KEY idx_cms_type_status (type, status, published_at),
        KEY idx_cms_status_pub (status, published_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    });
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_cms_list(string $type, array $opts = []): array
{
    arowin_ensure_cms_schema();
    $status = (string) ($opts['status'] ?? 'published');
    $limit = max(1, min(100, (int) ($opts['limit'] ?? 24)));
    $offset = max(0, (int) ($opts['offset'] ?? 0));
    $q = trim((string) ($opts['q'] ?? ''));
    $admin = !empty($opts['admin']);

    $sql = 'SELECT * FROM cms_entries WHERE type = ?';
    $params = [$type];
    if (!$admin) {
        $sql .= ' AND status = ?';
        $params[] = $status !== '' ? $status : 'published';
    } elseif ($status !== '' && $status !== 'all') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (title LIKE ? OR excerpt LIKE ? OR body LIKE ? OR meta_keywords LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like, $like);
    }
    $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset;
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

function arowin_cms_count(string $type, array $opts = []): int
{
    arowin_ensure_cms_schema();
    $status = (string) ($opts['status'] ?? 'published');
    $q = trim((string) ($opts['q'] ?? ''));
    $admin = !empty($opts['admin']);
    $sql = 'SELECT COUNT(*) FROM cms_entries WHERE type = ?';
    $params = [$type];
    if (!$admin) {
        $sql .= ' AND status = ?';
        $params[] = 'published';
    } elseif ($status !== '' && $status !== 'all') {
        $sql .= ' AND status = ?';
        $params[] = $status;
    }
    if ($q !== '') {
        $sql .= ' AND (title LIKE ? OR excerpt LIKE ? OR body LIKE ?)';
        $like = '%' . $q . '%';
        array_push($params, $like, $like, $like);
    }
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * @return array<string,mixed>|null
 */
function arowin_cms_find(string $type, string $slug, bool $publishedOnly = true): ?array
{
    arowin_ensure_cms_schema();
    $sql = 'SELECT * FROM cms_entries WHERE type = ? AND slug = ?';
    $params = [$type, $slug];
    if ($publishedOnly) {
        $sql .= ' AND status = \'published\'';
    }
    $sql .= ' LIMIT 1';
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array<string,mixed>|null
 */
function arowin_cms_find_by_id(int $id): ?array
{
    arowin_ensure_cms_schema();
    $stmt = arowin_db()->prepare('SELECT * FROM cms_entries WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Site-wide search across published CMS + static pages.
 *
 * @return list<array{title:string,url:string,type:string,excerpt:string}>
 */
function arowin_cms_search(string $q, int $limit = 30): array
{
    arowin_ensure_cms_schema();
    $q = trim($q);
    if ($q === '' || mb_strlen($q) < 2) {
        return [];
    }
    global $BASE, $SEO;
    $results = [];
    $like = '%' . $q . '%';

    // Static sections
    foreach (($SEO['sections'] ?? []) as $sec) {
        $loc = (string) ($sec['loc'] ?? '/');
        $label = $loc === '/' ? 'Home' : ucwords(str_replace(['-', '/'], [' ', ' '], trim($loc, '/')));
        if (stripos($label, $q) !== false || stripos($loc, $q) !== false) {
            $results[] = [
                'title' => 'Arowin — ' . $label,
                'url' => arowin_abs($BASE, $loc),
                'type' => 'page',
                'excerpt' => 'Official Arowin Trading Company page',
            ];
        }
    }

    $stmt = arowin_db()->prepare(
        'SELECT type, slug, title, excerpt, meta_description FROM cms_entries
         WHERE status = \'published\'
           AND (title LIKE ? OR excerpt LIKE ? OR body LIKE ? OR meta_keywords LIKE ?)
         ORDER BY published_at DESC LIMIT ' . max(1, min(50, $limit))
    );
    $stmt->execute([$like, $like, $like, $like]);
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $type = (string) $row['type'];
        $results[] = [
            'title' => (string) $row['title'],
            'url' => arowin_cms_url($type, (string) $row['slug']),
            'type' => $type,
            'excerpt' => (string) ($row['excerpt'] ?: $row['meta_description'] ?: ''),
        ];
    }
    return array_slice($results, 0, $limit);
}

function arowin_cms_url(string $type, string $slug = ''): string
{
    global $BASE;
    $base = arowin_cms_public_base($type);
    if ($slug === '') {
        return arowin_abs($BASE, $base);
    }
    return arowin_abs($BASE, $base . '/' . ltrim($slug, '/'));
}

/**
 * Unique slug for type.
 */
function arowin_cms_unique_slug(string $type, string $titleOrSlug, ?int $excludeId = null): string
{
    arowin_ensure_cms_schema();
    $base = arowin_slugify($titleOrSlug);
    $slug = $base;
    $i = 2;
    while (true) {
        $sql = 'SELECT id FROM cms_entries WHERE type = ? AND slug = ?';
        $params = [$type, $slug];
        if ($excludeId) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' LIMIT 1';
        $stmt = arowin_db()->prepare($sql);
        $stmt->execute($params);
        if (!$stmt->fetch()) {
            return $slug;
        }
        $slug = $base . '-' . $i;
        $i++;
        if ($i > 500) {
            return $base . '-' . bin2hex(random_bytes(3));
        }
    }
}

/**
 * @param array<string,mixed> $data
 */
function arowin_cms_save(?int $id, array $data): int
{
    arowin_ensure_cms_schema();
    $type = (string) ($data['type'] ?? '');
    if (!in_array($type, arowin_cms_types(), true)) {
        throw new InvalidArgumentException('Invalid CMS type.');
    }
    $title = trim((string) ($data['title'] ?? ''));
    if ($title === '') {
        throw new InvalidArgumentException('Title is required.');
    }
    $slugIn = trim((string) ($data['slug'] ?? ''));
    $slug = arowin_cms_unique_slug($type, $slugIn !== '' ? $slugIn : $title, $id);

    $excerpt = trim((string) ($data['excerpt'] ?? ''));
    $body = (string) ($data['body'] ?? '');
    if ($excerpt === '' && $body !== '') {
        $excerpt = arowin_auto_description($body, 180);
    }

    $metaTitle = trim((string) ($data['meta_title'] ?? ''));
    if ($metaTitle === '') {
        $metaTitle = arowin_auto_title($title);
    }
    $metaDesc = trim((string) ($data['meta_description'] ?? ''));
    if ($metaDesc === '') {
        $metaDesc = $excerpt !== '' ? arowin_auto_description($excerpt, 155) : arowin_auto_description($body, 155);
    }

    $status = (string) ($data['status'] ?? 'draft');
    if (!in_array($status, ['draft', 'published', 'archived'], true)) {
        $status = 'draft';
    }
    $publishedAt = $data['published_at'] ?? null;
    if ($status === 'published' && (empty($publishedAt) || $publishedAt === '')) {
        $publishedAt = gmdate('Y-m-d H:i:s');
    }

    $fields = [
        'type' => $type,
        'slug' => $slug,
        'title' => $title,
        'excerpt' => $excerpt !== '' ? $excerpt : null,
        'body' => $body !== '' ? $body : null,
        'featured_image' => arowin_seo_nullstr($data['featured_image'] ?? null),
        'gallery_json' => arowin_seo_nullstr($data['gallery_json'] ?? null),
        'video_url' => arowin_seo_nullstr($data['video_url'] ?? null),
        'video_embed' => arowin_seo_nullstr($data['video_embed'] ?? null),
        'download_url' => arowin_seo_nullstr($data['download_url'] ?? null),
        'download_mime' => arowin_seo_nullstr($data['download_mime'] ?? null),
        'download_size' => isset($data['download_size']) && $data['download_size'] !== '' ? (int) $data['download_size'] : null,
        'event_start' => arowin_seo_nullstr($data['event_start'] ?? null),
        'event_end' => arowin_seo_nullstr($data['event_end'] ?? null),
        'event_location' => arowin_seo_nullstr($data['event_location'] ?? null),
        'price' => isset($data['price']) && $data['price'] !== '' ? (float) $data['price'] : null,
        'currency' => strtoupper(substr((string) ($data['currency'] ?? 'USD'), 0, 3)),
        'availability' => arowin_seo_nullstr($data['availability'] ?? 'InStock') ?? 'InStock',
        'rating_value' => isset($data['rating_value']) && $data['rating_value'] !== '' ? (float) $data['rating_value'] : null,
        'rating_count' => isset($data['rating_count']) ? (int) $data['rating_count'] : 0,
        'sku' => arowin_seo_nullstr($data['sku'] ?? null),
        'meta_title' => $metaTitle,
        'meta_description' => $metaDesc,
        'meta_keywords' => arowin_seo_nullstr($data['meta_keywords'] ?? null),
        'canonical_url' => arowin_seo_nullstr($data['canonical_url'] ?? null),
        'robots' => arowin_seo_nullstr($data['robots'] ?? null),
        'og_title' => arowin_seo_nullstr($data['og_title'] ?? null),
        'og_description' => arowin_seo_nullstr($data['og_description'] ?? null),
        'og_image' => arowin_seo_nullstr($data['og_image'] ?? null),
        'status' => $status,
        'published_at' => $publishedAt,
        'author_name' => arowin_seo_nullstr($data['author_name'] ?? 'Arowin Trading Company'),
        'sort_order' => (int) ($data['sort_order'] ?? 0),
    ];

    $pdo = arowin_db();
    if ($id) {
        $sets = [];
        $vals = [];
        foreach ($fields as $k => $v) {
            $sets[] = "`$k` = ?";
            $vals[] = $v;
        }
        $vals[] = $id;
        $pdo->prepare('UPDATE cms_entries SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
        arowin_invalidate_sitemap();
        return $id;
    }

    $cols = array_keys($fields);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $pdo->prepare(
        'INSERT INTO cms_entries (`' . implode('`,`', $cols) . '`) VALUES (' . $placeholders . ')'
    )->execute(array_values($fields));
    arowin_invalidate_sitemap();
    return (int) $pdo->lastInsertId();
}

function arowin_cms_delete(int $id): void
{
    arowin_ensure_cms_schema();
    arowin_db()->prepare('DELETE FROM cms_entries WHERE id = ?')->execute([$id]);
    arowin_invalidate_sitemap();
}

function arowin_cms_bump_views(int $id): void
{
    arowin_ensure_cms_schema();
    arowin_db()->prepare('UPDATE cms_entries SET view_count = view_count + 1 WHERE id = ?')->execute([$id]);
}

/**
 * @return list<array{title:string,url:string,type:string}>
 */
function arowin_cms_related(string $type, string $excludeSlug = '', int $limit = 6): array
{
    arowin_ensure_cms_schema();
    $limit = max(1, min(12, $limit));
    $sql = 'SELECT type, slug, title FROM cms_entries WHERE status = \'published\' AND type = ?';
    $params = [$type];
    if ($excludeSlug !== '') {
        $sql .= ' AND slug <> ?';
        $params[] = $excludeSlug;
    }
    $sql .= ' ORDER BY published_at DESC LIMIT ' . $limit;
    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    $out = [];
    foreach ($stmt->fetchAll() ?: [] as $row) {
        $out[] = [
            'title' => (string) $row['title'],
            'url' => arowin_cms_url((string) $row['type'], (string) $row['slug']),
            'type' => (string) $row['type'],
        ];
    }
    // Cross-link hub pages for crawl depth
    if (count($out) < $limit) {
        $hubs = [
            ['title' => 'Arowin Binary Plan', 'url' => url('binary-plan'), 'type' => 'page'],
            ['title' => 'USDT Matching', 'url' => url('usdt-matching'), 'type' => 'page'],
            ['title' => 'Arowin FAQs', 'url' => url('faqs'), 'type' => 'page'],
            ['title' => 'About Arowin Trading Company', 'url' => url('about'), 'type' => 'page'],
        ];
        foreach ($hubs as $h) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = $h;
        }
    }
    return $out;
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_cms_all_published_for_sitemap(?string $type = null): array
{
    arowin_ensure_cms_schema();
    if ($type) {
        $stmt = arowin_db()->prepare(
            'SELECT type, slug, featured_image, og_image, video_url, updated_at, published_at, title
             FROM cms_entries WHERE status = \'published\' AND type = ?
             ORDER BY published_at DESC'
        );
        $stmt->execute([$type]);
        return $stmt->fetchAll() ?: [];
    }
    return arowin_db()->query(
        'SELECT type, slug, featured_image, og_image, video_url, updated_at, published_at, title
         FROM cms_entries WHERE status = \'published\'
         ORDER BY type ASC, published_at DESC'
    )->fetchAll() ?: [];
}

/**
 * Apply CMS entry SEO onto global page context.
 *
 * @param array<string,mixed> $entry
 */
function arowin_cms_prepare_entry_seo(array $entry): void
{
    global $BASE, $SEO;
    $type = (string) $entry['type'];
    $slug = (string) $entry['slug'];
    $path = arowin_cms_public_base($type) . '/' . $slug;
    $canonical = !empty($entry['canonical_url'])
        ? (string) $entry['canonical_url']
        : $path;

    $title = (string) ($entry['meta_title'] ?: arowin_auto_title((string) $entry['title']));
    $desc = (string) ($entry['meta_description'] ?: arowin_auto_description((string) ($entry['excerpt'] ?: $entry['body'] ?? ''), 155));

    $ogType = match ($type) {
        'blog', 'news' => 'article',
        'product' => 'product',
        'video' => 'video.other',
        default => 'website',
    };

    arowin_prepare_page([
        'title' => $title,
        'title_short' => (string) ($entry['og_title'] ?: $entry['title']),
        'description' => !empty($entry['og_description']) ? (string) $entry['og_description'] : $desc,
        'keywords' => (string) ($entry['meta_keywords'] ?? ''),
        'canonical' => $canonical,
        'robots' => (string) ($entry['robots'] ?: 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1'),
        'og_type' => $ogType,
        'og_image' => (string) ($entry['og_image'] ?: $entry['featured_image'] ?: ($SEO['og_image'] ?? '')),
        'faqs' => false,
    ]);

    arowin_apply_seo_overrides($path);

    // Schema nodes
    $pageUrl = arowin_abs($BASE, $path);
    $homeUrl = arowin_abs($BASE, '/');
    $image = !empty($entry['featured_image'])
        ? arowin_abs($BASE, (string) $entry['featured_image'])
        : ($GLOBALS['OG_IMAGE'] ?? '');

    if ($type === 'blog' || $type === 'news') {
        $articleType = $type === 'news' ? 'NewsArticle' : 'Article';
        arowin_add_schema([[
            '@type' => $articleType,
            '@id' => $pageUrl . '#article',
            'headline' => (string) $entry['title'],
            'description' => $desc,
            'image' => $image ? [$image] : [],
            'datePublished' => !empty($entry['published_at'])
                ? date('c', strtotime((string) $entry['published_at'] . ' UTC'))
                : date('c'),
            'dateModified' => !empty($entry['updated_at'])
                ? date('c', strtotime((string) $entry['updated_at'] . ' UTC'))
                : date('c'),
            'author' => [
                '@type' => 'Organization',
                'name' => (string) ($entry['author_name'] ?: 'Arowin Trading Company'),
            ],
            'publisher' => ['@id' => $homeUrl . '#organization'],
            'mainEntityOfPage' => ['@id' => $pageUrl . '#webpage'],
            'inLanguage' => 'en-US',
        ]]);
    }

    if ($type === 'product') {
        $product = [
            '@type' => 'Product',
            '@id' => $pageUrl . '#product',
            'name' => (string) $entry['title'],
            'description' => $desc,
            'image' => $image ? [$image] : [],
            'sku' => (string) ($entry['sku'] ?: $entry['slug']),
            'brand' => [
                '@type' => 'Brand',
                'name' => 'Arowin',
            ],
            'offers' => [
                '@type' => 'Offer',
                'url' => $pageUrl,
                'priceCurrency' => (string) ($entry['currency'] ?: 'USD'),
                'price' => isset($entry['price']) ? (string) $entry['price'] : '0',
                'availability' => 'https://schema.org/' . ((string) ($entry['availability'] ?: 'InStock')),
                'seller' => ['@id' => $homeUrl . '#organization'],
            ],
        ];
        if (!empty($entry['rating_value']) && (int) ($entry['rating_count'] ?? 0) > 0) {
            $product['aggregateRating'] = [
                '@type' => 'AggregateRating',
                'ratingValue' => (string) $entry['rating_value'],
                'reviewCount' => (int) $entry['rating_count'],
                'bestRating' => '5',
                'worstRating' => '1',
            ];
        }
        arowin_add_schema([$product]);
    }

    if ($type === 'video' && (!empty($entry['video_url']) || !empty($entry['video_embed']))) {
        arowin_add_schema([[
            '@type' => 'VideoObject',
            '@id' => $pageUrl . '#video',
            'name' => (string) $entry['title'],
            'description' => $desc,
            'thumbnailUrl' => $image ?: null,
            'uploadDate' => !empty($entry['published_at'])
                ? date('c', strtotime((string) $entry['published_at'] . ' UTC'))
                : date('c'),
            'contentUrl' => !empty($entry['video_url']) ? (string) $entry['video_url'] : null,
            'embedUrl' => !empty($entry['video_url']) ? (string) $entry['video_url'] : null,
            'publisher' => ['@id' => $homeUrl . '#organization'],
        ]]);
    }

    if ($type === 'event') {
        arowin_add_schema([[
            '@type' => 'Event',
            '@id' => $pageUrl . '#event',
            'name' => (string) $entry['title'],
            'description' => $desc,
            'image' => $image ?: null,
            'startDate' => !empty($entry['event_start']) ? date('c', strtotime((string) $entry['event_start'] . ' UTC')) : null,
            'endDate' => !empty($entry['event_end']) ? date('c', strtotime((string) $entry['event_end'] . ' UTC')) : null,
            'eventAttendanceMode' => 'https://schema.org/OnlineEventAttendanceMode',
            'eventStatus' => 'https://schema.org/EventScheduled',
            'location' => [
                '@type' => 'VirtualLocation',
                'url' => $pageUrl,
                'name' => (string) ($entry['event_location'] ?: 'Arowin Online'),
            ],
            'organizer' => ['@id' => $homeUrl . '#organization'],
        ]]);
    }
}
