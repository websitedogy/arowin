<?php
declare(strict_types=1);
/** @var array $view */
/** @var string|null $flash */
?>
<!-- CONTENT -->
      <div class="content">
        <!-- Greeting -->
        <div class="greeting-row">
          <div class="greeting">
            <h1>Hello, <?= e($view['full_name']) ?> 👋</h1>
            <p>Welcome back to your Crypto Dashboard.</p>
          </div>
          <svg class="greeting-chart" width="150" height="65" viewBox="0 0 150 65">
            <polyline points="0,58 24,48 48,40 72,28 100,18 124,10 148,3" fill="none" stroke="#ff8c1a" stroke-width="2.5" />
            <rect x="18" y="42" width="11" height="23" fill="#3a2a15" rx="2" />
            <rect x="46" y="34" width="11" height="31" fill="#4d3418" rx="2" />
            <rect x="74" y="24" width="11" height="41" fill="#66431a" rx="2" />
            <rect x="102" y="14" width="11" height="51" fill="#94591a" rx="2" />
            <rect x="128" y="5" width="11" height="60" fill="#ff8c1a" rx="2" />
          </svg>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-cards">
          <div class="stat-card is-hist" data-income-hist="earnings" role="button" tabindex="0" title="Tap to view history">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <rect x="2" y="5" width="20" height="14" rx="2" />
                  <path d="M16 12h.01" />
                </svg>
              </div>
              <span class="stat-label">Earnings</span>
            </div>
            <div class="stat-value"><?= e(arowin_fmt_usdt((float) ($view['earnings'] ?? $view['total_income'] ?? 0))) ?></div>
            <button type="button" class="dy-policy-link" data-policy="earnings" aria-haspopup="dialog">How you earn · Policy</button>
            <p class="stat-hist-hint">Tap card for history</p>
            <div class="sparkline spark-purple">
              <div class="sparkline-bar" style="height: 30%"></div>
              <div class="sparkline-bar" style="height: 50%"></div>
              <div class="sparkline-bar" style="height: 40%"></div>
              <div class="sparkline-bar" style="height: 65%"></div>
              <div class="sparkline-bar" style="height: 55%"></div>
              <div class="sparkline-bar peak" style="height: 80%"></div>
              <div class="sparkline-bar peak" style="height: 100%"></div>
            </div>
          </div>

          <div class="stat-card is-hist" data-income-hist="referral" role="button" tabindex="0" title="Tap to view history">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <line x1="12" y1="1" x2="12" y2="23" />
                  <path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6" />
                </svg>
              </div>
              <span class="stat-label">Referral Income</span>
            </div>
            <div class="stat-value"><?= e(arowin_fmt_usdt($view['referral_income'])) ?></div>
            <button type="button" class="dy-policy-link" data-policy="referral" aria-haspopup="dialog">How you earn · Policy</button>
            <p class="stat-hist-hint">Tap card for history</p>
            <div class="sparkline spark-green">
              <div class="sparkline-bar" style="height: 20%"></div>
              <div class="sparkline-bar" style="height: 40%"></div>
              <div class="sparkline-bar" style="height: 60%"></div>
              <div class="sparkline-bar" style="height: 45%"></div>
              <div class="sparkline-bar peak" style="height: 75%"></div>
              <div class="sparkline-bar peak" style="height: 90%"></div>
              <div class="sparkline-bar" style="height: 70%"></div>
            </div>
          </div>

          <div class="stat-card is-hist" data-income-hist="matching" role="button" tabindex="0" title="Tap to view history">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                  <polyline points="16 7 22 7 22 13" />
                </svg>
              </div>
              <span class="stat-label">Matching Income</span>
            </div>
            <div class="stat-value"><?= e(arowin_fmt_usdt($view['matching_income'])) ?></div>
            <button type="button" class="dy-policy-link" data-policy="matching" aria-haspopup="dialog">How you earn · Policy</button>
            <p class="stat-hist-hint">Tap card for history</p>
            <div class="sparkline spark-blue">
              <div class="sparkline-bar" style="height: 35%"></div>
              <div class="sparkline-bar" style="height: 55%"></div>
              <div class="sparkline-bar" style="height: 40%"></div>
              <div class="sparkline-bar" style="height: 70%"></div>
              <div class="sparkline-bar" style="height: 60%"></div>
              <div class="sparkline-bar peak" style="height: 85%"></div>
              <div class="sparkline-bar peak" style="height: 95%"></div>
            </div>
          </div>

          <div class="stat-card is-hist" data-income-hist="rank" role="button" tabindex="0" title="Tap to view history">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                  <polyline points="16 7 22 7 22 13" />
                </svg>
              </div>
              <span class="stat-label">Rank Bonus Income</span>
            </div>
            <div class="stat-value"><?= e(arowin_fmt_usdt($view['rank_bonus'])) ?></div>
            <button type="button" class="dy-policy-link" data-policy="rank" aria-haspopup="dialog">How you earn · Policy</button>
            <p class="stat-hist-hint">Tap card for history</p>
            <div class="sparkline spark-blue">
              <div class="sparkline-bar" style="height: 35%"></div>
              <div class="sparkline-bar" style="height: 55%"></div>
              <div class="sparkline-bar" style="height: 40%"></div>
              <div class="sparkline-bar" style="height: 70%"></div>
              <div class="sparkline-bar" style="height: 60%"></div>
              <div class="sparkline-bar peak" style="height: 85%"></div>
              <div class="sparkline-bar peak" style="height: 95%"></div>
            </div>
          </div>

          <div class="stat-card is-hist" data-income-hist="daily" role="button" tabindex="0" title="Tap to view history">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                  <polyline points="16 7 22 7 22 13" />
                </svg>
              </div>
              <span class="stat-label">Daily Yield</span>
            </div>
            <div class="stat-value"><?= e(arowin_fmt_usdt($view['daily_yield'])) ?></div>
            <button type="button" class="dy-policy-link" data-policy="daily" aria-haspopup="dialog">
              How you earn · Policy
            </button>
            <p class="stat-hist-hint">Tap card for history</p>
            <div class="sparkline spark-blue">
              <div class="sparkline-bar" style="height: 35%"></div>
              <div class="sparkline-bar" style="height: 55%"></div>
              <div class="sparkline-bar" style="height: 40%"></div>
              <div class="sparkline-bar" style="height: 70%"></div>
              <div class="sparkline-bar" style="height: 60%"></div>
              <div class="sparkline-bar peak" style="height: 85%"></div>
              <div class="sparkline-bar peak" style="height: 95%"></div>
            </div>
          </div>

          <div class="stat-card">
            <div class="stat-card-header">
              <div class="stat-icon">
                <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                  <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                  <circle cx="9" cy="7" r="4" />
                  <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
                </svg>
              </div>
              <span class="stat-label">Team Overview</span>
            </div>
            <?php
              $shareUid = arowin_normalize_sponsor_id((string) ($user['username'] ?? ''));
              $leftShareUrl = function_exists('arowin_join_share_url')
                  ? arowin_join_share_url($shareUid, 'left')
                  : (url('register') . '?sponsor_id=' . rawurlencode($shareUid) . '&placement=left&for_guest=1');
              $rightShareUrl = function_exists('arowin_join_share_url')
                  ? arowin_join_share_url($shareUid, 'right')
                  : (url('register') . '?sponsor_id=' . rawurlencode($shareUid) . '&placement=right&for_guest=1');
            ?>
            <?php
              $leftTeamCount = (int) ($view['left_team'] ?? 0);
              $rightTeamCount = (int) ($view['right_team'] ?? 0);
              $packageVolume = (float) ($view['left_business'] ?? 0) + (float) ($view['right_business'] ?? 0);
            ?>
            <div class="stat-team-row" style="margin-top: 10px">
              <a class="stat-team-item" href="<?= e(url('left-team')) ?>" style="text-decoration:none;color:inherit">
                <div class="tl">Left Team</div>
                <div class="tv"><?= e(number_format($leftTeamCount, 0)) ?></div>
              </a>
              <div class="bv-divider"></div>
              <a class="stat-team-item" href="<?= e(url('right-team')) ?>" style="text-decoration:none;color:inherit">
                <div class="tl">Right Team</div>
                <div class="tv"><?= e(number_format($rightTeamCount, 0)) ?></div>
              </a>
            </div>
            <div class="stat-team-row" style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed var(--border)">
              <div class="stat-team-item">
                <div class="tl">Total Business</div>
                <div class="tv"><?= e(number_format($packageVolume, 0)) ?></div>
              </div>
              <div class="bv-divider"></div>
              <div class="stat-team-item">
                <div class="tl">Business Turnover</div>
                <div class="tv">$<?= e(number_format($packageVolume, 0)) ?></div>
              </div>
            </div>
            <div class="stat-share-row">
              <div class="stat-share-item">
                <span class="stat-share-label">Left share link</span>
                <div class="stat-share-actions">
                  <button
                    type="button"
                    class="stat-share-btn"
                    data-share-url="<?= e($leftShareUrl) ?>"
                    data-share-copied="Copied"
                  >Copy</button>
                  <button
                    type="button"
                    class="stat-share-icon"
                    data-share-url="<?= e($leftShareUrl) ?>"
                    title="Share Left register link"
                    aria-label="Share Left register link"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                      <circle cx="18" cy="5" r="3" />
                      <circle cx="6" cy="12" r="3" />
                      <circle cx="18" cy="19" r="3" />
                      <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" />
                    </svg>
                  </button>
                </div>
              </div>
              <div class="bv-divider"></div>
              <div class="stat-share-item">
                <span class="stat-share-label">Right share link</span>
                <div class="stat-share-actions">
                  <button
                    type="button"
                    class="stat-share-btn"
                    data-share-url="<?= e($rightShareUrl) ?>"
                    data-share-copied="Copied"
                  >Copy</button>
                  <button
                    type="button"
                    class="stat-share-icon"
                    data-share-url="<?= e($rightShareUrl) ?>"
                    title="Share Right register link"
                    aria-label="Share Right register link"
                  >
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" aria-hidden="true">
                      <circle cx="18" cy="5" r="3" />
                      <circle cx="6" cy="12" r="3" />
                      <circle cx="18" cy="19" r="3" />
                      <path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4" />
                    </svg>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ROW 2 -->
        <div class="row2">
          <div class="carry-card">
            <div class="carry-icon">
              <svg width="20" height="20" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <line x1="18" y1="20" x2="18" y2="10" />
                <line x1="12" y1="20" x2="12" y2="4" />
                <line x1="6" y1="20" x2="6" y2="14" />
              </svg>
            </div>
            <div class="carry-content">
              <h3>Carry Forward Business</h3>
              <button type="button" class="dy-policy-link dy-policy-link--carry" data-policy="carry" aria-haspopup="dialog">How it works · Policy</button>
              <div class="bv-row">
                <div class="bv-item">
                  <div class="bl">Left Business</div>
                  <div class="bv">$<?= e(number_format((float) ($view['left_carry_business'] ?? 0), 0)) ?></div>
                </div>
                <div class="bv-divider"></div>
                <div class="bv-item">
                  <div class="bl">Right Business</div>
                  <div class="bv">$<?= e(number_format((float) ($view['right_carry_business'] ?? 0), 0)) ?></div>
                </div>
              </div>
            </div>
          </div>

          <div class="guest-card">
            <div class="guest-icon">
              <svg width="24" height="24" fill="none" stroke="#ff8c1a" stroke-width="2.2" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
                <circle cx="9" cy="7" r="4" />
                <path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" />
              </svg>
            </div>
            <div class="guest-content">
              <?php
                if (!function_exists('arowin_guest_list_count')) {
                    require_once dirname(__DIR__) . '/guest-list/includes/guests.php';
                }
                $guestOwner = (string) ($user['username'] ?? '');
                $guestHistory = arowin_guest_list_count($guestOwner, 'history');
                $guestDropped = arowin_guest_list_count($guestOwner, 'dropped');
                $guestJoined = count(arowin_guest_joiners($guestOwner));
                $guestTotal = $guestHistory + $guestJoined + $guestDropped;
              ?>
              <div class="guest-content-top">
                <h3>Guest List</h3>
                <span class="guest-total" title="History + Joined + Dropped"><?= (int) $guestTotal ?></span>
              </div>
              <h2>Manage Your Guests</h2>
              <p>History <?= (int) $guestHistory ?> · Joined <?= (int) $guestJoined ?> · Dropped <?= (int) $guestDropped ?></p>
              <a class="btn-guest" href="<?= e(url('guest-list')) ?>">View Guest List</a>
            </div>
          </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="section-title">Quick Actions</div>
        <div class="quick-actions">
          <a class="qa-item" href="<?= e(url('profile')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="8" r="4" />
                <path d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
              </svg>
            </div>
            <span class="qa-label">Identify Profile</span>
          </a>
          <a class="qa-item" href="<?= e(url('packages')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z" />
                <line x1="3" y1="6" x2="21" y2="6" />
                <path d="M16 10a4 4 0 01-8 0" />
              </svg>
            </div>
            <span class="qa-label">Packages</span>
          </a>
          <a class="qa-item" href="<?= e(url('direct-referrals')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <rect x="2" y="5" width="20" height="14" rx="2" />
                <path d="M2 10h20" />
              </svg>
            </div>
            <span class="qa-label">Direct Referrals</span>
          </a>
          <?php if (isset($user) && is_array($user) && function_exists('arowin_user_can_view_power_ids') && arowin_user_can_view_power_ids($user)): ?>
          <a class="qa-item" href="<?= e(url('team-collaborations')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <polyline points="22 7 13.5 15.5 8.5 10.5 2 17" />
                <polyline points="16 7 22 7 22 13" />
              </svg>
            </div>
            <span class="qa-label">Team Collaborations</span>
          </a>
          <?php endif; ?>
          <a class="qa-item" href="<?= e(url('ranks-rewards')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <path d="M6 9H4.5a2.5 2.5 0 010-5H6" />
                <path d="M18 9h1.5a2.5 2.5 0 000-5H18" />
                <path d="M4 22h16M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22" />
                <path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22" />
                <path d="M18 2H6v7a6 6 0 0012 0V2z" />
              </svg>
            </div>
            <span class="qa-label">Ranks &amp; Achievers</span>
          </a>

          <a class="qa-item" href="<?= e(url('support')) ?>" style="text-decoration:none;color:inherit">
            <div class="qa-icon">
              <svg width="19" height="19" fill="none" stroke="#ff8c1a" stroke-width="2" viewBox="0 0 24 24">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                <polyline points="9 12 11 14 15 10" />
              </svg>
            </div>
            <span class="qa-label">Support</span>
          </a>
        </div>

        <!-- BOTTOM ROW -->
        <div class="bottom-row">
          <div class="transactions-card">
            <div class="card-header">
              <h3>Recent Transactions</h3>
              <a class="view-all" href="<?= e(url('history')) ?>">View All</a>
            </div>
            <?php if (empty($recentTransactions)): ?>
              <div class="tx-empty">No transactions yet.</div>
            <?php else: ?>
            <div class="tx-table-wrap">
            <table class="tx-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Type</th>
                  <th>Amount</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentTransactions as $tx): ?>
                <tr>
                  <td><?= e((string) $tx['date']) ?></td>
                  <td class="<?= e((string) $tx['type_class']) ?>"><?= e((string) $tx['type']) ?></td>
                  <td><?= e((string) $tx['amount']) ?></td>
                  <td><span class="badge-ok"><?= e((string) $tx['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            </div>
            <?php endif; ?>
          </div>

          <div class="overview-card">
            <h3>Business Overview</h3>
            <div class="donut-wrap">
              <div class="donut-rel">
                <svg width="150" height="150" viewBox="0 0 150 150">
                  <circle cx="75" cy="75" r="55" fill="none" stroke="#262626" stroke-width="22" />
                  <!-- Matching Income 59% -->
                  <circle cx="75" cy="75" r="55" fill="none" stroke="#ff8c1a" stroke-width="22"
                    stroke-dasharray="203.7 345.6" stroke-dashoffset="0" stroke-linecap="round"
                    transform="rotate(-90 75 75)" />
                  <!-- Direct Income 39% -->
                  <circle cx="75" cy="75" r="55" fill="none" stroke="#d9700a" stroke-width="22"
                    stroke-dasharray="134.8 345.6" stroke-dashoffset="-203.7" stroke-linecap="round"
                    transform="rotate(-90 75 75)" />
                  <!-- Bonus 2% -->
                  <circle cx="75" cy="75" r="55" fill="none" stroke="#7a4413" stroke-width="22"
                    stroke-dasharray="6.9 345.6" stroke-dashoffset="-338.5" stroke-linecap="round"
                    transform="rotate(-90 75 75)" />
                </svg>
                <div class="donut-center">
                  <span>Total Income</span>
                  <strong><?= e(arowin_fmt_usdt($view['total_income'])) ?></strong>
                </div>
              </div>
              <div class="legend">
                <div class="legend-item">
                  <div class="legend-left">
                    <div class="legend-dot" style="background: #ff8c1a"></div>
                    Refel Income
                  </div>
                  <div class="legend-right">
                    <?= e(arowin_fmt_usdt($view['referral_income'])) ?><span class="legend-pct">(<?= $view['total_income'] > 0 ? (int) round(($view['referral_income'] / $view['total_income']) * 100) : 0 ?>%)</span>
                  </div>
                </div>
                <div class="legend-item">
                  <div class="legend-left">
                    <div class="legend-dot" style="background: #d9700a"></div>
                    Matching Income
                  </div>
                  <div class="legend-right">
                    <?= e(arowin_fmt_usdt($view['matching_income'])) ?><span class="legend-pct">(<?= $view['total_income'] > 0 ? (int) round(($view['matching_income'] / $view['total_income']) * 100) : 0 ?>%)</span>
                  </div>
                </div>
                <div class="legend-item">
                  <div class="legend-left">
                    <div class="legend-dot" style="background: #7a4413"></div>
                    Rank Bonus
                  </div>
                  <div class="legend-right">
                    <?= e(arowin_fmt_usdt($view['rank_bonus'])) ?><span class="legend-pct">(<?= $view['total_income'] > 0 ? (int) round(($view['rank_bonus'] / $view['total_income']) * 100) : 0 ?>%)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <!-- /content -->

<!-- Income / business policy popup (shared) -->
<div class="dy-policy-overlay hidden" id="dyPolicyOverlay" role="dialog" aria-modal="true" aria-labelledby="dyPolicyTitle">
  <div class="dy-policy-sheet" id="dyPolicySheet" onclick="event.stopPropagation()">
    <div class="dy-policy-head">
      <h3 id="dyPolicyTitle">Policy</h3>
      <button type="button" class="dy-policy-x" id="dyPolicyClose" aria-label="Close">×</button>
    </div>
    <div class="dy-policy-body" id="dyPolicyBody"></div>
    <div class="dy-policy-actions">
      <button type="button" class="dy-policy-share" id="dyPolicyShare" title="Share">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.435 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
        Share
      </button>
      <button type="button" class="dy-policy-ok" id="dyPolicyOk">Got it</button>
    </div>
  </div>
</div>

<!-- Income box history (in-page sheet — no history page redirect) -->
<div class="ih-overlay hidden" id="ihOverlay" role="dialog" aria-modal="true" aria-labelledby="ihTitle">
  <div class="ih-sheet" id="ihSheet" onclick="event.stopPropagation()">
    <div class="ih-head">
      <div>
        <h3 id="ihTitle">History</h3>
        <p class="ih-sub" id="ihSub" hidden></p>
      </div>
      <button type="button" class="ih-x" id="ihClose" aria-label="Close">×</button>
    </div>
    <div class="ih-body" id="ihBody">
      <p class="ih-loading">Loading…</p>
    </div>
  </div>
</div>
