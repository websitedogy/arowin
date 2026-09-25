<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/head.php';
?>
<body>
<?php arowin_flush_response(); ?>

  <?php require __DIR__ . '/includes/navbar.php'; ?>

  <main>
    <section class="hero" aria-labelledby="hero-heading">
      <div class="hero-bg" aria-hidden="true"></div>
      <div class="hero-grid-lines" aria-hidden="true"></div>
      <div class="container hero-layout">
        <div class="hero-copy">
          <h1 id="hero-heading">
            Arowin Trading Company<br>
            <span class="accent">Institutional Binary Networks.</span>
          </h1>
          <p>
            Official Arowin (AROWIN) Trading Company portal — automated binary validation, lightning-fast matching nodes, and USDT ledger structures.
            Secure $5.00 matching bonuses on 1:1 leg pairing with high-yield daily limits of 50 pairs ($250/day).
          </p>
          <div class="hero-ctas">
            <a class="btn btn-primary btn-lg" href="<?= e(url('register')) ?>">
              Register Node
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M5 12h14M13 6l6 6-6 6"/>
              </svg>
            </a>
            <a class="btn btn-outline btn-lg" href="<?= e(url('login')) ?>">
              Sign In
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/>
                <path d="M10 17l5-5-5-5"/>
                <path d="M15 12H3"/>
              </svg>
            </a>
          </div>
        </div>

        <aside class="node-card" aria-label="Live node dashboard preview">
          <div class="node-head">
            <div class="node-id">
              <div class="chip-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5L21.5 12L12 21.5L2.5 12L12 2.5Z"/></svg>
              </div>
              <div>
                <div class="node-title">NODE AROWIN-001</div>
                <div class="node-status">Mainnet Operational</div>
              </div>
            </div>
            <span class="sync-pill">Ledger Synced</span>
          </div>

          <div class="leg-list">
            <div class="leg-row">
              <div class="leg-left">
                <span class="leg-dot orange" aria-hidden="true"></span>
                Left Node (Starters)
              </div>
              <span class="leg-count orange">14 Active</span>
            </div>
            <div class="leg-row">
              <div class="leg-left">
                <span class="leg-dot blue" aria-hidden="true"></span>
                Right Node (Starters)
              </div>
              <span class="leg-count blue">18 Active</span>
            </div>
          </div>

          <div class="reward-bar">
            <div>
              <div class="reward-label">Matched Pairing Reward</div>
              <div class="reward-pairs">12 Pairs Matched</div>
            </div>
            <div class="reward-amount">+$60.00 USDT</div>
          </div>
        </aside>
      </div>
    </section>

    <section class="section" id="infrastructure" aria-labelledby="infra-heading">
      <div class="container">
        <p class="eyebrow">Infrastructure</p>
        <h2 id="infra-heading">Built for institutional binary operations</h2>
        <p class="section-lead">
          Matching engines, ledger sync, and node validation designed for high-volume USDT MLM networks.
        </p>
        <div class="feature-grid">
          <article class="feature">
            <h3>Automated Binary Validation</h3>
            <p>Every 1:1 leg pairing is verified in real time so matching bonuses clear without manual reconciliation.</p>
          </article>
          <article class="feature">
            <h3>Lightning-Fast Matching Nodes</h3>
            <p>Low-latency node matching keeps left and right starters balanced as your downline scales.</p>
          </article>
          <article class="feature">
            <h3>Custom Ledger Structures</h3>
            <p>USDT rewards, pair counts, and daily caps sync to a transparent ledger operators can audit anytime.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="earn-section" id="earning-matrix" aria-labelledby="earn-heading">
      <div class="container earn-layout">
        <div class="earn-copy">
          <p class="eyebrow">Balanced Leg Dynamics</p>
          <h2 id="earn-heading">50-Pair Capping &amp; $250 Daily Earning</h2>
          <p>The Starter Node offers high-performance trading yield configurations:</p>

          <div class="earn-features">
            <div class="earn-feature">
              <div class="earn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.5l2.4 6.8H21l-5.4 4.2 2 6.8L12 16.5 6.4 20.3l2-6.8L3 9.3h6.6L12 2.5z"/></svg>
              </div>
              <div>
                <h3>Capping Limit (50 Pairs)</h3>
                <p>Each standard Starter rank allows up to 50 matched node pairings every single day.</p>
              </div>
            </div>

            <div class="earn-feature">
              <div class="earn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9.5 14.5l5-5M8 11.5l-1.8-1.8a3.2 3.2 0 0 1 4.5-4.5L12.5 7M16 12.5l1.8 1.8a3.2 3.2 0 0 1-4.5 4.5L11.5 17"/></svg>
              </div>
              <div>
                <h3>10% Matching Per 1:1 Pair</h3>
                <p>When Left $50 USDT business pairs with Right $50 USDT business (1:1), you earn 10% Matching Income on that pair.</p>
              </div>
            </div>

            <div class="earn-feature">
              <div class="earn-icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 20h8M12 16v4M7 16l1.2-3.5h7.6L17 16H7zM8.5 12.5l1.5-5h4l1.5 5M10 7.5V5.5a2 2 0 0 1 4 0v2"/></svg>
              </div>
              <div>
                <h3>Higher Ranks Expansion</h3>
                <p>Ascend into Elite, Bronze, Silver and Gold ranks to boost pair income up to $25.00 per pair and 100 daily capping limits.</p>
              </div>
            </div>
          </div>
        </div>

        <aside class="matrix-card" aria-label="Node earning matrix">
          <div class="matrix-head">
            <h3>Node Earning Matrix</h3>
            <span class="matrix-chart-icon" aria-hidden="true">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 18V10M10 18V6M16 18v-4M20 18V8"/>
                <path d="M3 20h18"/>
              </svg>
            </span>
          </div>

          <div class="matrix-rows">
            <div class="matrix-row">
              <span class="matrix-label">Plan Name</span>
              <span class="matrix-value">Starter Protocol</span>
            </div>
            <div class="matrix-row">
              <span class="matrix-label">Direct Referral Bonus</span>
              <span class="matrix-value green">5% Commission</span>
            </div>
            <div class="matrix-row">
              <span class="matrix-label">Pair Payout Rate</span>
              <span class="matrix-value orange">$5.00 USDT</span>
            </div>
            <div class="matrix-row">
              <span class="matrix-label">Daily Pairs Cap</span>
              <span class="matrix-value">50 Pairs</span>
            </div>
            <div class="matrix-row">
              <span class="matrix-label">Max Daily Matching Payout</span>
              <span class="matrix-value orange">$250.00 USDT</span>
            </div>
          </div>

          <div class="matrix-alert">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13 2L4 14h7l-1 8 10-14h-7l1-6z"/></svg>
            <p>Upgraded to 50 pairs limits. The maximum potential is set at $250.00 daily capping matching income!</p>
          </div>
        </aside>
      </div>
    </section>

    <section class="yield-section" id="binary-plan" aria-labelledby="yield-sim-heading">
      <div class="container">
        <div class="yield-intro">
          <p class="eyebrow">Yield Estimator</p>
          <h2 id="yield-sim-heading">Simulate Matching Yields</h2>
          <p>Model your matching and direct sponsor commissions on customized left and right downline starter node configurations.</p>
        </div>

        <div class="yield-grid">
          <div class="yield-panel">
            <p class="cost-label">Select Base Node Cost: <span id="costLabel">$750 USDT</span></p>
            <div class="cost-options" role="group" aria-label="Base node cost">
              <button type="button" class="cost-btn" data-cost="150">$150</button>
              <button type="button" class="cost-btn" data-cost="350">$350</button>
              <button type="button" class="cost-btn active" data-cost="750">$750</button>
              <button type="button" class="cost-btn" data-cost="1550">$1550</button>
            </div>

            <div class="slider-block">
              <div class="slider-top">
                <label for="leftStarters">Left Node Starters: <span id="leftValue">15</span></label>
                <span class="max">Max 100</span>
              </div>
              <input class="yield-slider" type="range" id="leftStarters" min="0" max="100" value="15" aria-valuemin="0" aria-valuemax="100" aria-valuenow="15">
            </div>

            <div class="slider-block">
              <div class="slider-top">
                <label for="rightStarters">Right Node Starters: <span id="rightValue">12</span></label>
                <span class="max">Max 100</span>
              </div>
              <input class="yield-slider" type="range" id="rightStarters" min="0" max="100" value="12" aria-valuemin="0" aria-valuemax="100" aria-valuenow="12">
            </div>
          </div>

          <div class="yield-panel">
            <div class="metrics-head">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="4" y="3" width="16" height="18" rx="2"/>
                <path d="M8 7h8M8 11h8M8 15h4"/>
              </svg>
              <h3>Simulation Metrics</h3>
            </div>

            <div class="metric-row">
              <div class="metric-box">
                <div class="label">Pairs Matched</div>
                <div class="value" id="pairsMatched">12</div>
              </div>
              <div class="metric-box">
                <div class="label">Sponsor Commissions</div>
                <div class="value green" id="sponsorComm">$5.00</div>
              </div>
            </div>

            <div class="profit-box">
              <div class="label">Estimated Pairing Profit</div>
              <div class="amount"><span id="pairingProfit">$60.00</span><small>USDT</small></div>
              <p class="note">*Starter capping of 50 pairs ($250.00 limit) is applied.</p>
            </div>

            <a class="btn btn-primary" href="<?= e(url('register')) ?>">LAUNCH LIVE PORTFOLIO</a>
          </div>
        </div>
      </div>
    </section>

    <section class="section" id="interactive-yield" aria-labelledby="yield-heading">
      <div class="container">
        <p class="eyebrow">Interactive Yield</p>
        <h2 id="yield-heading">Track every matched pair in your operator terminal</h2>
        <p class="section-lead">
          Monitor left/right starters, live pair counts, and cumulative USDT rewards from a single node dashboard.
        </p>
        <div class="feature-grid">
          <article class="feature">
            <h3>Live Node Status</h3>
            <p>See mainnet health, ledger sync state, and active starter counts on both binary legs.</p>
          </article>
          <article class="feature">
            <h3>Pairing Rewards</h3>
            <p>Matched pairing rewards update as soon as both legs clear — paid in USDT to your core node.</p>
          </article>
          <article class="feature">
            <h3>Daily Cap Controls</h3>
            <p>Soft limits of 50 pairs ($250/day) keep yield predictable while your network compounds.</p>
          </article>
        </div>
      </div>
    </section>

    <section class="faq-section" id="faq" aria-labelledby="faq-heading">
      <div class="container">
        <div class="faq-intro">
          <p class="eyebrow">Support</p>
          <h2 id="faq-heading">Frequently Asked Questions</h2>
          <p>Clear answers on binary pairing, USDT matching bonuses, daily caps, and how Arowin nodes work.</p>
        </div>

        <div class="faq-list">
          <?php foreach (($SEO['faqs'] ?? []) as $i => $faq): ?>
          <details class="faq-item"<?= $i === 0 ? ' open' : '' ?>>
            <summary><?= e($faq['q']) ?></summary>
            <div class="faq-answer"><?= e($faq['a']) ?></div>
          </details>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="cta-block" id="register" aria-labelledby="cta-heading">
      <h2 id="cta-heading">Initialize your core node</h2>
      <p>Register a node on Arowin Trading Portal and start earning USDT matching bonuses on 1:1 binary pairs.</p>
      <a class="btn btn-primary btn-lg" href="<?= e(url('register')) ?>">REGISTER NODE</a>
    </section>
  </main>

  <?php require __DIR__ . '/includes/footer.php'; ?>
  <script src="<?= e($JS_NAVBAR_HREF) ?>" defer></script>
  <script src="<?= e($JS_HREF) ?>" defer></script>
  <?php require __DIR__ . '/includes/pwa-register.php'; ?>
</body>
</html>
