<?php
/**
 * Support — current updates, closed tickets, new request
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/support.php';

$CURRENT_ROUTE = 'dashboard';
$DASHBOARD_PAGE = 'support';

arowin_prepare_page([
    'title'       => 'Support — Arowin Trading Portal',
    'title_short' => 'Support',
    'description' => 'Contact Arowin support for node, matching, and payout help.',
    'canonical'   => '/dashboard/support',
    'robots'      => 'noindex, nofollow',
    'faqs'        => false,
]);

if (!arowin_is_logged_in()) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$sessionUid = (string) ($_SESSION['arowin_uid'] ?? '');
$user = arowin_get_user_by_username($sessionUid);
if (!$user || (int) ($user['id'] ?? 0) < 1) {
    arowin_logout_user();
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Location: ' . url('login'), true, 303);
    exit;
}

$view = arowin_dashboard_view_data($user);
$csrf = arowin_csrf_token();
$form = arowin_support_form_defaults($user);
$flashOk = null;
$flashErr = null;
$topics = arowin_support_topic_labels();
$memberId = (string) ($user['username'] ?? '');
$pageBase = arowin_abs($BASE, '/dashboard/support/');

if (!empty($_SESSION['arowin_support_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_support_flash_ok'];
    unset($_SESSION['arowin_support_flash_ok']);
}

$openThread = arowin_support_get_open_thread($memberId);
$closedTickets = arowin_support_list_member_threads($memberId, 'closed', 40);

$viewTicketId = (int) ($_GET['ticket'] ?? 0);
$tab = strtolower(trim((string) ($_GET['tab'] ?? '')));
if (!in_array($tab, ['current', 'closed', 'new'], true)) {
    if ($viewTicketId > 0) {
        $tab = 'closed';
    } elseif ($openThread) {
        $tab = 'current';
    } else {
        $tab = 'new';
    }
}

// Member opened Current tab → clear unread badge
if ($tab === 'current' && $openThread) {
    arowin_support_mark_member_seen($memberId, (int) ($openThread['id'] ?? 0));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $forceNew = (string) ($_POST['intent'] ?? '') === 'new';
        $form = [
            'member_id' => $memberId,
            'topic'     => trim((string) ($_POST['topic'] ?? '')),
            'subject'   => trim((string) ($_POST['subject'] ?? '')),
            'message'   => trim((string) ($_POST['message'] ?? '')),
            'force_new' => $forceNew ? '1' : '',
        ];
        $result = arowin_support_submit($user, $form);
        if (!empty($result['ok'])) {
            $_SESSION['arowin_support_flash_ok'] = (string) ($result['message'] ?? 'Request submitted.');
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            header('Location: ' . $pageBase . '?tab=current#supUpdates', true, 303);
            exit;
        }
        $flashErr = (string) ($result['message'] ?? 'Could not submit request.');
        $tab = $forceNew || !$openThread ? 'new' : 'current';
    }
}

// Refresh open after possible submit failure path
$openThread = arowin_support_get_open_thread($memberId);
$closedTickets = arowin_support_list_member_threads($memberId, 'closed', 40);

$viewThread = null;
if ($tab === 'closed' && $viewTicketId > 0) {
    $candidate = arowin_support_get_thread($viewTicketId);
    if ($candidate
        && arowin_normalize_sponsor_id((string) ($candidate['username'] ?? '')) === arowin_normalize_sponsor_id($memberId)
        && (string) ($candidate['status'] ?? '') === 'closed'
    ) {
        $viewThread = $candidate;
    }
}

$activeThread = $tab === 'closed' ? $viewThread : $openThread;
$messages = $activeThread
    ? arowin_support_list_thread_messages((int) $activeThread['id'])
    : [];
$messagesNewestFirst = array_reverse($messages);

$hasOpen = (bool) $openThread;
$isFollowUp = $tab === 'current' && $hasOpen;
$showNewForm = $tab === 'new' || (!$hasOpen && $tab !== 'closed');

if ($isFollowUp) {
    $form['topic'] = (string) ($openThread['topic'] ?? $form['topic']);
    $form['subject'] = (string) ($openThread['subject'] ?? $form['subject']);
}

$baseCss = arowin_abs($BASE, '/dashboard/assets/css/dashboard.css');
$featCss = arowin_abs($BASE, '/dashboard/support/assets/support.css');
$jsHref  = arowin_abs($BASE, '/dashboard/assets/js/dashboard.js');
$baseCssFile = dirname(__DIR__, 2) . '/dashboard/assets/css/dashboard.css';
$featCssFile = __DIR__ . '/assets/support.css';
$jsFile = dirname(__DIR__, 2) . '/dashboard/assets/js/dashboard.js';
if (is_file($baseCssFile)) {
    $baseCss .= '?v=' . filemtime($baseCssFile);
}
if (is_file($featCssFile)) {
    $featCss .= '?v=' . filemtime($featCssFile);
}
if (is_file($jsFile)) {
    $jsHref .= '?v=' . filemtime($jsFile);
}

$statusLabel = 'No open request';
$statusClass = 'is-idle';
if ($hasOpen) {
    $waitingOn = (string) ($openThread['waiting_on'] ?? '');
    if ($waitingOn === 'member') {
        $statusLabel = 'Reply from support';
        $statusClass = 'is-reply';
    } else {
        $statusLabel = 'Open request';
        $statusClass = 'is-open';
    }
} elseif ($closedTickets !== []) {
    $statusLabel = 'All closed';
    $statusClass = 'is-closed';
}

$tabUrl = static function (string $t, int $ticketId = 0) use ($pageBase): string {
    $url = $pageBase . '?tab=' . rawurlencode($t);
    if ($ticketId > 0) {
        $url .= '&ticket=' . $ticketId;
    }
    return $url;
};
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($SEO['title']) ?></title>
    <meta name="robots" content="<?= e($SEO['robots']) ?>">
    <link rel="canonical" href="<?= e($CANONICAL) ?>">
    <link rel="icon" href="<?= e(arowin_abs($BASE, '/favicon.ico')) ?>" sizes="any">
    <link rel="icon" type="image/svg+xml" href="<?= e(img('favicon.svg')) ?>">
    <?php require dirname(__DIR__, 2) . '/includes/pwa-meta.php'; ?>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="<?= e($baseCss) ?>" />
    <link rel="stylesheet" href="<?= e($featCss) ?>" />
  </head>
  <body>
    <?php require dirname(__DIR__) . '/sidebar.php'; ?>

    <div class="main" id="main">
      <?php require dirname(__DIR__) . '/navbar.php'; ?>

      <div class="content">
        <header class="sup-head">
          <div>
            <p class="sup-eyebrow">Help center</p>
            <h1 class="sup-title">Support</h1>
            <p class="sup-sub">Open request updates stay here. Closed tickets are listed separately. Start a new request anytime after close.</p>
          </div>
          <div class="sup-head-meta">
            <span class="sup-id">ID <strong><?= e($memberId) ?></strong></span>
            <span class="sup-status <?= e($statusClass) ?>"><?= e($statusLabel) ?></span>
          </div>
        </header>

        <?php if ($flashOk): ?>
          <div class="sup-flash ok" role="status"><?= e($flashOk) ?></div>
        <?php endif; ?>
        <?php if ($flashErr): ?>
          <div class="sup-flash err" role="alert"><?= e($flashErr) ?></div>
        <?php endif; ?>

        <nav class="sup-tabs" aria-label="Support sections">
          <a class="sup-tab<?= $tab === 'current' ? ' is-active' : '' ?>" href="<?= e($tabUrl('current')) ?>">
            Current<?= $hasOpen ? ' · open' : '' ?>
          </a>
          <a class="sup-tab<?= $tab === 'closed' ? ' is-active' : '' ?>" href="<?= e($tabUrl('closed')) ?>">
            Closed tickets (<?= count($closedTickets) ?>)
          </a>
          <a class="sup-tab<?= $tab === 'new' ? ' is-active' : '' ?>" href="<?= e($tabUrl('new')) ?>">
            New request
          </a>
        </nav>

        <?php if ($tab === 'closed'): ?>
          <div class="sup-layout is-stack">
            <section class="sup-panel">
              <div class="sup-panel-head">
                <h2>Closed tickets</h2>
                <p>Past requests. Open one to read the full history (read-only).</p>
              </div>

              <?php if ($closedTickets === []): ?>
                <div class="sup-empty">
                  <strong>No closed tickets</strong>
                  <span>When support closes a request, it moves here.</span>
                </div>
              <?php else: ?>
                <ul class="sup-ticket-list">
                  <?php foreach ($closedTickets as $row): ?>
                    <?php
                      $tid = (int) ($row['id'] ?? 0);
                      $isSel = $viewThread && (int) $viewThread['id'] === $tid;
                      $href = $tabUrl('closed', $tid) . '#supUpdates';
                    ?>
                    <li>
                      <a class="sup-ticket<?= $isSel ? ' is-active' : '' ?>" href="<?= e($href) ?>">
                        <div class="sup-ticket-top">
                          <strong>#<?= $tid ?></strong>
                          <time><?= e(arowin_support_fmt_time_short((string) ($row['last_message_at'] ?? $row['created_at'] ?? ''))) ?></time>
                        </div>
                        <div class="sup-ticket-sub">
                          <?= e(arowin_support_topic_label((string) ($row['topic'] ?? ''))) ?>
                          · <?= e((string) ($row['subject'] ?? 'Support request')) ?>
                        </div>
                        <?php if (trim((string) ($row['last_preview'] ?? '')) !== ''): ?>
                          <div class="sup-ticket-preview"><?= e((string) $row['last_preview']) ?></div>
                        <?php endif; ?>
                      </a>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>

              <?php if (!$hasOpen): ?>
                <div class="sup-closed-cta">
                  <a class="sup-submit" href="<?= e($tabUrl('new')) ?>">Start new request</a>
                </div>
              <?php endif; ?>
            </section>

            <section class="sup-panel" id="supUpdates">
              <div class="sup-panel-head">
                <h2>Ticket history</h2>
                <p>
                  <?php if ($viewThread): ?>
                    #<?= (int) $viewThread['id'] ?> · <?= e((string) ($viewThread['subject'] ?? '')) ?>
                  <?php else: ?>
                    Select a closed ticket to view updates.
                  <?php endif; ?>
                </p>
              </div>

              <?php if (!$viewThread): ?>
                <div class="sup-empty">
                  <strong>Select a ticket</strong>
                  <span>Choose a closed ticket on the left to see messages and replies.</span>
                </div>
              <?php elseif ($messagesNewestFirst === []): ?>
                <div class="sup-empty">
                  <strong>No messages</strong>
                  <span>This ticket has no stored updates.</span>
                </div>
              <?php else: ?>
                <ol class="sup-timeline">
                  <?php foreach ($messagesNewestFirst as $msg): ?>
                    <?php
                      $sender = (string) ($msg['sender'] ?? 'member');
                      $isYou = $sender === 'member';
                      $created = (string) ($msg['created_at'] ?? '');
                    ?>
                    <li class="sup-item <?= $isYou ? 'is-you' : 'is-support' ?>">
                      <div class="sup-item-rail" aria-hidden="true"></div>
                      <article class="sup-item-body">
                        <header class="sup-item-head">
                          <span class="sup-item-who"><?= $isYou ? 'You' : 'Support team' ?></span>
                          <time datetime="<?= e($created) ?>"><?= e(arowin_support_fmt_time($created)) ?></time>
                        </header>
                        <div class="sup-item-text"><?= nl2br(e((string) ($msg['body'] ?? ''))) ?></div>
                      </article>
                    </li>
                  <?php endforeach; ?>
                </ol>
              <?php endif; ?>
            </section>
          </div>

        <?php elseif ($tab === 'new' || $showNewForm): ?>

          <?php if ($hasOpen && $tab === 'new'): ?>
            <div class="sup-flash err" role="status">
              You already have an open request.
              <a href="<?= e($tabUrl('current')) ?>">Go to Current</a> to send an update.
              New request is available after support closes the open one.
            </div>
          <?php else: ?>
            <div class="sup-layout">
              <section class="sup-panel" aria-labelledby="supFormTitle">
                <div class="sup-panel-head">
                  <h2 id="supFormTitle">New support request</h2>
                  <p>Choose a type, subject, and describe your issue. This creates a fresh ticket.</p>
                </div>

                <form class="sup-form" method="post" action="<?= e($pageBase) ?>" autocomplete="off">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="member_id" value="<?= e($form['member_id']) ?>">
                  <input type="hidden" name="intent" value="new">

                  <div class="sup-form-grid">
                    <label class="sup-field">
                      <span>Type <em>*</em></span>
                      <select name="topic" required>
                        <option value="" disabled<?= $form['topic'] === '' ? ' selected' : '' ?>>Select type</option>
                        <?php foreach ($topics as $value => $label): ?>
                          <option value="<?= e($value) ?>"<?= $form['topic'] === $value ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="sup-field">
                      <span>Subject <em>*</em></span>
                      <input type="text" name="subject" required maxlength="190" value="<?= e($form['subject']) ?>" placeholder="Short subject">
                    </label>
                  </div>

                  <label class="sup-field">
                    <span>Message <em>*</em></span>
                    <textarea name="message" required maxlength="4000" rows="5" placeholder="Describe your issue with any useful details…"><?= e($form['message']) ?></textarea>
                  </label>

                  <div class="sup-form-actions">
                    <button type="submit" class="sup-submit">Submit request</button>
                  </div>
                </form>
              </section>

              <section class="sup-panel">
                <div class="sup-panel-head">
                  <h2>How it works</h2>
                  <p>Simple flow for every ticket</p>
                </div>
                <ol class="sup-howto">
                  <li><strong>Submit</strong> a new request with type + subject.</li>
                  <li><strong>Current</strong> shows updates and support replies.</li>
                  <li>When closed, the ticket moves to <strong>Closed tickets</strong>.</li>
                  <li>Need help again? Open <strong>New request</strong>.</li>
                </ol>
                <?php if ($closedTickets !== []): ?>
                  <p class="sup-howto-link"><a href="<?= e($tabUrl('closed')) ?>">View <?= count($closedTickets) ?> closed ticket<?= count($closedTickets) === 1 ? '' : 's' ?> →</a></p>
                <?php endif; ?>
              </section>
            </div>
          <?php endif; ?>

        <?php else: ?>
          <?php /* Current tab */ ?>
          <?php if (!$hasOpen): ?>
            <div class="sup-panel">
              <div class="sup-empty">
                <strong>No open request</strong>
                <span>You don’t have an active ticket. Start a new support request.</span>
                <a class="sup-submit" href="<?= e($tabUrl('new')) ?>">New request</a>
              </div>
            </div>
          <?php else: ?>
            <div class="sup-layout">
              <section class="sup-panel" aria-labelledby="supFormTitle">
                <div class="sup-panel-head">
                  <h2 id="supFormTitle">Add update</h2>
                  <p>Ticket #<?= (int) $openThread['id'] ?> is open. Send a follow-up if needed.</p>
                </div>

                <div class="sup-active">
                  <?php if (!empty($openThread['topic'])): ?>
                    <span class="sup-tag"><?= e(arowin_support_topic_label((string) $openThread['topic'])) ?></span>
                  <?php endif; ?>
                  <strong><?= e((string) ($openThread['subject'] ?? 'Support request')) ?></strong>
                </div>

                <form class="sup-form" method="post" action="<?= e($pageBase) ?>" autocomplete="off">
                  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                  <input type="hidden" name="member_id" value="<?= e($form['member_id']) ?>">
                  <input type="hidden" name="intent" value="update">
                  <input type="hidden" name="topic" value="<?= e($form['topic']) ?>">
                  <input type="hidden" name="subject" value="<?= e($form['subject']) ?>">

                  <label class="sup-field">
                    <span>Follow-up <em>*</em></span>
                    <textarea name="message" required maxlength="4000" rows="5" placeholder="Add more details for the support team…"><?= e($form['message']) ?></textarea>
                  </label>

                  <div class="sup-form-actions">
                    <button type="submit" class="sup-submit">Send update</button>
                  </div>
                </form>
              </section>

              <section class="sup-panel" id="supUpdates" aria-labelledby="supHistoryTitle">
                <div class="sup-panel-head">
                  <h2 id="supHistoryTitle">Updates</h2>
                  <p><?= count($messages) ?> entr<?= count($messages) === 1 ? 'y' : 'ies' ?> on this open ticket</p>
                </div>

                <?php if ($messagesNewestFirst === []): ?>
                  <div class="sup-empty">
                    <strong>No updates yet</strong>
                    <span>Your messages and support replies for this ticket appear here.</span>
                  </div>
                <?php else: ?>
                  <ol class="sup-timeline">
                    <?php foreach ($messagesNewestFirst as $msg): ?>
                      <?php
                        $sender = (string) ($msg['sender'] ?? 'member');
                        $isYou = $sender === 'member';
                        $created = (string) ($msg['created_at'] ?? '');
                      ?>
                      <li class="sup-item <?= $isYou ? 'is-you' : 'is-support' ?>">
                        <div class="sup-item-rail" aria-hidden="true"></div>
                        <article class="sup-item-body">
                          <header class="sup-item-head">
                            <span class="sup-item-who"><?= $isYou ? 'You' : 'Support team' ?></span>
                            <time datetime="<?= e($created) ?>"><?= e(arowin_support_fmt_time($created)) ?></time>
                          </header>
                          <div class="sup-item-text"><?= nl2br(e((string) ($msg['body'] ?? ''))) ?></div>
                        </article>
                      </li>
                    <?php endforeach; ?>
                  </ol>
                <?php endif; ?>
              </section>
            </div>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>

    <?php require dirname(__DIR__) . '/includes/bottom-nav.php'; ?>
    <script src="<?= e($jsHref) ?>"></script>
    <?php require dirname(__DIR__, 2) . '/includes/pwa-register.php'; ?>
  </body>
</html>
