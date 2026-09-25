<?php
/**
 * Register service worker + install prompt + offline helpers
 */
declare(strict_types=1);

$AROWIN_BASE_PATH = $AROWIN_BASE_PATH ?? (string) (parse_url($BASE, PHP_URL_PATH) ?: '');
$PWA_JS_HREF = asset_v('js/pwa.js');
$PWA_INSTALL_CSS = asset_v('css/pwa-install.css');
?>
<link rel="stylesheet" href="<?= e($PWA_INSTALL_CSS) ?>">
<script>window.AROWIN_BASE_PATH = <?= json_encode($AROWIN_BASE_PATH, JSON_UNESCAPED_SLASHES) ?>;</script>
<?php
$pwaUid = '';
$pwa2fa = false;
if (function_exists('arowin_is_logged_in') && arowin_is_logged_in()) {
    $pwaUid = arowin_normalize_sponsor_id((string) ($_SESSION['arowin_uid'] ?? ''));
    if ($pwaUid !== '' && is_file(__DIR__ . '/security-repository.php')) {
        require_once __DIR__ . '/security-repository.php';
        if (function_exists('arowin_security_status')) {
            $st = arowin_security_status($pwaUid);
            $pwa2fa = !empty($st['two_fa_enabled']);
        }
    }
}
?>
<script>
  window.AROWIN_PWA_UID = <?= json_encode($pwaUid, JSON_UNESCAPED_SLASHES) ?>;
  window.AROWIN_PWA_2FA = <?= $pwa2fa ? 'true' : 'false' ?>;
  try {
    if (window.AROWIN_PWA_UID && window.AROWIN_PWA_2FA) {
      localStorage.setItem('arowin_pwa_uid', window.AROWIN_PWA_UID);
      localStorage.setItem('arowin_pwa_2fa', '1');
    }
  } catch (e) {}
</script>
<script src="<?= e($PWA_JS_HREF) ?>" defer></script>
