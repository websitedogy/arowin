<?php
/**
 * Performance helpers — schema stamps, micro-cache, timing
 */
declare(strict_types=1);

/**
 * Run expensive schema DDL at most once per version (across requests).
 * Bump $version when schema SQL changes.
 */
function arowin_schema_once(string $key, int $version, callable $install): void
{
    static $mem = [];
    $memKey = $key . '@' . $version;
    if (isset($mem[$memKey])) {
        return;
    }

    $dir = dirname(__DIR__) . '/storage/cache';
    $stamp = $dir . '/schema_' . preg_replace('/[^a-z0-9_\-]/i', '_', $key) . '.v' . $version;
    if (is_file($stamp)) {
        $mem[$memKey] = true;
        return;
    }

    $install();

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents($stamp, (string) time());
    $mem[$memKey] = true;
}

/**
 * Short-lived file cache (seconds). Returns cached value or computes via $producer.
 *
 * @template T
 * @param callable():T $producer
 * @return T
 */
function arowin_micro_cache(string $key, int $ttlSeconds, callable $producer): mixed
{
    static $mem = [];
    if (array_key_exists($key, $mem)) {
        return $mem[$key];
    }

    $dir = dirname(__DIR__) . '/storage/cache';
    $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $key) ?: 'k';
    $file = $dir . '/mc_' . $safe . '.json';
    $ttlSeconds = max(1, $ttlSeconds);

    if (is_file($file)) {
        $mtime = (int) @filemtime($file);
        if ($mtime > 0 && (time() - $mtime) < $ttlSeconds) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && array_key_exists('v', $decoded)) {
                    $mem[$key] = $decoded['v'];
                    return $mem[$key];
                }
            }
        }
    }

    $value = $producer();
    $mem[$key] = $value;

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    @file_put_contents(
        $file,
        json_encode(['v' => $value, 't' => time()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        LOCK_EX
    );

    return $value;
}

function arowin_micro_cache_forget(string $key): void
{
    $dir = dirname(__DIR__) . '/storage/cache';
    $safe = preg_replace('/[^a-z0-9_\-]/i', '_', $key) ?: 'k';
    $file = $dir . '/mc_' . $safe . '.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

/**
 * Start request timing (optional debug via ?_perf=1 or APP_PERF=1).
 */
function arowin_perf_start(): void
{
    $GLOBALS['AROWIN_PERF_START'] = hrtime(true);
}

function arowin_perf_ms(): float
{
    $start = $GLOBALS['AROWIN_PERF_START'] ?? null;
    if ($start === null) {
        return 0.0;
    }
    return (hrtime(true) - (int) $start) / 1e6;
}

function arowin_perf_should_show(): bool
{
    if (!empty(env('APP_PERF', false))) {
        return true;
    }
    return isset($_GET['_perf']) && (string) $_GET['_perf'] === '1';
}

function arowin_perf_header(): void
{
    if (headers_sent()) {
        return;
    }
    $ms = arowin_perf_ms();
    if ($ms > 0) {
        header('Server-Timing: app;desc="PHP";dur=' . number_format($ms, 1, '.', ''));
        header('X-Arowin-Perf-Ms: ' . number_format($ms, 1, '.', ''));
    }
}
