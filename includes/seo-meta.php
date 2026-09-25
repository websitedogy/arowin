<?php
/**
 * Shared SEO meta / Open Graph / Twitter / JSON-LD (Organization, WebSite, SearchAction, Breadcrumb, FAQ, LocalBusiness + extras)
 *
 * Expects: $APP, $SEO, $BASE, $CANONICAL, $OG_IMAGE
 * Optional: $SEO_META = ['json_ld' => bool, 'preconnect' => bool]
 */
declare(strict_types=1);

$SEO_META = is_array($SEO_META ?? null) ? $SEO_META : [];
$seoJsonLd = array_key_exists('json_ld', $SEO_META) ? (bool) $SEO_META['json_ld'] : true;
$seoPreconnect = array_key_exists('preconnect', $SEO_META) ? (bool) $SEO_META['preconnect'] : true;

$seoTitle = (string) ($SEO['title'] ?? $APP['name']);
$seoTitleShort = (string) ($SEO['title_short'] ?? $seoTitle);
$seoDesc = (string) ($SEO['og_description_override'] ?? $SEO['description'] ?? '');
$seoKeywords = (string) ($SEO['keywords'] ?? '');
$seoRobots = (string) ($SEO['robots'] ?? 'index, follow');
$seoAuthor = (string) ($SEO['author'] ?? $APP['legal_name'] ?? $APP['name']);
$seoOgType = (string) ($SEO['og_type'] ?? 'website');
$seoLocale = str_replace('_', '-', (string) ($APP['locale'] ?? 'en_US'));
$seoLang = (string) ($APP['language'] ?? 'en');
$homeUrl = arowin_abs($BASE, '/');
$legalName = (string) ($APP['legal_name'] ?? 'Arowin Trading Company');
$brandAliases = $SEO['brand_aliases'] ?? ['AROWIN', 'Arowin', 'Aro Win', 'Arowin Trading Company', 'Arowin Trading Portal'];
if (!is_array($brandAliases)) {
    $brandAliases = ['AROWIN', 'Arowin', 'Arowin Trading Company'];
}
$twitterTitle = (string) ($SEO['twitter_title'] ?? $seoTitleShort);
$twitterDesc = (string) ($SEO['twitter_description'] ?? $seoDesc);
$twitterImage = !empty($SEO['twitter_image'])
    ? arowin_abs($BASE, (string) $SEO['twitter_image'])
    : $OG_IMAGE;
$ogDesc = (string) ($SEO['og_description_override'] ?? $seoDesc);
?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title><?= e($seoTitle) ?></title>
  <meta name="description" content="<?= e($seoDesc) ?>">
  <?php if ($seoKeywords !== ''): ?>
  <meta name="keywords" content="<?= e($seoKeywords) ?>">
  <?php endif; ?>
  <meta name="author" content="<?= e($seoAuthor) ?>">
  <meta name="creator" content="<?= e($seoAuthor) ?>">
  <meta name="publisher" content="<?= e($legalName) ?>">
  <meta name="robots" content="<?= e($seoRobots) ?>">
  <meta name="googlebot" content="<?= e($seoRobots) ?>">
  <meta name="bingbot" content="<?= e($seoRobots) ?>">
  <meta name="theme-color" content="<?= e($APP['theme_color'] ?? '#000000') ?>">
  <meta name="color-scheme" content="dark">
  <meta name="format-detection" content="telephone=no,email=no,address=no">
  <meta name="application-name" content="<?= e($APP['short_name'] ?? 'AROWIN') ?>">
  <meta name="apple-mobile-web-app-title" content="<?= e($APP['short_name'] ?? 'AROWIN') ?>">
  <meta name="rating" content="general">
  <meta name="referrer" content="strict-origin-when-cross-origin">
  <?php
  $gsc = '';
  $bing = '';
  if (function_exists('arowin_seo_get_setting')) {
      try {
          $gsc = arowin_seo_get_setting('google_site_verification');
          $bing = arowin_seo_get_setting('bing_site_verification');
      } catch (Throwable $e) {
          $gsc = '';
          $bing = '';
      }
  }
  if ($gsc !== ''): ?>
  <meta name="google-site-verification" content="<?= e($gsc) ?>">
  <?php endif; ?>
  <?php if ($bing !== ''): ?>
  <meta name="msvalidate.01" content="<?= e($bing) ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?= e($CANONICAL) ?>">
  <link rel="sitemap" type="application/xml" title="Sitemap" href="<?= e(arowin_abs($BASE, '/sitemap.xml')) ?>">
  <link rel="alternate" hreflang="<?= e($seoLang) ?>" href="<?= e($CANONICAL) ?>">
  <link rel="alternate" hreflang="x-default" href="<?= e($homeUrl) ?>">

  <!-- Open Graph -->
  <meta property="og:type" content="<?= e($seoOgType) ?>">
  <meta property="og:site_name" content="<?= e($legalName) ?>">
  <meta property="og:title" content="<?= e($seoTitleShort) ?>">
  <meta property="og:description" content="<?= e($ogDesc) ?>">
  <meta property="og:url" content="<?= e($CANONICAL) ?>">
  <meta property="og:locale" content="<?= e($APP['locale'] ?? 'en_US') ?>">
  <meta property="og:image" content="<?= e($OG_IMAGE) ?>">
  <meta property="og:image:secure_url" content="<?= e($OG_IMAGE) ?>">
  <meta property="og:image:type" content="image/png">
  <meta property="og:image:width" content="<?= (int) ($SEO['og_image_w'] ?? 1200) ?>">
  <meta property="og:image:height" content="<?= (int) ($SEO['og_image_h'] ?? 630) ?>">
  <meta property="og:image:alt" content="<?= e((string) ($SEO['og_image_alt'] ?? $legalName)) ?>">

  <!-- Twitter / X -->
  <meta name="twitter:card" content="<?= e((string) ($SEO['twitter_card'] ?? 'summary_large_image')) ?>">
  <meta name="twitter:title" content="<?= e($twitterTitle) ?>">
  <meta name="twitter:description" content="<?= e($twitterDesc) ?>">
  <meta name="twitter:image" content="<?= e($twitterImage) ?>">
  <meta name="twitter:image:alt" content="<?= e((string) ($SEO['og_image_alt'] ?? $legalName)) ?>">
  <?php if (!empty($SEO['twitter_site'])): ?>
  <meta name="twitter:site" content="<?= e((string) $SEO['twitter_site']) ?>">
  <?php endif; ?>

  <meta name="subject" content="Arowin Trading Company — institutional binary networks, USDT matching, AROWIN operator nodes">
  <meta name="classification" content="Business, Finance, Trading">
  <meta name="category" content="Trading Company">
  <meta name="coverage" content="Worldwide">
  <meta name="distribution" content="global">
  <meta name="HandheldFriendly" content="true">
  <meta name="MobileOptimized" content="width">

  <!-- Favicons -->
  <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
  <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= e(img('favicon-32x32.png')) ?>">
  <link rel="icon" type="image/png" sizes="16x16" href="<?= e(img('favicon-16x16.png')) ?>">
  <link rel="icon" type="image/png" sizes="192x192" href="<?= e(img('favicon-192x192.png')) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="<?= e(img('apple-touch-icon.png')) ?>">
  <?php require __DIR__ . '/pwa-meta.php'; ?>

  <?php if ($seoPreconnect): ?>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="//fonts.googleapis.com">
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <?php endif; ?>

<?php if ($seoJsonLd):
  $faqEntities = [];
  foreach (($SEO['faqs'] ?? []) as $faq) {
      if (empty($faq['q']) || empty($faq['a'])) {
          continue;
      }
      $faqEntities[] = [
          '@type' => 'Question',
          'name' => (string) $faq['q'],
          'acceptedAnswer' => [
              '@type' => 'Answer',
              'text' => (string) $faq['a'],
          ],
      ];
  }
  $sameAs = array_values(array_filter(array_map('strval', $APP['social'] ?? [])));
  $searchUrl = arowin_abs($BASE, '/search') . '?q={search_term_string}';

  $org = [
      '@type' => 'Organization',
      '@id' => $homeUrl . '#organization',
      'name' => $legalName,
      'alternateName' => array_values($brandAliases),
      'legalName' => $legalName,
      'url' => $homeUrl,
      'logo' => [
          '@type' => 'ImageObject',
          'url' => img('favicon-512x512.png'),
          'width' => 512,
          'height' => 512,
      ],
      'image' => $OG_IMAGE,
      'description' => (string) ($SEO['description'] ?? $seoDesc),
      'email' => $APP['contact_email'] ?? null,
      'sameAs' => $sameAs,
      'brand' => [
          '@type' => 'Brand',
          'name' => 'AROWIN',
          'alternateName' => array_values($brandAliases),
      ],
  ];

  $graph = [
      $org,
      [
          '@type' => 'WebSite',
          '@id' => $homeUrl . '#website',
          'url' => $homeUrl,
          'name' => $legalName,
          'alternateName' => array_values($brandAliases),
          'description' => (string) ($SEO['description'] ?? $seoDesc),
          'publisher' => ['@id' => $homeUrl . '#organization'],
          'inLanguage' => 'en-US',
          'potentialAction' => [
              '@type' => 'SearchAction',
              'target' => [
                  '@type' => 'EntryPoint',
                  'urlTemplate' => $searchUrl,
              ],
              'query-input' => 'required name=search_term_string',
          ],
      ],
      [
          '@type' => 'WebPage',
          '@id' => $CANONICAL . '#webpage',
          'url' => $CANONICAL,
          'name' => $seoTitle,
          'headline' => $seoTitleShort,
          'isPartOf' => ['@id' => $homeUrl . '#website'],
          'about' => ['@id' => $homeUrl . '#organization'],
          'description' => $seoDesc,
          'inLanguage' => 'en-US',
          'primaryImageOfPage' => [
              '@type' => 'ImageObject',
              'url' => $OG_IMAGE,
          ],
          'dateModified' => date('c'),
      ],
  ];

  // BreadcrumbList (use local names — never overwrite page $items / $item)
  $seoCrumbs = function_exists('arowin_get_breadcrumbs') ? arowin_get_breadcrumbs() : [];
  if ($seoCrumbs !== []) {
      $seoBreadcrumbItems = [];
      $seoCrumbPos = 1;
      foreach ($seoCrumbs as $seoCrumb) {
          $seoBreadcrumbItem = [
              '@type' => 'ListItem',
              'position' => $seoCrumbPos,
              'name' => (string) ($seoCrumb['name'] ?? ''),
          ];
          if (!empty($seoCrumb['url'])) {
              $seoBreadcrumbItem['item'] = (string) $seoCrumb['url'];
          }
          $seoBreadcrumbItems[] = $seoBreadcrumbItem;
          $seoCrumbPos++;
      }
      $graph[] = [
          '@type' => 'BreadcrumbList',
          '@id' => $CANONICAL . '#breadcrumb',
          'itemListElement' => $seoBreadcrumbItems,
      ];
  }

  // LocalBusiness (optional, admin-enabled)
  $lb = $SEO['local_business'] ?? [];
  if (!empty($lb['enabled'])) {
      $lbNode = [
          '@type' => 'LocalBusiness',
          '@id' => $homeUrl . '#localbusiness',
          'name' => $legalName,
          'url' => $homeUrl,
          'image' => $OG_IMAGE,
          'email' => $APP['contact_email'] ?? null,
          'telephone' => $lb['phone'] !== '' ? $lb['phone'] : null,
          'priceRange' => '$$',
          'parentOrganization' => ['@id' => $homeUrl . '#organization'],
      ];
      if (($lb['street'] ?? '') !== '' || ($lb['city'] ?? '') !== '') {
          $lbNode['address'] = [
              '@type' => 'PostalAddress',
              'streetAddress' => $lb['street'] ?: null,
              'addressLocality' => $lb['city'] ?: null,
              'addressRegion' => $lb['region'] ?: null,
              'postalCode' => $lb['postal'] ?: null,
              'addressCountry' => $lb['country'] ?: 'IN',
          ];
      }
      if (($lb['lat'] ?? '') !== '' && ($lb['lng'] ?? '') !== '') {
          $lbNode['geo'] = [
              '@type' => 'GeoCoordinates',
              'latitude' => $lb['lat'],
              'longitude' => $lb['lng'],
          ];
      }
      $graph[] = $lbNode;
  }

  if ($faqEntities !== []) {
      $graph[] = [
          '@type' => 'FAQPage',
          '@id' => $CANONICAL . '#faq',
          'mainEntity' => $faqEntities,
          'isPartOf' => ['@id' => $CANONICAL . '#webpage'],
      ];
  }

  if (function_exists('arowin_extra_schema')) {
      foreach (arowin_extra_schema() as $extra) {
          $graph[] = $extra;
      }
  }
?>
  <script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@graph' => $graph,
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
