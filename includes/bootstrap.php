<?php
/**
 * Bootstrap — load config, detect base URL, SEO helpers
 *
 * Modes (set before require, or auto-detected):
 * - light: auth/register only ($AROWIN_BOOT_LIGHT)
 * - admin: lean admin desk ($AROWIN_BOOT_ADMIN)
 * - api: minimal JSON/webhook ($AROWIN_API_MODE)
 * - public: marketing/legal (auto when URI is not /dashboard or /admin)
 * - full: member dashboard repositories
 */
declare(strict_types=1);

require_once __DIR__ . '/env.php';
arowin_load_env();
require_once __DIR__ . '/perf.php';
arowin_perf_start();

$APP  = require __DIR__ . '/../config/app.php';
$SEO  = require __DIR__ . '/../config/seo.php';
$NAV  = require __DIR__ . '/../config/navigation.php';
$DB   = require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/security.php';

$appTz = (string) ($APP['timezone'] ?? 'Asia/Kolkata');
if ($appTz === '' || @timezone_open($appTz) === false) {
    $appTz = 'Asia/Kolkata';
}
date_default_timezone_set($appTz);

/**
 * Format a DB datetime in India time (IST).
 * Values without a timezone are treated as UTC storage, then shown in Asia/Kolkata.
 */
function arowin_format_india_time(?string $dt, string $format = 'd M Y, h:i A'): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '—';
    }
    try {
        if (preg_match('/[zZ]|[+-]\d{2}:?\d{2}$/', $dt)) {
            $parsed = new DateTimeImmutable($dt);
        } else {
            // TIMESTAMP / DATETIME from MySQL — interpret as UTC wall clock then convert.
            $parsed = new DateTimeImmutable($dt, new DateTimeZone('UTC'));
        }
        return $parsed
            ->setTimezone(new DateTimeZone('Asia/Kolkata'))
            ->format($format) . ' IST';
    } catch (Throwable $e) {
        return $dt;
    }
}

$AROWIN_BOOT_LIGHT = !empty($AROWIN_BOOT_LIGHT ?? false);
$AROWIN_BOOT_ADMIN = !empty($AROWIN_BOOT_ADMIN ?? false);
// JSON/API endpoints set $AROWIN_API_MODE = true before requiring bootstrap
$AROWIN_API_MODE = !empty($AROWIN_API_MODE ?? false);

$reqPathEarly = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isDashboardReq = str_contains($reqPathEarly, '/dashboard');
$isAdminPathEarly = str_contains($reqPathEarly, '/admin');
$isCli = PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
// Marketing / legal / CMS — skip wallet/team/income stacks.
// CLI cron (rank bonus / daily yield) must load the full income stack.
$AROWIN_BOOT_PUBLIC = !$AROWIN_BOOT_LIGHT
    && !$AROWIN_BOOT_ADMIN
    && !$AROWIN_API_MODE
    && !$isDashboardReq
    && !$isAdminPathEarly
    && !$isCli;

if ($AROWIN_BOOT_LIGHT) {
    require_once __DIR__ . '/register-repository.php';
    require_once __DIR__ . '/auth.php';
} elseif ($AROWIN_BOOT_ADMIN) {
    // Admin desk — lean boot (page-specific repos load on demand)
    require_once __DIR__ . '/register-repository.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/packages-catalog.php';
    require_once __DIR__ . '/package-power.php';
    require_once __DIR__ . '/purchase-repository.php';
    require_once __DIR__ . '/wallet-repository.php';
    require_once __DIR__ . '/crypto-helpers.php';
    require_once __DIR__ . '/nowpayments-service.php';
    require_once __DIR__ . '/deposit-repository.php';
    require_once __DIR__ . '/withdrawal-repository.php';
    require_once __DIR__ . '/withdrawal-settings-repository.php';
    require_once __DIR__ . '/income-repository.php';
    require_once __DIR__ . '/admin-metrics-repository.php';
    require_once __DIR__ . '/seo-engine.php';
} elseif ($AROWIN_API_MODE) {
    require_once __DIR__ . '/register-repository.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/seo-engine.php';
} elseif ($AROWIN_BOOT_PUBLIC) {
    require_once __DIR__ . '/contact-repository.php';
    require_once __DIR__ . '/register-repository.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/seo-engine.php';
    require_once __DIR__ . '/seo-settings-repository.php';
} else {
    require_once __DIR__ . '/contact-repository.php';
    require_once __DIR__ . '/register-repository.php';
    require_once __DIR__ . '/auth.php';
    require_once __DIR__ . '/dashboard-repository.php';
    require_once __DIR__ . '/profile-repository.php';
    require_once __DIR__ . '/team-repository.php';
    require_once __DIR__ . '/ranks-repository.php';
    require_once __DIR__ . '/packages-catalog.php';
    require_once __DIR__ . '/package-power.php';
    require_once __DIR__ . '/purchase-repository.php';
    require_once __DIR__ . '/wallet-repository.php';
    require_once __DIR__ . '/crypto-helpers.php';
    require_once __DIR__ . '/nowpayments-service.php';
    require_once __DIR__ . '/deposit-repository.php';
    require_once __DIR__ . '/withdrawal-repository.php';
    require_once __DIR__ . '/withdrawal-settings-repository.php';
    require_once __DIR__ . '/income-repository.php';
    require_once __DIR__ . '/daily-yield-repository.php';
    require_once __DIR__ . '/rank-bonus-repository.php';
    require_once __DIR__ . '/admin-metrics-repository.php';
    require_once __DIR__ . '/seo-engine.php';
    require_once __DIR__ . '/seo-settings-repository.php';
    // CMS + sitemap loaded on demand (public CMS / sitemap endpoints)
}

// Prefer APP_URL from .env when set
if (!empty(env('APP_URL'))) {
    $APP['base_url'] = rtrim((string) env('APP_URL'), '/');
}
if (env('APP_NAME')) {
    $APP['name'] = (string) env('APP_NAME');
}

/**
 * Detect scheme + host + project web root from the current HTTP request.
 */
function arowin_request_base_url(): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    $scheme = $https ? 'https' : 'http';
    $host   = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    $docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
    $appRoot = realpath(dirname(__DIR__));

    $basePath = '';
    if ($docRoot && $appRoot && str_starts_with($appRoot, $docRoot)) {
        $basePath = str_replace('\\', '/', substr($appRoot, strlen($docRoot)));
        $basePath = rtrim($basePath, '/');
    }

    return $scheme . '://' . $host . $basePath;
}

/**
 * Public site base URL for assets/links.
 * Uses APP_URL when the browser host matches; otherwise follows the request host
 * so LAN/mobile access (e.g. 192.168.x.x) still loads CSS/JS.
 */
function arowin_base_url(array $app): string
{
    $requestBase = arowin_request_base_url();
    $configured = !empty($app['base_url']) ? rtrim((string) $app['base_url'], '/') : '';
    if ($configured === '') {
        return $requestBase;
    }

    $reqHostHeader = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if ($reqHostHeader === '') {
        return $configured;
    }

    $cfgHost = strtolower((string) (parse_url($configured, PHP_URL_HOST) ?? ''));
    $reqHost = strtolower((string) (parse_url('http://' . $reqHostHeader, PHP_URL_HOST) ?? ''));
    if ($cfgHost === '' || $reqHost === '' || $cfgHost === $reqHost) {
        return $configured;
    }

    // Browser opened via a different host (phone on LAN IP, 127.0.0.1, etc.)
    return $requestBase;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function arowin_abs(string $base, string $path): string
{
    if ($path === '') {
        return $base . '/';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    if (str_starts_with($path, '#')) {
        return $base . '/' . $path;
    }
    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    global $BASE;
    return arowin_abs($BASE, '/public/' . ltrim($path, '/'));
}

function img(string $file): string
{
    return asset('assets/images/' . ltrim($file, '/'));
}

/**
 * Named route → absolute URL (from config/navigation.php routes).
 */
function url(string $route = 'home'): string
{
    global $BASE, $NAV;
    $path = $NAV['routes'][$route] ?? '/' . ltrim($route, '/');
    if (str_contains($path, '#')) {
        [$p, $hash] = explode('#', $path, 2);
        $p = $p === '' || $p === '/' ? '' : rtrim($p, '/');
        $absolute = arowin_abs($BASE, $p === '' ? '/' : $p) . '#' . $hash;
    } else {
        $absolute = arowin_abs($BASE, $path);
    }

    // Multi-tab account context — skip public marketing / auth entry routes
    $skipAs = ['home', 'login', 'register', 'sign-in', 'welcome-login'];
    if (!in_array($route, $skipAs, true) && function_exists('arowin_url_with_account')) {
        return arowin_url_with_account($absolute);
    }
    return $absolute;
}

/**
 * Apply per-page SEO overrides before including head.php
 *
 * @param array{
 *   title?:string,
 *   title_short?:string,
 *   description?:string,
 *   keywords?:string,
 *   canonical?:string,
 *   robots?:string,
 *   og_type?:string,
 *   faqs?:bool
 * } $page
 */
function arowin_prepare_page(array $page): void
{
    global $SEO, $CANONICAL, $BASE, $PAGE_SHOW_FAQS, $OG_IMAGE;

    if (!empty($page['title'])) {
        $SEO['title'] = $page['title'];
    }
    if (!empty($page['title_short'])) {
        $SEO['title_short'] = $page['title_short'];
    } elseif (!empty($page['title'])) {
        $SEO['title_short'] = $page['title'];
    }
    if (!empty($page['description'])) {
        $SEO['description'] = $page['description'];
    }
    if (!empty($page['keywords'])) {
        $SEO['keywords'] = $page['keywords'];
    }
    if (!empty($page['robots'])) {
        $SEO['robots'] = $page['robots'];
    }
    if (!empty($page['og_type'])) {
        $SEO['og_type'] = $page['og_type'];
    }
    if (!empty($page['canonical'])) {
        $CANONICAL = arowin_abs($BASE, $page['canonical']);
    }
    if (!empty($page['og_image'])) {
        $SEO['og_image'] = $page['og_image'];
        $OG_IMAGE = arowin_abs($BASE, (string) $page['og_image']);
    }

    $PAGE_SHOW_FAQS = !empty($page['faqs']);
    if (!$PAGE_SHOW_FAQS) {
        $SEO['faqs'] = [];
    }
}

function asset_v(string $relativePath): string
{
    $href = asset($relativePath);
    $full = dirname(__DIR__) . '/public/' . ltrim($relativePath, '/');
    if (is_file($full)) {
        $href .= '?v=' . filemtime($full);
    }
    return $href;
}

/**
 * Flush buffered HTML so the browser can paint while PHP finishes the rest.
 */
function arowin_flush_response(): void
{
    if (function_exists('arowin_perf_header')) {
        arowin_perf_header();
    }
    if (ob_get_level() > 0) {
        @ob_flush();
    }
    @flush();
}

$BASE       = arowin_base_url($APP);
$CANONICAL  = arowin_abs($BASE, '/');
$OG_IMAGE   = arowin_abs($BASE, $SEO['og_image']);
$YEAR       = (int) date('Y');
$COPY_RANGE = ((int) $APP['copyright_start'] < $YEAR)
    ? $APP['copyright_start'] . ' – ' . $YEAR
    : (string) $YEAR;
$PAGE_SHOW_FAQS = true;
$CURRENT_ROUTE  = 'home';

// HTTPS-only when APP_URL is https (production) — skip CLI / local http testing
if (
    empty($AROWIN_API_MODE ?? false)
    && PHP_SAPI !== 'cli'
    && !headers_sent()
    && !empty(env('APP_URL'))
    && str_starts_with(strtolower((string) env('APP_URL')), 'https://')
) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if (!$https) {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $host = (string) (parse_url((string) env('APP_URL'), PHP_URL_HOST) ?: ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        header('Location: https://' . $host . $uri, true, 301);
        exit;
    }
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
}
$CSS_HREF        = asset_v('css/app.css');
$CSS_NAVBAR_HREF = asset_v('css/navbar.css');
$CSS_FOOTER_HREF = asset_v('css/footer.css');
$CSS_PAGES_HREF  = asset_v('css/pages.css');
$JS_HREF         = asset_v('js/app.js');
$JS_NAVBAR_HREF  = asset_v('js/navbar.js');

if (!$AROWIN_API_MODE) {
    // Apache mod_deflate already compresses — avoid PHP ob_gzhandler (delays first paint)
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        // Dynamic pages: no long buffer cache
        header('Cache-Control: private, max-age=0, must-revalidate');
    }
    // Small buffer only so redirects can clear accidental output
    if (ob_get_level() === 0) {
        ob_start();
    }
} else {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

// Multi-account tabs: activate ?as= / header account when session already started
if (session_status() === PHP_SESSION_ACTIVE && function_exists('arowin_apply_request_account')) {
    arowin_apply_request_account();
}

// Kick blocked members out of an existing session (admin "login as" still allowed)
if (
    empty($AROWIN_API_MODE ?? false)
    && empty($AROWIN_BOOT_ADMIN ?? false)
    && session_status() === PHP_SESSION_ACTIVE
    && function_exists('arowin_is_logged_in')
    && arowin_is_logged_in()
    && empty($_SESSION['arowin_admin_impersonating'])
    && function_exists('arowin_get_user_by_username')
    && function_exists('arowin_user_is_blocked')
) {
    $uidCheck = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($uidCheck !== '') {
        $liveUser = arowin_get_user_by_username($uidCheck);
        if (is_array($liveUser) && arowin_user_is_blocked($liveUser)) {
            arowin_logout_user($uidCheck);
            if (!headers_sent() && str_contains((string) ($_SERVER['REQUEST_URI'] ?? ''), '/dashboard')) {
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $_SESSION['arowin_flash'] = 'This account has been blocked. You cannot sign in.';
                header('Location: ' . url('login'), true, 303);
                exit;
            }
        }
    }
}

// Apply DB-managed redirects (SEO panel) before page render — skip admin/API
$reqPath = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isAdminReq = str_contains($reqPath, '/admin');
if (
    !$AROWIN_API_MODE
    && !$AROWIN_BOOT_ADMIN
    && !$isAdminReq
    && function_exists('arowin_process_redirects')
) {
    arowin_process_redirects();
}

// Merge admin SEO site settings (cached) — skip admin desk HTML
if (
    !$AROWIN_BOOT_LIGHT
    && !$AROWIN_BOOT_ADMIN
    && !$AROWIN_API_MODE
    && !$isAdminReq
) {
    if (!function_exists('arowin_seo_get_settings')) {
        require_once __DIR__ . '/seo-settings-repository.php';
    }
    try {
        $seoSettings = arowin_micro_cache('seo_site_settings', 60, static function (): array {
            return arowin_seo_get_settings();
        });
        if (!is_array($seoSettings)) {
            $seoSettings = [];
        }
        if (!empty($seoSettings['twitter_site'])) {
            $SEO['twitter_site'] = $seoSettings['twitter_site'];
        }
        if (!empty($seoSettings['og_image'])) {
            $SEO['og_image'] = $seoSettings['og_image'];
            $OG_IMAGE = arowin_abs($BASE, $seoSettings['og_image']);
        }
        if (!empty($seoSettings['default_robots'])) {
            $SEO['robots'] = $seoSettings['default_robots'];
        }
        foreach (['x', 'instagram', 'youtube', 'telegram', 'linkedin', 'facebook', 'reddit'] as $net) {
            $key = 'social_' . $net;
            if (!empty($seoSettings[$key])) {
                $APP['social'][$net] = $seoSettings[$key];
            }
        }
        if (!empty($seoSettings['legal_name'])) {
            $APP['legal_name'] = $seoSettings['legal_name'];
        }
        if (!empty($seoSettings['contact_email'])) {
            $APP['contact_email'] = $seoSettings['contact_email'];
        }
        $SEO['local_business'] = [
            'enabled' => ($seoSettings['localbusiness_enabled'] ?? '0') === '1',
            'street' => $seoSettings['lb_street'] ?? '',
            'city' => $seoSettings['lb_city'] ?? '',
            'region' => $seoSettings['lb_region'] ?? '',
            'postal' => $seoSettings['lb_postal'] ?? '',
            'country' => $seoSettings['lb_country'] ?? 'IN',
            'phone' => $seoSettings['lb_phone'] ?? '',
            'lat' => $seoSettings['lb_lat'] ?? '',
            'lng' => $seoSettings['lb_lng'] ?? '',
        ];
    } catch (Throwable $e) {
        // DB may be unavailable during early install — keep file defaults
    }
}

if (!$AROWIN_API_MODE && function_exists('arowin_perf_header')) {
    register_shutdown_function(static function (): void {
        arowin_perf_header();
    });
}
