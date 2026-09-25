<?php
/**
 * SEO page meta, redirects, and site-wide SEO settings (admin-managed).
 */
declare(strict_types=1);

function arowin_ensure_seo_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once __DIR__ . '/perf.php';
    }
    arowin_schema_once('seo', 1, static function (): void {
    $pdo = arowin_db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS seo_page_meta (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        path VARCHAR(255) NOT NULL,
        meta_title VARCHAR(255) NULL,
        meta_description VARCHAR(512) NULL,
        meta_keywords VARCHAR(512) NULL,
        canonical_url VARCHAR(512) NULL,
        robots VARCHAR(128) NULL,
        og_title VARCHAR(255) NULL,
        og_description VARCHAR(512) NULL,
        og_image VARCHAR(512) NULL,
        og_type VARCHAR(64) NULL,
        twitter_title VARCHAR(255) NULL,
        twitter_description VARCHAR(512) NULL,
        twitter_image VARCHAR(512) NULL,
        schema_json MEDIUMTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_seo_path (path)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS seo_redirects (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        from_path VARCHAR(255) NOT NULL,
        to_path VARCHAR(512) NOT NULL,
        status_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        hits INT UNSIGNED NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_redirect_from (from_path),
        KEY idx_redirect_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS seo_site_settings (
        setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
        setting_value MEDIUMTEXT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    });
}

/**
 * @return array<string,mixed>|null
 */
function arowin_seo_get_page_meta(string $path): ?array
{
    arowin_ensure_seo_schema();
    $path = arowin_seo_normalize_path($path);
    $stmt = arowin_db()->prepare('SELECT * FROM seo_page_meta WHERE path = ? LIMIT 1');
    $stmt->execute([$path]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_seo_list_page_meta(): array
{
    arowin_ensure_seo_schema();
    $stmt = arowin_db()->query('SELECT * FROM seo_page_meta ORDER BY path ASC');
    return $stmt->fetchAll() ?: [];
}

/**
 * @param array<string,mixed> $data
 */
function arowin_seo_save_page_meta(string $path, array $data): void
{
    arowin_ensure_seo_schema();
    $path = arowin_seo_normalize_path($path);
    $pdo = arowin_db();
    $sql = 'INSERT INTO seo_page_meta
        (path, meta_title, meta_description, meta_keywords, canonical_url, robots,
         og_title, og_description, og_image, og_type,
         twitter_title, twitter_description, twitter_image, schema_json)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE
         meta_title=VALUES(meta_title),
         meta_description=VALUES(meta_description),
         meta_keywords=VALUES(meta_keywords),
         canonical_url=VALUES(canonical_url),
         robots=VALUES(robots),
         og_title=VALUES(og_title),
         og_description=VALUES(og_description),
         og_image=VALUES(og_image),
         og_type=VALUES(og_type),
         twitter_title=VALUES(twitter_title),
         twitter_description=VALUES(twitter_description),
         twitter_image=VALUES(twitter_image),
         schema_json=VALUES(schema_json)';
    $pdo->prepare($sql)->execute([
        $path,
        arowin_seo_nullstr($data['meta_title'] ?? null),
        arowin_seo_nullstr($data['meta_description'] ?? null),
        arowin_seo_nullstr($data['meta_keywords'] ?? null),
        arowin_seo_nullstr($data['canonical_url'] ?? null),
        arowin_seo_nullstr($data['robots'] ?? null),
        arowin_seo_nullstr($data['og_title'] ?? null),
        arowin_seo_nullstr($data['og_description'] ?? null),
        arowin_seo_nullstr($data['og_image'] ?? null),
        arowin_seo_nullstr($data['og_type'] ?? null),
        arowin_seo_nullstr($data['twitter_title'] ?? null),
        arowin_seo_nullstr($data['twitter_description'] ?? null),
        arowin_seo_nullstr($data['twitter_image'] ?? null),
        arowin_seo_nullstr($data['schema_json'] ?? null),
    ]);
    if (function_exists('arowin_micro_cache_forget')) {
        arowin_micro_cache_forget('seo_page_meta_' . md5($path));
    }
    arowin_invalidate_sitemap();
}

function arowin_seo_delete_page_meta(int $id): void
{
    arowin_ensure_seo_schema();
    arowin_db()->prepare('DELETE FROM seo_page_meta WHERE id = ?')->execute([$id]);
    arowin_invalidate_sitemap();
}

/**
 * @return array{from_path:string,to_path:string,status_code:int}|null
 */
function arowin_seo_find_redirect(string $path): ?array
{
    arowin_ensure_seo_schema();
    $path = arowin_seo_normalize_path($path);
    if (!function_exists('arowin_micro_cache')) {
        require_once __DIR__ . '/perf.php';
    }
    /** @var array<string,array{from_path:string,to_path:string,status_code:int}> $map */
    $map = arowin_micro_cache('seo_redirects_map', 120, static function (): array {
        $rows = arowin_db()->query(
            'SELECT from_path, to_path, status_code FROM seo_redirects WHERE is_active = 1'
        )->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $from = (string) ($row['from_path'] ?? '');
            if ($from === '') {
                continue;
            }
            $out[$from] = [
                'from_path' => $from,
                'to_path' => (string) ($row['to_path'] ?? ''),
                'status_code' => (int) ($row['status_code'] ?? 301),
            ];
        }
        return $out;
    });
    if (!is_array($map) || !isset($map[$path])) {
        return null;
    }
    try {
        arowin_db()->prepare('UPDATE seo_redirects SET hits = hits + 1 WHERE from_path = ?')->execute([$path]);
    } catch (Throwable $e) {
        // non-fatal
    }
    return $map[$path];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_seo_list_redirects(): array
{
    arowin_ensure_seo_schema();
    return arowin_db()->query('SELECT * FROM seo_redirects ORDER BY from_path ASC')->fetchAll() ?: [];
}

/**
 * @param array{from_path:string,to_path:string,status_code?:int,is_active?:int} $data
 */
function arowin_seo_save_redirect(?int $id, array $data): void
{
    arowin_ensure_seo_schema();
    $from = arowin_seo_normalize_path((string) ($data['from_path'] ?? ''));
    $to = trim((string) ($data['to_path'] ?? ''));
    $code = (int) ($data['status_code'] ?? 301);
    $active = !empty($data['is_active']) ? 1 : 0;
    if ($from === '' || $to === '') {
        throw new InvalidArgumentException('Redirect requires from and to paths.');
    }
    if ($id) {
        arowin_db()->prepare(
            'UPDATE seo_redirects SET from_path=?, to_path=?, status_code=?, is_active=? WHERE id=?'
        )->execute([$from, $to, $code, $active, $id]);
    } else {
        arowin_db()->prepare(
            'INSERT INTO seo_redirects (from_path, to_path, status_code, is_active) VALUES (?,?,?,?)
             ON DUPLICATE KEY UPDATE to_path=VALUES(to_path), status_code=VALUES(status_code), is_active=VALUES(is_active)'
        )->execute([$from, $to, $code, $active]);
    }
    if (function_exists('arowin_micro_cache_forget')) {
        arowin_micro_cache_forget('seo_redirects_map');
    }
}

function arowin_seo_delete_redirect(int $id): void
{
    arowin_ensure_seo_schema();
    arowin_db()->prepare('DELETE FROM seo_redirects WHERE id = ?')->execute([$id]);
    if (function_exists('arowin_micro_cache_forget')) {
        arowin_micro_cache_forget('seo_redirects_map');
    }
}

/**
 * @return array<string,string>
 */
function arowin_seo_get_settings(): array
{
    arowin_ensure_seo_schema();
    $rows = arowin_db()->query('SELECT setting_key, setting_value FROM seo_site_settings')->fetchAll() ?: [];
    $out = [];
    foreach ($rows as $r) {
        $out[(string) $r['setting_key']] = (string) ($r['setting_value'] ?? '');
    }
    return $out;
}

function arowin_seo_get_setting(string $key, string $default = ''): string
{
    $all = arowin_seo_get_settings();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

/**
 * @param array<string,string> $pairs
 */
function arowin_seo_save_settings(array $pairs): void
{
    arowin_ensure_seo_schema();
    $stmt = arowin_db()->prepare(
        'INSERT INTO seo_site_settings (setting_key, setting_value) VALUES (?,?)
         ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)'
    );
    foreach ($pairs as $k => $v) {
        $stmt->execute([(string) $k, (string) $v]);
    }
    if (function_exists('arowin_micro_cache_forget')) {
        arowin_micro_cache_forget('seo_site_settings');
    }
    arowin_invalidate_sitemap();
}

function arowin_seo_normalize_path(string $path): string
{
    $path = trim($path);
    if ($path === '') {
        return '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        $parsed = parse_url($path, PHP_URL_PATH);
        $path = is_string($parsed) ? $parsed : '/';
    }
    $path = '/' . ltrim($path, '/');
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return $path;
}

function arowin_seo_nullstr(mixed $v): ?string
{
    if ($v === null) {
        return null;
    }
    $s = trim((string) $v);
    return $s === '' ? null : $s;
}
