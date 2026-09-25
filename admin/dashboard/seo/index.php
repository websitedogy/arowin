<?php
/**
 * Admin — SEO Control Panel
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/includes/seo-settings-repository.php';

$ADMIN_PAGE = 'seo';
$ADMIN_TITLE = 'SEO';
$ADMIN_SUBTITLE = 'Titles, social, LocalBusiness, crawl settings';

$flash = '';
$settings = arowin_seo_get_settings();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    arowin_require_post_csrf();
    $action = (string) ($_POST['action'] ?? 'save_settings');

    if ($action === 'save_settings') {
        arowin_seo_save_settings([
            'legal_name' => trim((string) ($_POST['legal_name'] ?? '')),
            'contact_email' => trim((string) ($_POST['contact_email'] ?? '')),
            'twitter_site' => trim((string) ($_POST['twitter_site'] ?? '')),
            'og_image' => trim((string) ($_POST['og_image'] ?? '')),
            'default_robots' => trim((string) ($_POST['default_robots'] ?? '')),
            'google_site_verification' => trim((string) ($_POST['google_site_verification'] ?? '')),
            'bing_site_verification' => trim((string) ($_POST['bing_site_verification'] ?? '')),
            'social_x' => trim((string) ($_POST['social_x'] ?? '')),
            'social_instagram' => trim((string) ($_POST['social_instagram'] ?? '')),
            'social_youtube' => trim((string) ($_POST['social_youtube'] ?? '')),
            'social_telegram' => trim((string) ($_POST['social_telegram'] ?? '')),
            'social_linkedin' => trim((string) ($_POST['social_linkedin'] ?? '')),
            'social_facebook' => trim((string) ($_POST['social_facebook'] ?? '')),
            'social_reddit' => trim((string) ($_POST['social_reddit'] ?? '')),
            'localbusiness_enabled' => isset($_POST['localbusiness_enabled']) ? '1' : '0',
            'lb_street' => trim((string) ($_POST['lb_street'] ?? '')),
            'lb_city' => trim((string) ($_POST['lb_city'] ?? '')),
            'lb_region' => trim((string) ($_POST['lb_region'] ?? '')),
            'lb_postal' => trim((string) ($_POST['lb_postal'] ?? '')),
            'lb_country' => trim((string) ($_POST['lb_country'] ?? 'IN')),
            'lb_phone' => trim((string) ($_POST['lb_phone'] ?? '')),
            'lb_lat' => trim((string) ($_POST['lb_lat'] ?? '')),
            'lb_lng' => trim((string) ($_POST['lb_lng'] ?? '')),
        ]);
        arowin_invalidate_sitemap();
        $flash = 'SEO settings saved. Sitemap stamp refreshed.';
        $settings = arowin_seo_get_settings();
    }
}

$s = static function (string $key, string $default = '') use ($settings): string {
    return (string) ($settings[$key] ?? $default);
};

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<?php if ($flash !== ''): ?>
  <div class="admin-flash ok" role="status"><?= e($flash) ?></div>
<?php endif; ?>

<section class="admin-panel">
  <div class="admin-panel-head">
    <h2>Site-wide SEO</h2>
    <span class="admin-muted">
      <a href="<?= e(admin_url('/dashboard/seo/pages')) ?>">Page meta</a> ·
      <a href="<?= e(admin_url('/dashboard/seo/redirects')) ?>">Redirects</a> ·
      <a href="<?= e(admin_url('/dashboard/cms')) ?>">CMS content</a> ·
      <a href="<?= e(arowin_abs($BASE, '/sitemap.xml')) ?>" target="_blank" rel="noopener">sitemap.xml</a>
    </span>
  </div>

  <form method="post" class="admin-form" action="">
    <input type="hidden" name="csrf" value="<?= e(arowin_csrf_token()) ?>">
    <input type="hidden" name="action" value="save_settings">

    <div class="admin-form-grid">
      <label>Legal / Organization name
        <input name="legal_name" value="<?= e($s('legal_name', 'Arowin Trading Company')) ?>">
      </label>
      <label>Contact email
        <input type="email" name="contact_email" value="<?= e($s('contact_email', (string) ($APP['contact_email'] ?? ''))) ?>">
      </label>
      <label>Default robots
        <input name="default_robots" value="<?= e($s('default_robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1')) ?>">
      </label>
      <label>Default OG image path
        <input name="og_image" value="<?= e($s('og_image', '/public/assets/images/og-arowin.png')) ?>">
      </label>
      <label>Twitter @site
        <input name="twitter_site" placeholder="@arowin" value="<?= e($s('twitter_site')) ?>">
      </label>
      <label>Google site verification
        <input name="google_site_verification" placeholder="Search Console meta token" value="<?= e($s('google_site_verification')) ?>">
      </label>
      <label>Bing site verification
        <input name="bing_site_verification" value="<?= e($s('bing_site_verification')) ?>">
      </label>
    </div>

    <h3 class="admin-subhead">Social profiles (Organization sameAs)</h3>
    <div class="admin-form-grid">
      <?php foreach (['x','instagram','youtube','telegram','linkedin','facebook','reddit'] as $net): ?>
        <label><?= e(ucfirst($net)) ?> URL
          <input name="social_<?= e($net) ?>" value="<?= e($s('social_' . $net, (string) ($APP['social'][$net] ?? ''))) ?>">
        </label>
      <?php endforeach; ?>
    </div>

    <h3 class="admin-subhead">LocalBusiness schema</h3>
    <label class="admin-check">
      <input type="checkbox" name="localbusiness_enabled" value="1" <?= $s('localbusiness_enabled') === '1' ? 'checked' : '' ?>>
      Enable LocalBusiness JSON-LD
    </label>
    <div class="admin-form-grid">
      <label>Street <input name="lb_street" value="<?= e($s('lb_street')) ?>"></label>
      <label>City <input name="lb_city" value="<?= e($s('lb_city')) ?>"></label>
      <label>Region <input name="lb_region" value="<?= e($s('lb_region')) ?>"></label>
      <label>Postal <input name="lb_postal" value="<?= e($s('lb_postal')) ?>"></label>
      <label>Country <input name="lb_country" value="<?= e($s('lb_country', 'IN')) ?>"></label>
      <label>Phone <input name="lb_phone" value="<?= e($s('lb_phone')) ?>"></label>
      <label>Latitude <input name="lb_lat" value="<?= e($s('lb_lat')) ?>"></label>
      <label>Longitude <input name="lb_lng" value="<?= e($s('lb_lng')) ?>"></label>
    </div>

    <div class="admin-form-actions">
      <button type="submit" class="admin-btn primary">Save SEO settings</button>
    </div>
  </form>
</section>

<style>
.admin-form-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px;margin:16px 0}
.admin-form label{display:flex;flex-direction:column;gap:6px;font-size:12px;color:#9ca3af}
.admin-form input[type=text],.admin-form input[type=email],.admin-form input:not([type]),.admin-form input[type=url],.admin-form input[type=number],.admin-form textarea,.admin-form select{background:#111;border:1px solid #2a2a2a;border-radius:8px;padding:10px 12px;color:#fff;font:inherit}
.admin-subhead{margin:28px 0 8px;font-size:15px}
.admin-check{display:flex;align-items:center;gap:8px;margin:12px 0;color:#ddd}
.admin-form-actions{margin-top:20px}
.admin-btn{display:inline-flex;align-items:center;padding:10px 16px;border-radius:8px;border:1px solid #333;background:#1a1a1a;color:#fff;cursor:pointer;text-decoration:none;font-weight:600}
.admin-btn.primary{background:#ff8c1a;border-color:#ff8c1a;color:#000}
.admin-flash{padding:12px 14px;border-radius:8px;margin-bottom:16px}
.admin-flash.ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.35);color:#86efac}
.admin-flash.err{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.35);color:#fca5a5}
</style>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
