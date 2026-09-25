<?php
/**
 * Offline fallback — cached by service worker
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/bootstrap.php';

$CURRENT_ROUTE = 'offline';

arowin_prepare_page([
    'title'       => 'Offline — Arowin Trading Portal',
    'title_short' => 'Offline',
    'description' => 'You are offline. Cached Arowin content may still be available.',
    'canonical'   => '/offline',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

$basePath = (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$homeUrl = url('home');
$loginUrl = url('login');
$dashUrl = url('dashboard');
$pwaJs = asset_v('js/pwa.js');
$icon = img('favicon-192x192.png');
?>
<!DOCTYPE html>
<html lang="<?= e($APP['language']) ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($SEO['title']) ?></title>
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="<?= e($APP['theme_color']) ?>">
  <meta name="arowin-base" content="<?= e($basePath) ?>">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <link rel="manifest" href="<?= e(arowin_abs($BASE, '/manifest.json')) ?>">
  <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
  <link rel="apple-touch-icon" href="<?= e(img('apple-touch-icon.png')) ?>">
  <style>
    :root {
      --bg0: #070707;
      --bg1: #121212;
      --line: rgba(255,255,255,.08);
      --text: #f5f5f5;
      --muted: #a3a3a3;
      --accent: #ff8c1a;
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      font-family: "Segoe UI", "Helvetica Neue", sans-serif;
      color: var(--text);
      background:
        radial-gradient(1200px 600px at 10% -10%, rgba(255,140,26,.18), transparent 55%),
        radial-gradient(900px 500px at 100% 0%, rgba(255,255,255,.05), transparent 50%),
        linear-gradient(160deg, var(--bg0), #0d0d0d 45%, #111);
    }
    .wrap {
      max-width: 720px;
      margin: 0 auto;
      padding: 48px 20px 64px;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 28px;
    }
    .brand img {
      width: 44px;
      height: 44px;
      border-radius: 10px;
    }
    .brand span {
      font-size: 1.35rem;
      font-weight: 800;
      letter-spacing: .04em;
    }
    h1 {
      font-size: clamp(1.8rem, 4vw, 2.4rem);
      line-height: 1.15;
      margin: 0 0 12px;
      letter-spacing: -.02em;
    }
    .lead {
      color: var(--muted);
      font-size: 1.05rem;
      line-height: 1.55;
      margin: 0 0 28px;
      max-width: 36rem;
    }
    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-bottom: 36px;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 18px;
      border-radius: 10px;
      text-decoration: none;
      font-weight: 650;
      font-size: .95rem;
      border: 1px solid var(--line);
      color: var(--text);
      background: rgba(255,255,255,.04);
    }
    .btn.primary {
      background: var(--accent);
      border-color: transparent;
      color: #111;
    }
    #offline-snapshot { margin-top: 8px; }
    .offline-empty {
      color: var(--muted);
      border: 1px dashed var(--line);
      border-radius: 14px;
      padding: 18px;
      background: rgba(0,0,0,.25);
    }
    .offline-card {
      border: 1px solid var(--line);
      border-radius: 16px;
      padding: 20px;
      background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(0,0,0,.2));
    }
    .offline-eyebrow {
      margin: 0 0 8px;
      color: var(--accent);
      font-size: .78rem;
      font-weight: 700;
      letter-spacing: .08em;
      text-transform: uppercase;
    }
    .offline-card h2 {
      margin: 0 0 6px;
      font-size: 1.35rem;
    }
    .offline-meta {
      margin: 0 0 18px;
      color: var(--muted);
      font-size: .92rem;
    }
    .offline-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
      gap: 10px;
    }
    .offline-stat {
      border: 1px solid var(--line);
      border-radius: 12px;
      padding: 12px;
      background: rgba(0,0,0,.28);
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .offline-stat span {
      color: var(--muted);
      font-size: .78rem;
    }
    .offline-stat strong {
      font-size: 1.05rem;
    }
    .offline-saved {
      margin: 16px 0 0;
      color: var(--muted);
      font-size: .8rem;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <img src="<?= e($icon) ?>" alt="" width="44" height="44">
      <span>AROWIN</span>
    </div>
    <h1>You’re offline</h1>
    <p class="lead">
      Live login, genealogy updates, and USDT ledgers need a connection.
      Cached pages and your last dashboard snapshot may still be available.
    </p>
    <div class="actions">
      <a class="btn primary" href="<?= e($homeUrl) ?>">Try home</a>
      <a class="btn" href="<?= e($dashUrl) ?>">Dashboard</a>
      <a class="btn" href="<?= e($loginUrl) ?>">Login</a>
    </div>
    <div id="offline-snapshot" aria-live="polite"></div>
  </div>
  <script>window.AROWIN_BASE_PATH = <?= json_encode($basePath, JSON_UNESCAPED_SLASHES) ?>;</script>
  <script src="<?= e($pwaJs) ?>" defer></script>
</body>
</html>
