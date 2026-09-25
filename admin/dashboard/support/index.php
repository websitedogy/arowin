<?php
/**
 * Admin Support desk — member conversation inbox
 */
declare(strict_types=1);

require dirname(__DIR__, 2) . '/includes/init.php';
require dirname(__DIR__, 2) . '/includes/auth-check.php';
require_once dirname(__DIR__, 3) . '/dashboard/support/includes/support.php';

$ADMIN_PAGE = 'support';
$ADMIN_TITLE = 'Support';
$ADMIN_SUBTITLE = 'Signed in as ' . arowin_admin_username();

$flashOk = null;
$flashErr = null;

if (!empty($_SESSION['arowin_admin_support_flash_ok'])) {
    $flashOk = (string) $_SESSION['arowin_admin_support_flash_ok'];
    unset($_SESSION['arowin_admin_support_flash_ok']);
}
if (!empty($_SESSION['arowin_admin_support_flash_err'])) {
    $flashErr = (string) $_SESSION['arowin_admin_support_flash_err'];
    unset($_SESSION['arowin_admin_support_flash_err']);
}

$baseUrl = admin_url('/dashboard/support');
$q = trim((string) ($_GET['q'] ?? ''));
$activeMember = arowin_normalize_sponsor_id((string) ($_GET['member'] ?? ''));
$activeThreadId = (int) ($_GET['thread'] ?? 0);
$counts = arowin_support_admin_counts();

if (isset($_GET['filter'])) {
    $filter = (string) $_GET['filter'];
} elseif ($counts['waiting'] > 0) {
    $filter = 'waiting';
} elseif ($counts['open'] > 0) {
    $filter = 'open';
} else {
    $filter = 'all';
}
if (!in_array($filter, ['waiting', 'open', 'closed', 'all', 'public'], true)) {
    $filter = 'waiting';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!arowin_csrf_validate((string) ($_POST['csrf'] ?? ''))) {
        $flashErr = 'Session expired. Refresh and try again.';
    } else {
        $action = (string) ($_POST['action'] ?? 'reply');
        $redirectMember = arowin_normalize_sponsor_id((string) ($_POST['member'] ?? ''));
        $redirectFilter = (string) ($_POST['filter'] ?? $filter);
        $redirectQ = trim((string) ($_POST['q'] ?? $q));

        if ($action === 'public_reply') {
            $id = (int) ($_POST['ticket_id'] ?? 0);
            $reply = (string) ($_POST['admin_reply'] ?? '');
            $status = (string) ($_POST['status'] ?? 'in_progress');
            $result = arowin_support_admin_reply($id, $reply, $status);
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_support_flash_ok'] = (string) ($result['message'] ?? 'Reply saved.');
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $loc = $baseUrl . '?filter=public';
                header('Location: ' . $loc, true, 303);
                exit;
            }
            $flashErr = (string) ($result['message'] ?? 'Reply failed.');
            $filter = 'public';
        } elseif ($action === 'status') {
            $threadId = (int) ($_POST['thread_id'] ?? 0);
            $status = (string) ($_POST['status'] ?? 'closed');
            $result = arowin_support_admin_set_status($threadId, $status);
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_support_flash_ok'] = (string) ($result['message'] ?? 'Updated.');
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $loc = $baseUrl . '?filter=' . rawurlencode($redirectFilter);
                if ($redirectQ !== '') {
                    $loc .= '&q=' . rawurlencode($redirectQ);
                }
                if ($threadId > 0) {
                    $loc .= '&thread=' . $threadId;
                }
                if ($redirectMember !== '') {
                    $loc .= '&member=' . rawurlencode($redirectMember);
                }
                header('Location: ' . $loc, true, 303);
                exit;
            }
            $flashErr = (string) ($result['message'] ?? 'Update failed.');
        } else {
            $threadId = (int) ($_POST['thread_id'] ?? 0);
            $reply = (string) ($_POST['admin_reply'] ?? '');
            $status = (string) ($_POST['status'] ?? 'open');
            $result = arowin_support_admin_reply_thread($threadId, $reply, $status);
            if (!empty($result['ok'])) {
                $_SESSION['arowin_admin_support_flash_ok'] = (string) ($result['message'] ?? 'Reply sent.');
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                $loc = $baseUrl . '?filter=' . rawurlencode($redirectFilter);
                if ($redirectQ !== '') {
                    $loc .= '&q=' . rawurlencode($redirectQ);
                }
                $loc .= '&thread=' . $threadId;
                if ($redirectMember !== '') {
                    $loc .= '&member=' . rawurlencode($redirectMember);
                }
                header('Location: ' . $loc . '#asChatEnd', true, 303);
                exit;
            }
            $flashErr = (string) ($result['message'] ?? 'Reply failed.');
        }
    }
}

$csrf = arowin_csrf_token();
$inboxAll = $filter === 'public' ? [] : arowin_support_admin_inbox($filter === 'all' ? 'all' : $filter, $q, 200);
$publicAll = $filter === 'public' ? arowin_support_list_public_inquiries(100) : [];

$activeThread = null;
if ($activeThreadId > 0) {
    $activeThread = arowin_support_get_thread($activeThreadId);
}
if (!$activeThread && $activeMember !== '') {
    $activeThread = arowin_support_get_thread_by_username($activeMember);
}
if (!$activeThread && $inboxAll !== [] && $filter !== 'public') {
    $activeThread = $inboxAll[0];
}

$chatMessages = [];
if ($activeThread) {
    $activeMember = arowin_normalize_sponsor_id((string) ($activeThread['username'] ?? ''));
    $activeThreadId = (int) ($activeThread['id'] ?? 0);
    $chatMessages = arowin_support_list_thread_messages($activeThreadId);
    arowin_support_mark_admin_seen($activeThreadId);
}

$inboxPager = arowin_admin_paginate($inboxAll);
$inbox = $inboxPager['rows'];
$publicPager = arowin_admin_paginate($publicAll);
$publicTickets = $publicPager['rows'];
$snapLatestId = (int) (($inboxAll[0]['id'] ?? 0));

require dirname(__DIR__, 2) . '/includes/layout-start.php';
?>

<link rel="stylesheet" href="<?= e(admin_asset('css/support.css')) ?>" />

<section class="admin-page-intro">
  <h2><?= e($ADMIN_TITLE) ?></h2>
</section>

<p class="admin-back-link">
  <a class="admin-btn link" href="<?= e(admin_url('/dashboard')) ?>">← Back to dashboard</a>
</p>

<?php if ($flashOk): ?>
  <div class="admin-flash ok" role="status"><?= e($flashOk) ?></div>
<?php endif; ?>
<?php if ($flashErr): ?>
  <div class="admin-flash err" role="alert"><?= e($flashErr) ?></div>
<?php endif; ?>

<div class="as-toolbar">
  <nav class="as-filters" aria-label="Conversation filters">
    <?php
      $tabs = [
          'waiting' => 'Needs reply (' . $counts['waiting'] . ')',
          'open'    => 'Open (' . $counts['open'] . ')',
          'closed'  => 'Closed (' . $counts['closed'] . ')',
          'all'     => 'All tickets (' . $counts['all'] . ')',
          'public'  => 'Website contact',
      ];
      foreach ($tabs as $key => $label):
          $href = $baseUrl . '?filter=' . rawurlencode($key);
          if ($q !== '' && $key !== 'public') {
              $href .= '&q=' . rawurlencode($q);
          }
    ?>
      <a class="as-filter<?= $filter === $key ? ' is-active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if ($filter !== 'public'): ?>
    <form class="as-search" method="get" action="<?= e($baseUrl) ?>">
      <input type="hidden" name="filter" value="<?= e($filter) ?>">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search member ID, name, subject…" maxlength="80">
      <button type="submit" class="admin-btn">Search</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($filter === 'public'): ?>
  <section class="admin-panel as-public">
    <?php if ($publicPager['total'] < 1): ?>
      <div class="admin-empty">No public website contact inquiries.</div>
    <?php else: ?>
      <div class="as-public-list">
        <?php foreach ($publicTickets as $row): ?>
          <?php
            $id = (int) ($row['id'] ?? 0);
            $name = trim((string) ($row['first_name'] ?? '') . ' ' . (string) ($row['last_name'] ?? ''));
            $reply = trim((string) ($row['admin_reply'] ?? ''));
            $status = (string) ($row['status'] ?? 'new');
          ?>
          <article class="as-public-card">
            <header>
              <strong>#<?= $id ?></strong>
              <span><?= e(arowin_support_topic_label((string) ($row['topic'] ?? ''))) ?></span>
              <span class="as-badge"><?= e(ucfirst(str_replace('_', ' ', $status))) ?></span>
            </header>
            <h3><?= e((string) ($row['subject'] ?? '')) ?></h3>
            <p class="as-meta"><?= e($name !== '' ? $name : '—') ?> · <?= e((string) ($row['email'] ?? '')) ?> · <?= e(arowin_support_fmt_time((string) ($row['created_at'] ?? ''))) ?></p>
            <div class="as-bubble"><?= nl2br(e((string) ($row['message'] ?? ''))) ?></div>
            <?php if ($reply !== ''): ?>
              <div class="as-bubble is-admin">
                <span class="label">Last reply</span>
                <?= nl2br(e($reply)) ?>
              </div>
            <?php endif; ?>
            <form method="post" class="as-public-form">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="public_reply">
              <input type="hidden" name="ticket_id" value="<?= $id ?>">
              <label>
                Reply note
                <textarea name="admin_reply" required maxlength="4000" rows="3" placeholder="Internal / email reply note…"><?= e($reply) ?></textarea>
              </label>
              <div class="as-public-actions">
                <select name="status">
                  <option value="in_progress"<?= $status === 'in_progress' || $status === 'new' ? ' selected' : '' ?>>In progress</option>
                  <option value="closed"<?= $status === 'closed' ? ' selected' : '' ?>>Closed</option>
                </select>
                <button type="submit" class="admin-btn primary">Save</button>
              </div>
            </form>
          </article>
        <?php endforeach; ?>
      </div>
      <?= arowin_admin_pagination_html($publicPager) ?>
    <?php endif; ?>
  </section>
<?php else: ?>

  <section class="as-desk" aria-label="Support ticket desk">
    <aside class="as-inbox">
      <div class="as-inbox-head">
        <strong>Tickets</strong>
        <span id="asInboxCount"><?= (int) $inboxPager['total'] ?></span>
      </div>
      <?php if ($inboxPager['total'] < 1): ?>
        <div class="as-inbox-empty">No tickets in this filter. When members message from their dashboard, they show up here.</div>
      <?php else: ?>
        <ul class="as-inbox-list">
          <?php foreach ($inbox as $row): ?>
            <?php
              $tid = (int) ($row['id'] ?? 0);
              $uid = (string) ($row['username'] ?? '');
              $isActive = $tid === $activeThreadId;
              $waiting = (string) ($row['waiting_on'] ?? '') === 'admin' && (string) ($row['status'] ?? '') === 'open';
              $unreadAdmin = arowin_support_thread_unread_for_admin($row);
              $href = $baseUrl . '?filter=' . rawurlencode($filter)
                  . '&thread=' . $tid
                  . '&member=' . rawurlencode($uid);
              if ($q !== '') {
                  $href .= '&q=' . rawurlencode($q);
              }
              $name = trim((string) ($row['full_name'] ?? ''));
              $preview = trim((string) ($row['last_preview'] ?? ''));
              $subject = trim((string) ($row['subject'] ?? ''));
            ?>
            <li>
              <a class="as-inbox-item<?= $isActive ? ' is-active' : '' ?><?= $waiting ? ' is-waiting' : '' ?><?= $unreadAdmin ? ' is-unread' : '' ?>" href="<?= e($href) ?>">
                <div class="as-inbox-top">
                  <strong>#<?= $tid ?> · <?= e($uid) ?></strong>
                  <time><?= e(arowin_support_fmt_time_short((string) ($row['last_message_at'] ?? $row['created_at'] ?? ''))) ?></time>
                </div>
                <?php if ($name !== '' || $subject !== ''): ?>
                  <div class="as-inbox-name">
                    <?= e($name !== '' ? $name : 'Member') ?>
                    <?php if ($subject !== ''): ?>
                      <span class="as-inbox-subject"> — <?= e($subject) ?></span>
                    <?php endif; ?>
                  </div>
                <?php endif; ?>
                <div class="as-inbox-preview">
                  <?php if ($waiting): ?><span class="as-dot" aria-hidden="true"></span><?php endif; ?>
                  <?= e($preview !== '' ? $preview : ($subject !== '' ? $subject : 'No messages yet')) ?>
                </div>
                <div class="as-inbox-tags">
                  <span class="as-badge <?= (string) ($row['status'] ?? '') === 'closed' ? 'is-muted' : ($waiting ? 'is-warn' : 'is-ok') ?>">
                    <?= e(arowin_support_status_label((string) ($row['status'] ?? 'open'), (string) ($row['waiting_on'] ?? ''))) ?>
                  </span>
                </div>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
        <div class="as-inbox-pager">
          <?= arowin_admin_pagination_html($inboxPager) ?>
        </div>
      <?php endif; ?>
    </aside>

    <div class="as-chat">
      <?php if (!$activeThread): ?>
        <div class="as-chat-empty">
          <strong>Select a ticket</strong>
          <span>Pick any member’s ticket from the left list. Each person has their own chat thread — replies go only to that member.</span>
        </div>
      <?php else: ?>
        <?php
          $tStatus = (string) ($activeThread['status'] ?? 'open');
          $tWaiting = (string) ($activeThread['waiting_on'] ?? '');
          $tName = trim((string) ($activeThread['full_name'] ?? ''));
          $tEmail = trim((string) ($activeThread['email'] ?? ''));
          $tTopic = (string) ($activeThread['topic'] ?? '');
          $tSubject = (string) ($activeThread['subject'] ?? '');
        ?>
        <header class="as-chat-head">
          <div>
            <h3>
              <span class="as-ticket-id">Ticket #<?= (int) $activeThreadId ?></span>
              <code><?= e($activeMember) ?></code>
              <?php if ($tName !== ''): ?>
                <span class="as-chat-name"><?= e($tName) ?></span>
              <?php endif; ?>
            </h3>
            <p>
              <?php if ($tEmail !== ''): ?><?= e($tEmail) ?> · <?php endif; ?>
              <?php if ($tTopic !== ''): ?><?= e(arowin_support_topic_label($tTopic)) ?><?php endif; ?>
              <?php if ($tSubject !== ''): ?> · <?= e($tSubject) ?><?php endif; ?>
            </p>
          </div>
          <div class="as-chat-actions">
            <span class="as-badge <?= $tStatus === 'closed' ? 'is-muted' : ($tWaiting === 'admin' ? 'is-warn' : 'is-ok') ?>">
              <?= e(arowin_support_status_label($tStatus, $tWaiting)) ?>
            </span>
            <form method="post" class="as-status-form">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="thread_id" value="<?= (int) $activeThreadId ?>">
              <input type="hidden" name="member" value="<?= e($activeMember) ?>">
              <input type="hidden" name="filter" value="<?= e($filter) ?>">
              <input type="hidden" name="q" value="<?= e($q) ?>">
              <?php if ($tStatus === 'closed'): ?>
                <input type="hidden" name="status" value="open">
                <button type="submit" class="admin-btn">Reopen</button>
              <?php else: ?>
                <input type="hidden" name="status" value="closed">
                <button type="submit" class="admin-btn">Close ticket</button>
              <?php endif; ?>
            </form>
          </div>
        </header>

        <div class="as-chat-body" id="asChatBody">
          <?php if ($chatMessages === []): ?>
            <div class="as-chat-empty sm">No messages yet.</div>
          <?php else: ?>
            <?php
              $lastDay = '';
              foreach ($chatMessages as $msg):
                $sender = (string) ($msg['sender'] ?? 'member');
                $isAdmin = $sender === 'admin';
                $adminName = trim((string) ($msg['admin_username'] ?? ''));
                $created = (string) ($msg['created_at'] ?? '');
                $dayKey = $created !== '' ? date('Y-m-d', strtotime($created) ?: time()) : '';
                $showDay = $dayKey !== '' && $dayKey !== $lastDay;
                if ($showDay) {
                    $lastDay = $dayKey;
                }
            ?>
              <?php if ($showDay): ?>
                <div class="as-day"><span><?= e(date('d M Y', strtotime($dayKey) ?: time())) ?></span></div>
              <?php endif; ?>
              <div class="as-msg <?= $isAdmin ? 'is-admin' : 'is-member' ?>">
                <div class="as-msg-meta">
                  <span><?= $isAdmin ? ('Support' . ($adminName !== '' ? ' · ' . e($adminName) : '')) : ('Member · ' . e($activeMember)) ?></span>
                  <time><?= e(arowin_support_fmt_time_short($created)) ?></time>
                </div>
                <div class="as-msg-bubble"><?= nl2br(e((string) ($msg['body'] ?? ''))) ?></div>
              </div>
            <?php endforeach; ?>
            <div id="asChatEnd"></div>
          <?php endif; ?>
        </div>

        <form method="post" class="as-composer" autocomplete="off">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="reply">
          <input type="hidden" name="thread_id" value="<?= (int) $activeThreadId ?>">
          <input type="hidden" name="member" value="<?= e($activeMember) ?>">
          <input type="hidden" name="filter" value="<?= e($filter) ?>">
          <input type="hidden" name="q" value="<?= e($q) ?>">
          <label class="as-composer-field">
            <span class="visually-hidden">Reply</span>
            <textarea name="admin_reply" required maxlength="4000" rows="3" placeholder="Reply to <?= e($activeMember) ?> on ticket #<?= (int) $activeThreadId ?>…"<?= $tStatus === 'closed' ? ' disabled' : '' ?>></textarea>
          </label>
          <div class="as-composer-row">
            <label>
              After send
              <select name="status"<?= $tStatus === 'closed' ? ' disabled' : '' ?>>
                <option value="open" selected>Keep open (wait for member)</option>
                <option value="closed">Close ticket</option>
              </select>
            </label>
            <button type="submit" class="admin-btn primary"<?= $tStatus === 'closed' ? ' disabled' : '' ?>>Send reply</button>
          </div>
          <?php if ($tStatus === 'closed'): ?>
            <p class="as-composer-hint">This ticket is closed. Reopen it to send another reply.</p>
          <?php else: ?>
            <p class="as-composer-hint">Only <strong><?= e($activeMember) ?></strong> sees this reply in their dashboard Support chat.</p>
          <?php endif; ?>
        </form>
      <?php endif; ?>
    </div>
  </section>

<?php endif; ?>

<script>
(function () {
  var body = document.getElementById("asChatBody");
  if (body) body.scrollTop = body.scrollHeight;
  if (location.hash === "#asChatEnd") {
    var end = document.getElementById("asChatEnd");
    if (end) end.scrollIntoView({ block: "end" });
  }

  // Auto-refresh when new tickets arrive (other members messaging)
  var snapWaiting = <?= (int) $counts['waiting'] ?>;
  var snapLatest = <?= (int) $snapLatestId ?>;
  var pollUrl = <?= json_encode(admin_url('/dashboard/support/poll'), JSON_UNESCAPED_SLASHES) ?>;
  var filter = <?= json_encode($filter) ?>;
  setInterval(function () {
    if (filter === "public" || document.hidden) return;
    fetch(pollUrl, { credentials: "same-origin", headers: { "Accept": "application/json" } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (data) {
        if (!data || !data.ok) return;
        var w = Number(data.waiting || 0);
        var lid = Number(data.latest_id || 0);
        if (w > snapWaiting || (lid > 0 && lid !== snapLatest && w > 0)) {
          var banner = document.getElementById("asNewBanner");
          if (!banner) {
            banner = document.createElement("div");
            banner.id = "asNewBanner";
            banner.className = "as-new-banner";
            banner.innerHTML = "<strong>New support activity</strong> — <a href=\"" + location.pathname + location.search + "\">Refresh inbox</a>";
            var toolbar = document.querySelector(".as-toolbar");
            if (toolbar && toolbar.parentNode) toolbar.parentNode.insertBefore(banner, toolbar.nextSibling);
          }
        }
        var badge = document.getElementById("asNavBadge");
        if (badge) {
          badge.textContent = w > 0 ? String(w) : "";
          badge.hidden = w < 1;
        }
      })
      .catch(function () {});
  }, 20000);
})();
</script>

<?php require dirname(__DIR__, 2) . '/includes/layout-end.php'; ?>
