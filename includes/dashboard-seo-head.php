<?php
/**
 * Shared SEO head for private dashboard pages (noindex) + performance basics.
 * Call after arowin_prepare_page([... robots => noindex ...]).
 */
declare(strict_types=1);

if (empty($SEO['robots']) || !str_contains((string) $SEO['robots'], 'noindex')) {
    $SEO['robots'] = 'noindex, nofollow';
}
$SEO['faqs'] = [];
$SEO_META = ['json_ld' => false, 'preconnect' => true];
require __DIR__ . '/seo-meta.php';

// Non-blocking dashboard font — do not wait on Google Fonts before first paint
$AROWIN_DASH_FONT = 'https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap';
?>
  <link rel="preload" as="style" href="<?= e($AROWIN_DASH_FONT) ?>" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="<?= e($AROWIN_DASH_FONT) ?>"></noscript>
<?php
