<?php
/**
 * Arowin Trading Company — SEO config (single source of truth)
 */
declare(strict_types=1);

return [
    'title'       => 'Arowin Trading Company | Official AROWIN Binary Networks Portal',
    'title_short' => 'Arowin Trading Company',
    'description' => 'Official website of Arowin Trading Company (AROWIN / Arowin) — institutional binary networks, 1:1 USDT matching, direct referral income, and secure operator node registration.',
    'keywords'    => 'Arowin Trading Company, Arowin, AROWIN, Aro Win, Arowin Trading Portal, Arowin login, Arowin register, Arowin binary, Arowin USDT, Arowin matching, institutional binary network',
    'brand_aliases' => [
        'AROWIN',
        'Arowin',
        'Aro Win',
        'Arowin Trading Company',
        'Arowin Trading Portal',
        'Arowin Portal',
        'Arowin Trading',
    ],
    'author'      => 'Arowin Trading Company',
    'robots'      => 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1',
    'og_type'     => 'website',
    'og_image'    => '/public/assets/images/og-arowin.png',
    'og_image_w'  => 1200,
    'og_image_h'  => 630,
    'og_image_alt'=> 'Arowin Trading Company (AROWIN) — Institutional Binary Networks & USDT Matching',
    'twitter_card'=> 'summary_large_image',
    'twitter_site'=> '',
    'sections' => [
        ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
        ['loc' => '/usdt-matching', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => '/binary-plan', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => '/yield-estimator', 'priority' => '0.8', 'changefreq' => 'weekly'],
        ['loc' => '/faqs', 'priority' => '0.85', 'changefreq' => 'weekly'],
        ['loc' => '/about', 'priority' => '0.8', 'changefreq' => 'monthly'],
        ['loc' => '/careers', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => '/contact', 'priority' => '0.75', 'changefreq' => 'monthly'],
        ['loc' => '/blog', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['loc' => '/news', 'priority' => '0.8', 'changefreq' => 'daily'],
        ['loc' => '/products', 'priority' => '0.9', 'changefreq' => 'weekly'],
        ['loc' => '/events', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => '/gallery', 'priority' => '0.6', 'changefreq' => 'weekly'],
        ['loc' => '/videos', 'priority' => '0.7', 'changefreq' => 'weekly'],
        ['loc' => '/downloads', 'priority' => '0.6', 'changefreq' => 'monthly'],
        ['loc' => '/search', 'priority' => '0.4', 'changefreq' => 'monthly'],
        ['loc' => '/register', 'priority' => '0.95', 'changefreq' => 'weekly'],
        ['loc' => '/login', 'priority' => '0.7', 'changefreq' => 'monthly'],
        ['loc' => '/legal/terms', 'priority' => '0.4', 'changefreq' => 'yearly'],
        ['loc' => '/legal/cookies', 'priority' => '0.4', 'changefreq' => 'yearly'],
    ],
    'faqs' => [
        [
            'q' => 'What is Arowin Trading Company?',
            'a' => 'Arowin Trading Company (also searched as AROWIN, Arowin, or Aro Win) operates an institutional-grade binary network portal that automates left/right leg matching, USDT pairing bonuses, and ledger sync for operators.',
        ],
        [
            'q' => 'How do I find the official Arowin website?',
            'a' => 'Search for Arowin Trading Company, Arowin, or AROWIN. The official site uses Arowin branding, secure login/register, and published plan pages for USDT binary matching.',
        ],
        [
            'q' => 'How does the $5.00 matching bonus work?',
            'a' => 'Whenever a 1:1 pair forms between your left and right starter legs, Arowin credits $5.00 USDT (10% of the $50 unit) to your matching ledger. Pairs are validated in real time by the binary engine.',
        ],
        [
            'q' => 'What is the daily capping limit?',
            'a' => 'Starter Protocol allows up to 50 matched pairs per day, which equals a maximum of $250.00 USDT in daily matching income. Higher ranks can expand pair rates and caps.',
        ],
        [
            'q' => 'How are left and right legs balanced?',
            'a' => 'Binary payouts use the absolute minimum starter count between left and right structures. Extra starters on the stronger leg carry forward until the weaker leg catches up.',
        ],
        [
            'q' => 'What is the direct referral (sponsor) commission?',
            'a' => 'Direct referrals earn a 5% commission on qualifying node activations. Use the Yield Estimator to model sponsor commissions alongside matching profits.',
        ],
        [
            'q' => 'Which node packages are available?',
            'a' => 'Base node costs include $50, $150, $350, $750, and higher USDT packages. Matching pays $5.00 per 1:1 pair on the $50 unit model.',
        ],
        [
            'q' => 'How do I register a core node on Arowin?',
            'a' => 'Open Register Node on the Arowin portal, enter your sponsor ID, complete activation, then manage your tree from the Operator Terminal.',
        ],
        [
            'q' => 'Are matching rewards paid in USDT?',
            'a' => 'Yes. Pairing rewards, sponsor commissions, and ledger balances are denominated in USDT and synced to your node ledger after each validated match.',
        ],
    ],
];
