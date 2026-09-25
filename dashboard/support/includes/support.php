<?php
/**
 * Support module — multiple tickets per member (one open at a time).
 * Shared by member dashboard and admin support desk.
 */
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/includes/contact-repository.php';

/**
 * @return array<string, string>
 */
function arowin_support_topic_labels(): array
{
    return [
        'node'        => 'Node registration & activation',
        'matching'    => 'USDT matching & pairing',
        'payouts'     => 'Payouts, caps & ledger',
        'technical'   => 'Technical / Operator Terminal',
        'partnership' => 'Partnership & integrations',
        'compliance'  => 'Legal & compliance',
        'other'       => 'Other',
    ];
}

/**
 * @return list<string>
 */
function arowin_support_allowed_topics(): array
{
    return array_keys(arowin_support_topic_labels());
}

function arowin_support_topic_label(string $topic): string
{
    $labels = arowin_support_topic_labels();
    return $labels[$topic] ?? ucfirst($topic);
}

/**
 * @param array<string,mixed> $user
 * @return array{member_id:string,topic:string,subject:string,message:string}
 */
function arowin_support_form_defaults(array $user): array
{
    return [
        'member_id' => (string) ($user['username'] ?? ''),
        'topic'     => '',
        'subject'   => '',
        'message'   => '',
    ];
}

/**
 * Ensure threads + messages schema (safe to call repeatedly).
 */
function arowin_ensure_support_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once dirname(__DIR__, 2) . '/includes/perf.php';
    }
    arowin_schema_once('support', 3, static function (): void {
    try {
        $pdo = arowin_db();

        // Legacy reply cols on contact_inquiries (public contact + backfill)
        $cols = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM contact_inquiries');
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $cols[(string) ($row['Field'] ?? '')] = true;
            }
            if (empty($cols['admin_reply'])) {
                $pdo->exec('ALTER TABLE contact_inquiries ADD COLUMN `admin_reply` TEXT NULL');
            }
            if (empty($cols['admin_replied_at'])) {
                $pdo->exec('ALTER TABLE contact_inquiries ADD COLUMN `admin_replied_at` TIMESTAMP NULL DEFAULT NULL');
            }
        } catch (Throwable $e) {
            // table may not exist yet
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `support_threads` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `username` VARCHAR(64) NOT NULL,
                `full_name` VARCHAR(190) DEFAULT NULL,
                `email` VARCHAR(190) DEFAULT NULL,
                `topic` VARCHAR(60) DEFAULT NULL,
                `subject` VARCHAR(190) DEFAULT NULL,
                `status` ENUM(\'open\',\'closed\') NOT NULL DEFAULT \'open\',
                `last_sender` ENUM(\'member\',\'admin\') DEFAULT NULL,
                `last_preview` VARCHAR(255) DEFAULT NULL,
                `last_message_at` TIMESTAMP NULL DEFAULT NULL,
                `waiting_on` ENUM(\'admin\',\'member\') NOT NULL DEFAULT \'admin\',
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_support_thread_user` (`username`),
                KEY `idx_support_thread_status` (`status`),
                KEY `idx_support_thread_waiting` (`waiting_on`),
                KEY `idx_support_thread_last` (`last_message_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Upgrade: allow multiple tickets per member
        try {
            $idx = $pdo->query("SHOW INDEX FROM support_threads WHERE Key_name = 'uq_support_thread_user'")->fetchAll();
            if ($idx) {
                $pdo->exec('ALTER TABLE support_threads DROP INDEX `uq_support_thread_user`');
            }
        } catch (Throwable $e) {
            // ignore
        }
        try {
            $hasUserIdx = $pdo->query("SHOW INDEX FROM support_threads WHERE Key_name = 'idx_support_thread_user'")->fetchAll();
            if (!$hasUserIdx) {
                $pdo->exec('ALTER TABLE support_threads ADD KEY `idx_support_thread_user` (`username`)');
            }
        } catch (Throwable $e) {
            // ignore
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS `support_messages` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `thread_id` BIGINT UNSIGNED DEFAULT NULL,
                `username` VARCHAR(64) NOT NULL,
                `inquiry_id` BIGINT UNSIGNED DEFAULT NULL,
                `sender` ENUM(\'member\',\'admin\') NOT NULL,
                `topic` VARCHAR(60) DEFAULT NULL,
                `subject` VARCHAR(190) DEFAULT NULL,
                `body` TEXT NOT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_support_msg_thread` (`thread_id`),
                KEY `idx_support_msg_user` (`username`),
                KEY `idx_support_msg_inquiry` (`inquiry_id`),
                KEY `idx_support_msg_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Add thread_id if upgrading older installs
        $msgCols = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM support_messages');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $msgCols[(string) ($row['Field'] ?? '')] = true;
        }
        if (empty($msgCols['thread_id'])) {
            $pdo->exec('ALTER TABLE support_messages ADD COLUMN `thread_id` BIGINT UNSIGNED DEFAULT NULL AFTER `id`');
            $pdo->exec('ALTER TABLE support_messages ADD KEY `idx_support_msg_thread` (`thread_id`)');
        }
        if (empty($msgCols['admin_username'])) {
            $pdo->exec('ALTER TABLE support_messages ADD COLUMN `admin_username` VARCHAR(64) DEFAULT NULL AFTER `sender`');
        }

        // Desk UX columns on threads
        $threadCols = [];
        $tStmt = $pdo->query('SHOW COLUMNS FROM support_threads');
        foreach ($tStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $threadCols[(string) ($row['Field'] ?? '')] = true;
        }
        if (empty($threadCols['member_last_seen_at'])) {
            $pdo->exec('ALTER TABLE support_threads ADD COLUMN `member_last_seen_at` TIMESTAMP NULL DEFAULT NULL');
        }
        if (empty($threadCols['admin_last_seen_at'])) {
            $pdo->exec('ALTER TABLE support_threads ADD COLUMN `admin_last_seen_at` TIMESTAMP NULL DEFAULT NULL');
        }
        if (empty($threadCols['priority'])) {
            $pdo->exec("ALTER TABLE support_threads ADD COLUMN `priority` ENUM('low','normal','high') NOT NULL DEFAULT 'normal'");
        }

        arowin_support_backfill_threads();
    } catch (Throwable $e) {
        error_log('Support schema ensure failed: ' . $e->getMessage());
    }
    });
}

/**
 * Build threads from existing messages / inquiries (idempotent).
 */
function arowin_support_backfill_threads(): void
{
    static $ran = false;
    if ($ran) {
        return;
    }
    $ran = true;

    try {
        $pdo = arowin_db();

        // From support_messages without a thread
        $users = $pdo->query(
            "SELECT DISTINCT UPPER(username) AS u FROM support_messages
             WHERE username IS NOT NULL AND username <> ''"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        foreach ($users as $u) {
            $memberId = arowin_normalize_sponsor_id((string) $u);
            if ($memberId === '') {
                continue;
            }
            arowin_support_ensure_thread_row($memberId);
            $thread = arowin_support_get_thread_by_username($memberId);
            if (!$thread) {
                continue;
            }
            $tid = (int) $thread['id'];
            $pdo->prepare(
                'UPDATE support_messages SET thread_id = :t
                 WHERE UPPER(username) = :u AND (thread_id IS NULL OR thread_id = 0)'
            )->execute([':t' => $tid, ':u' => $memberId]);
            arowin_support_refresh_thread($tid);
        }

        // From member-linked contact_inquiries not yet in messages
        try {
            $tickets = $pdo->query(
                "SELECT id, organization, topic, subject, message, status, admin_reply, admin_replied_at, created_at,
                        first_name, last_name, email
                 FROM contact_inquiries
                 WHERE organization IS NOT NULL AND TRIM(organization) <> ''
                 ORDER BY id ASC
                 LIMIT 2000"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $e) {
            $tickets = [];
        }

        foreach ($tickets as $t) {
            $memberId = arowin_normalize_sponsor_id((string) ($t['organization'] ?? ''));
            if ($memberId === '') {
                continue;
            }
            $name = trim((string) ($t['first_name'] ?? '') . ' ' . (string) ($t['last_name'] ?? ''));
            $email = trim((string) ($t['email'] ?? ''));
            arowin_support_ensure_thread_row($memberId, [
                'full_name' => $name,
                'email'     => $email,
                'topic'     => (string) ($t['topic'] ?? ''),
                'subject'   => (string) ($t['subject'] ?? ''),
            ]);
            $thread = arowin_support_get_thread_by_username($memberId);
            if (!$thread) {
                continue;
            }
            $tid = (int) $thread['id'];
            $iid = (int) ($t['id'] ?? 0);

            $exists = $pdo->prepare(
                'SELECT id FROM support_messages WHERE inquiry_id = :i AND sender = \'member\' LIMIT 1'
            );
            $exists->execute([':i' => $iid]);
            if (!$exists->fetch()) {
                $ins = $pdo->prepare(
                    'INSERT INTO support_messages (thread_id, username, inquiry_id, sender, topic, subject, body, created_at)
                     VALUES (:tid, :u, :i, \'member\', :t, :s, :b, COALESCE(:c, CURRENT_TIMESTAMP))'
                );
                $ins->execute([
                    ':tid' => $tid,
                    ':u'   => $memberId,
                    ':i'   => $iid,
                    ':t'   => (string) ($t['topic'] ?? ''),
                    ':s'   => (string) ($t['subject'] ?? ''),
                    ':b'   => (string) ($t['message'] ?? ''),
                    ':c'   => (string) ($t['created_at'] ?? '') ?: null,
                ]);
            }

            $reply = trim((string) ($t['admin_reply'] ?? ''));
            if ($reply !== '') {
                $existsA = $pdo->prepare(
                    'SELECT id FROM support_messages WHERE inquiry_id = :i AND sender = \'admin\' LIMIT 1'
                );
                $existsA->execute([':i' => $iid]);
                if (!$existsA->fetch()) {
                    $insA = $pdo->prepare(
                        'INSERT INTO support_messages (thread_id, username, inquiry_id, sender, topic, subject, body, created_at)
                         VALUES (:tid, :u, :i, \'admin\', :t, :s, :b, COALESCE(:c, CURRENT_TIMESTAMP))'
                    );
                    $insA->execute([
                        ':tid' => $tid,
                        ':u'   => $memberId,
                        ':i'   => $iid,
                        ':t'   => (string) ($t['topic'] ?? ''),
                        ':s'   => (string) ($t['subject'] ?? ''),
                        ':b'   => $reply,
                        ':c'   => (string) ($t['admin_replied_at'] ?? $t['created_at'] ?? '') ?: null,
                    ]);
                }
            }

            if ((string) ($t['status'] ?? '') === 'closed') {
                $pdo->prepare('UPDATE support_threads SET status = \'closed\' WHERE id = :id')->execute([':id' => $tid]);
            }
            arowin_support_refresh_thread($tid);
        }
    } catch (Throwable $e) {
        // ignore backfill failures
    }
}

/**
 * Create a brand-new open ticket for a member.
 *
 * @param array{full_name?:string,email?:string,topic?:string,subject?:string} $meta
 */
function arowin_support_create_thread(string $memberId, array $meta = []): int
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return 0;
    }

    $stmt = arowin_db()->prepare(
        'INSERT INTO support_threads (username, full_name, email, topic, subject, status, waiting_on)
         VALUES (:u, :fn, :em, :t, :s, \'open\', \'admin\')'
    );
    $stmt->execute([
        ':u'  => $memberId,
        ':fn' => !empty($meta['full_name']) ? mb_substr(trim((string) $meta['full_name']), 0, 190) : null,
        ':em' => !empty($meta['email']) ? mb_substr(trim((string) $meta['email']), 0, 190) : null,
        ':t'  => !empty($meta['topic']) ? (string) $meta['topic'] : null,
        ':s'  => !empty($meta['subject']) ? mb_substr(trim((string) $meta['subject']), 0, 190) : null,
    ]);
    return (int) arowin_db()->lastInsertId();
}

/**
 * Get or create the member's current open ticket (never reuses a closed one).
 *
 * @param array{full_name?:string,email?:string,topic?:string,subject?:string} $meta
 */
function arowin_support_ensure_thread_row(string $memberId, array $meta = []): int
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return 0;
    }

    $existing = arowin_support_get_open_thread($memberId);
    if ($existing) {
        $id = (int) $existing['id'];
        $sets = [];
        $params = [':id' => $id];
        if (!empty($meta['full_name']) && trim((string) ($existing['full_name'] ?? '')) === '') {
            $sets[] = 'full_name = :fn';
            $params[':fn'] = mb_substr(trim((string) $meta['full_name']), 0, 190);
        }
        if (!empty($meta['email']) && trim((string) ($existing['email'] ?? '')) === '') {
            $sets[] = 'email = :em';
            $params[':em'] = mb_substr(trim((string) $meta['email']), 0, 190);
        }
        if ($sets !== []) {
            arowin_db()->prepare('UPDATE support_threads SET ' . implode(', ', $sets) . ' WHERE id = :id')
                ->execute($params);
        }
        return $id;
    }

    return arowin_support_create_thread($memberId, $meta);
}

/**
 * Current open ticket for a member (if any).
 *
 * @return array<string,mixed>|null
 */
function arowin_support_get_open_thread(string $memberId): ?array
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return null;
    }
    $stmt = arowin_db()->prepare(
        "SELECT * FROM support_threads
         WHERE UPPER(username) = :u AND status = 'open'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':u' => $memberId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * Latest thread for a member (open preferred, else most recent).
 * Kept for admin/legacy callers.
 *
 * @return array<string,mixed>|null
 */
function arowin_support_get_thread_by_username(string $memberId): ?array
{
    $open = arowin_support_get_open_thread($memberId);
    if ($open) {
        return $open;
    }
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return null;
    }
    $stmt = arowin_db()->prepare(
        'SELECT * FROM support_threads WHERE UPPER(username) = :u ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([':u' => $memberId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_support_list_member_threads(string $memberId, string $status = 'all', int $limit = 50): array
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return [];
    }
    $limit = max(1, min(100, $limit));
    $status = in_array($status, ['all', 'open', 'closed'], true) ? $status : 'all';

    $sql = 'SELECT * FROM support_threads WHERE UPPER(username) = :u';
    if ($status !== 'all') {
        $sql .= ' AND status = :s';
    }
    $sql .= " ORDER BY COALESCE(last_message_at, created_at) DESC, id DESC LIMIT {$limit}";

    try {
        $stmt = arowin_db()->prepare($sql);
        $params = [':u' => $memberId];
        if ($status !== 'all') {
            $params[':s'] = $status;
        }
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array<string,mixed>|null
 */
function arowin_support_get_thread(int $id): ?array
{
    arowin_ensure_support_schema();
    if ($id < 1) {
        return null;
    }
    $stmt = arowin_db()->prepare('SELECT * FROM support_threads WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function arowin_support_refresh_thread(int $threadId): void
{
    if ($threadId < 1) {
        return;
    }
    try {
        $pdo = arowin_db();
        $last = $pdo->prepare(
            'SELECT sender, body, topic, subject, created_at
             FROM support_messages
             WHERE thread_id = :t
             ORDER BY id DESC LIMIT 1'
        );
        $last->execute([':t' => $threadId]);
        $msg = $last->fetch(PDO::FETCH_ASSOC);
        if (!$msg) {
            return;
        }
        $sender = ((string) ($msg['sender'] ?? '')) === 'admin' ? 'admin' : 'member';
        $preview = trim(preg_replace('/\s+/', ' ', (string) ($msg['body'] ?? '')) ?? '');
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117) . '…';
        }
        $waiting = $sender === 'member' ? 'admin' : 'member';
        $pdo->prepare(
            'UPDATE support_threads
             SET last_sender = :ls,
                 last_preview = :lp,
                 last_message_at = :lm,
                 waiting_on = CASE WHEN status = \'closed\' THEN waiting_on ELSE :w END,
                 topic = COALESCE(NULLIF(:topic, \'\'), topic),
                 subject = COALESCE(NULLIF(:subject, \'\'), subject)
             WHERE id = :id'
        )->execute([
            ':ls'      => $sender,
            ':lp'      => $preview,
            ':lm'      => (string) ($msg['created_at'] ?? date('Y-m-d H:i:s')),
            ':w'       => $waiting,
            ':topic'   => (string) ($msg['topic'] ?? ''),
            ':subject' => (string) ($msg['subject'] ?? ''),
            ':id'      => $threadId,
        ]);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * @param array<string,mixed> $user
 * @param array<string,string> $form
 * @return array{ok:bool, message?:string, thread_id?:int}
 */
function arowin_support_submit(array $user, array $form): array
{
    arowin_ensure_support_schema();

    $topics = arowin_support_allowed_topics();
    $memberId = arowin_normalize_sponsor_id((string) ($user['username'] ?? ''));
    $fullName = trim((string) ($user['full_name'] ?? ''));
    $email = trim((string) ($user['email'] ?? ''));
    $phone = trim((string) ($user['phone'] ?? ''));

    $topic = trim((string) ($form['topic'] ?? ''));
    $subject = trim((string) ($form['subject'] ?? ''));
    $message = trim((string) ($form['message'] ?? ''));
    $forceNew = !empty($form['force_new']);

    if ($memberId === '') {
        return ['ok' => false, 'message' => 'Member ID is missing. Sign in again.'];
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Your profile email is missing or invalid. Update profile first.'];
    }
    if ($message === '' || mb_strlen($message) > 4000) {
        return ['ok' => false, 'message' => 'Please enter a message (max 4000 characters).'];
    }

    $openThread = arowin_support_get_open_thread($memberId);
    $isNewTicket = $forceNew || !$openThread;

    if ($isNewTicket && $openThread && $forceNew) {
        return ['ok' => false, 'message' => 'You already have an open request. Send an update there, or wait until support closes it.'];
    }

    if ($isNewTicket) {
        if (!in_array($topic, $topics, true)) {
            return ['ok' => false, 'message' => 'Please select a type.'];
        }
        if ($subject === '' || mb_strlen($subject) > 190) {
            return ['ok' => false, 'message' => 'Please enter a subject (max 190 characters).'];
        }
    } else {
        $topic = (string) ($openThread['topic'] ?? $topic);
        $subject = (string) ($openThread['subject'] ?? $subject);
        if ($topic === '' || !in_array($topic, $topics, true)) {
            $topic = 'other';
        }
        if ($subject === '') {
            $subject = 'Support request';
        }
    }

    try {
        $parts = preg_split('/\s+/', $fullName !== '' ? $fullName : 'Member', 2) ?: ['Member'];
        $first = (string) ($parts[0] ?? 'Member');
        $last = (string) ($parts[1] ?? 'Operator');
        $displayName = $fullName !== '' ? $fullName : ($first . ' ' . $last);

        if ($isNewTicket) {
            $threadId = arowin_support_create_thread($memberId, [
                'full_name' => $displayName,
                'email'     => $email,
                'topic'     => $topic,
                'subject'   => $subject,
            ]);
        } else {
            $threadId = (int) ($openThread['id'] ?? 0);
            arowin_db()->prepare(
                "UPDATE support_threads SET waiting_on = 'admin' WHERE id = :id AND status = 'open'"
            )->execute([':id' => $threadId]);
        }

        if ($threadId < 1) {
            return ['ok' => false, 'message' => 'Unable to open a support ticket right now.'];
        }

        $inquiryId = null;
        if ($isNewTicket) {
            $inquiryId = arowin_save_contact_inquiry([
                'first_name'   => mb_substr($first, 0, 100),
                'last_name'    => mb_substr($last !== '' ? $last : '—', 0, 100),
                'email'        => $email,
                'phone'        => $phone !== '' ? mb_substr($phone, 0, 40) : null,
                'organization' => $memberId,
                'topic'        => $topic,
                'subject'      => $subject,
                'message'      => $message,
                'consent'      => true,
                'ip_address'   => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'   => isset($_SERVER['HTTP_USER_AGENT'])
                    ? mb_substr((string) $_SERVER['HTTP_USER_AGENT'], 0, 255)
                    : null,
            ]);
        }

        arowin_support_insert_message([
            'thread_id'  => $threadId,
            'username'   => $memberId,
            'inquiry_id' => $inquiryId && $inquiryId > 0 ? $inquiryId : null,
            'sender'     => 'member',
            'topic'      => $topic,
            'subject'    => $subject,
            'body'       => $message,
        ]);

        arowin_support_refresh_thread($threadId);
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => env('APP_DEBUG', false)
                ? ('Unable to send: ' . $e->getMessage())
                : 'Unable to send right now. Please try again shortly.',
        ];
    }

    return [
        'ok' => true,
        'message' => $isNewTicket ? 'Support request submitted.' : 'Update sent.',
        'thread_id' => $threadId ?? 0,
    ];
}

/**
 * @param array{thread_id?:?int,username:string,inquiry_id?:?int,sender:string,topic?:?string,subject?:?string,body:string} $row
 */
function arowin_support_insert_message(array $row): int
{
    arowin_ensure_support_schema();
    $username = arowin_normalize_sponsor_id((string) ($row['username'] ?? ''));
    $threadId = isset($row['thread_id']) ? (int) $row['thread_id'] : 0;
    if ($threadId < 1 && $username !== '') {
        $threadId = arowin_support_ensure_thread_row($username);
    }

    $stmt = arowin_db()->prepare(
        'INSERT INTO support_messages (thread_id, username, inquiry_id, sender, admin_username, topic, subject, body)
         VALUES (:tid, :u, :i, :s, :au, :t, :sub, :b)'
    );
    $stmt->execute([
        ':tid' => $threadId > 0 ? $threadId : null,
        ':u'   => $username,
        ':i'   => isset($row['inquiry_id']) && (int) $row['inquiry_id'] > 0 ? (int) $row['inquiry_id'] : null,
        ':s'   => ($row['sender'] ?? '') === 'admin' ? 'admin' : 'member',
        ':au'  => ($row['sender'] ?? '') === 'admin'
            ? (trim((string) ($row['admin_username'] ?? '')) ?: null)
            : null,
        ':t'   => ($row['topic'] ?? null) !== null && (string) $row['topic'] !== '' ? (string) $row['topic'] : null,
        ':sub' => ($row['subject'] ?? null) !== null && (string) $row['subject'] !== '' ? (string) $row['subject'] : null,
        ':b'   => (string) ($row['body'] ?? ''),
    ]);
    return (int) arowin_db()->lastInsertId();
}

/**
 * Messages for a ticket thread (or member's open ticket if no id).
 *
 * @return list<array{id:string,sender:string,topic:string,subject:string,body:string,created_at:string}>
 */
function arowin_support_list_thread_messages(int $threadId, int $limit = 200): array
{
    arowin_ensure_support_schema();
    if ($threadId < 1) {
        return [];
    }
    $limit = max(1, min(500, $limit));

    try {
        $stmt = arowin_db()->prepare(
            "SELECT id, sender, admin_username, topic, subject, body, created_at
             FROM support_messages
             WHERE thread_id = :t
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $stmt->execute([':t' => $threadId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'             => 'm' . (int) ($r['id'] ?? 0),
                'sender'         => (string) ($r['sender'] ?? 'member'),
                'admin_username' => (string) ($r['admin_username'] ?? ''),
                'topic'          => (string) ($r['topic'] ?? ''),
                'subject'        => (string) ($r['subject'] ?? ''),
                'body'           => (string) ($r['body'] ?? ''),
                'created_at'     => (string) ($r['created_at'] ?? ''),
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Chronological messages for a member's current open ticket (fallback: latest thread).
 *
 * @return list<array{id:string,sender:string,topic:string,subject:string,body:string,created_at:string}>
 */
function arowin_support_list_chat(string $memberId, int $limit = 200): array
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return [];
    }

    $thread = arowin_support_get_open_thread($memberId);
    if (!$thread) {
        $thread = arowin_support_get_thread_by_username($memberId);
    }
    if ($thread) {
        return arowin_support_list_thread_messages((int) $thread['id'], $limit);
    }

    $limit = max(1, min(500, $limit));
    try {
        $stmt = arowin_db()->prepare(
            "SELECT id, sender, topic, subject, body, created_at
             FROM support_messages
             WHERE UPPER(username) = :m
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $stmt->execute([':m' => $memberId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'id'         => 'm' . (int) ($r['id'] ?? 0),
                'sender'     => (string) ($r['sender'] ?? 'member'),
                'topic'      => (string) ($r['topic'] ?? ''),
                'subject'    => (string) ($r['subject'] ?? ''),
                'body'       => (string) ($r['body'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }
        return $out;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Admin inbox — one row per member conversation.
 *
 * @return list<array<string,mixed>>
 */
function arowin_support_admin_inbox(string $filter = 'all', string $q = '', int $limit = 200): array
{
    arowin_ensure_support_schema();
    $limit = max(1, min(500, $limit));
    $filter = in_array($filter, ['all', 'waiting', 'open', 'closed'], true) ? $filter : 'all';
    $q = trim($q);

    $where = ['1=1'];
    $params = [];

    if ($filter === 'waiting') {
        $where[] = "status = 'open' AND waiting_on = 'admin'";
    } elseif ($filter === 'open') {
        $where[] = "status = 'open'";
    } elseif ($filter === 'closed') {
        $where[] = "status = 'closed'";
    }

    if ($q !== '') {
        $where[] = '(UPPER(username) LIKE :q OR full_name LIKE :q2 OR email LIKE :q2 OR subject LIKE :q2 OR last_preview LIKE :q2)';
        $params[':q'] = '%' . strtoupper($q) . '%';
        $params[':q2'] = '%' . $q . '%';
    }

    $sql = 'SELECT * FROM support_threads WHERE ' . implode(' AND ', $where)
        . ' ORDER BY COALESCE(last_message_at, created_at) DESC, id DESC'
        . " LIMIT {$limit}";

    try {
        $stmt = arowin_db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * @return array{waiting:int,open:int,closed:int,all:int}
 */
function arowin_support_admin_counts(): array
{
    arowin_ensure_support_schema();
    $counts = ['waiting' => 0, 'open' => 0, 'closed' => 0, 'all' => 0];
    try {
        $rows = arowin_db()->query(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'open' AND waiting_on = 'admin' THEN 1 ELSE 0 END) AS waiting,
                SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_n,
                SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END) AS closed_n
             FROM support_threads"
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $counts['all'] = (int) ($rows['total'] ?? 0);
        $counts['waiting'] = (int) ($rows['waiting'] ?? 0);
        $counts['open'] = (int) ($rows['open_n'] ?? 0);
        $counts['closed'] = (int) ($rows['closed_n'] ?? 0);
    } catch (Throwable $e) {
        // ignore
    }
    return $counts;
}

/**
 * Admin reply into a member thread (by thread id).
 *
 * @return array{ok:bool,message:string}
 */
function arowin_support_admin_reply_thread(int $threadId, string $reply, string $status = 'open'): array
{
    arowin_ensure_support_schema();
    $threadId = max(0, $threadId);
    $reply = trim($reply);
    if ($threadId < 1) {
        return ['ok' => false, 'message' => 'Invalid conversation.'];
    }
    if ($reply === '' || mb_strlen($reply) > 4000) {
        return ['ok' => false, 'message' => 'Enter a reply (max 4000 characters).'];
    }
    if (!in_array($status, ['open', 'closed'], true)) {
        $status = 'open';
    }

    $thread = arowin_support_get_thread($threadId);
    if (!$thread) {
        return ['ok' => false, 'message' => 'Conversation not found.'];
    }

    $memberId = arowin_normalize_sponsor_id((string) ($thread['username'] ?? ''));
    if ($memberId === '') {
        return ['ok' => false, 'message' => 'Member ID missing on this conversation.'];
    }

    try {
        $adminUser = '';
        if (function_exists('arowin_admin_username')) {
            $adminUser = trim((string) arowin_admin_username());
        }
        arowin_support_insert_message([
            'thread_id'      => $threadId,
            'username'       => $memberId,
            'sender'         => 'admin',
            'admin_username' => $adminUser,
            'topic'          => (string) ($thread['topic'] ?? ''),
            'subject'        => (string) ($thread['subject'] ?? ''),
            'body'           => $reply,
        ]);

        arowin_db()->prepare(
            'UPDATE support_threads
             SET status = :s,
                 waiting_on = \'member\',
                 last_sender = \'admin\',
                 last_preview = :p,
                 last_message_at = NOW(),
                 admin_last_seen_at = NOW()
             WHERE id = :id'
        )->execute([
            ':s'  => $status,
            ':p'  => mb_substr(trim(preg_replace('/\s+/', ' ', $reply) ?? ''), 0, 120),
            ':id' => $threadId,
        ]);

        // Mirror last reply onto latest member inquiry for legacy admin views
        try {
            $pdo = arowin_db();
            $find = $pdo->prepare(
                'SELECT id FROM contact_inquiries WHERE UPPER(organization) = :m ORDER BY id DESC LIMIT 1'
            );
            $find->execute([':m' => $memberId]);
            $iid = (int) ($find->fetchColumn() ?: 0);
            if ($iid > 0) {
                $pdo->prepare(
                    'UPDATE contact_inquiries
                     SET admin_reply = :r, admin_replied_at = NOW(),
                         status = :st
                     WHERE id = :id LIMIT 1'
                )->execute([
                    ':r'  => $reply,
                    ':st' => $status === 'closed' ? 'closed' : 'in_progress',
                    ':id' => $iid,
                ]);
            }
        } catch (Throwable $e) {
            // optional
        }

        arowin_support_notify_member_reply($thread, $reply);

        return ['ok' => true, 'message' => 'Reply sent to ' . $memberId . '.'];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => env('APP_DEBUG', false)
                ? ('Reply failed: ' . $e->getMessage())
                : 'Unable to save reply right now.',
        ];
    }
}

/**
 * Close / reopen without a message.
 *
 * @return array{ok:bool,message:string}
 */
function arowin_support_admin_set_status(int $threadId, string $status): array
{
    arowin_ensure_support_schema();
    if ($threadId < 1 || !in_array($status, ['open', 'closed'], true)) {
        return ['ok' => false, 'message' => 'Invalid request.'];
    }
    try {
        $stmt = arowin_db()->prepare(
            'UPDATE support_threads SET status = :s WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':s' => $status, ':id' => $threadId]);
        return ['ok' => true, 'message' => $status === 'closed' ? 'Conversation closed.' : 'Conversation reopened.'];
    } catch (Throwable $e) {
        return ['ok' => false, 'message' => 'Could not update status.'];
    }
}

/**
 * Public website contact inquiries (no member ID).
 *
 * @return list<array<string,mixed>>
 */
function arowin_support_list_public_inquiries(int $limit = 100): array
{
    arowin_ensure_support_schema();
    $limit = max(1, min(300, $limit));
    try {
        $stmt = arowin_db()->query(
            "SELECT id, first_name, last_name, email, phone, topic, subject, message, status,
                    admin_reply, admin_replied_at, created_at
             FROM contact_inquiries
             WHERE organization IS NULL OR TRIM(organization) = ''
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Legacy ticket reply (public contact form).
 *
 * @return array{ok:bool,message:string}
 */
function arowin_support_admin_reply(int $id, string $reply, string $status = 'in_progress'): array
{
    arowin_ensure_support_schema();
    $id = max(0, $id);
    $reply = trim($reply);
    if ($id < 1) {
        return ['ok' => false, 'message' => 'Invalid ticket.'];
    }
    if ($reply === '' || mb_strlen($reply) > 4000) {
        return ['ok' => false, 'message' => 'Enter a reply (max 4000 characters).'];
    }
    if (!in_array($status, ['new', 'in_progress', 'closed'], true)) {
        $status = 'in_progress';
    }

    try {
        $pdo = arowin_db();
        $find = $pdo->prepare(
            'SELECT id, organization, topic, subject FROM contact_inquiries WHERE id = :id LIMIT 1'
        );
        $find->execute([':id' => $id]);
        $ticket = $find->fetch(PDO::FETCH_ASSOC);
        if (!$ticket) {
            return ['ok' => false, 'message' => 'Ticket not found.'];
        }

        $stmt = $pdo->prepare(
            'UPDATE contact_inquiries
             SET admin_reply = :r, admin_replied_at = NOW(), status = :s
             WHERE id = :id
             LIMIT 1'
        );
        $stmt->execute([':r' => $reply, ':s' => $status, ':id' => $id]);

        $memberId = arowin_normalize_sponsor_id((string) ($ticket['organization'] ?? ''));
        if ($memberId !== '') {
            $threadId = arowin_support_ensure_thread_row($memberId, [
                'topic'   => (string) ($ticket['topic'] ?? ''),
                'subject' => (string) ($ticket['subject'] ?? ''),
            ]);
            arowin_support_insert_message([
                'thread_id'  => $threadId,
                'username'   => $memberId,
                'inquiry_id' => $id,
                'sender'     => 'admin',
                'topic'      => (string) ($ticket['topic'] ?? ''),
                'subject'    => (string) ($ticket['subject'] ?? ''),
                'body'       => $reply,
            ]);
            arowin_db()->prepare(
                'UPDATE support_threads SET status = :st, waiting_on = \'member\' WHERE id = :id'
            )->execute([
                ':st' => $status === 'closed' ? 'closed' : 'open',
                ':id' => $threadId,
            ]);
            arowin_support_refresh_thread($threadId);
        }

        return ['ok' => true, 'message' => 'Reply saved.'];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'message' => env('APP_DEBUG', false)
                ? ('Reply failed: ' . $e->getMessage())
                : 'Unable to save reply right now.',
        ];
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_support_list_member_tickets(string $memberId, int $limit = 40): array
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return [];
    }
    $limit = max(1, min(100, $limit));

    try {
        $stmt = arowin_db()->prepare(
            "SELECT id, topic, subject, message, status, admin_reply, admin_replied_at, created_at
             FROM contact_inquiries
             WHERE UPPER(organization) = :m
             ORDER BY id DESC
             LIMIT {$limit}"
        );
        $stmt->execute([':m' => $memberId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
        return [];
    }
}

function arowin_support_status_label(string $status, ?string $waitingOn = null): string
{
    if ($status === 'closed') {
        return 'Closed';
    }
    if ($waitingOn === 'admin') {
        return 'Needs reply';
    }
    if ($waitingOn === 'member') {
        return 'Awaiting member';
    }
    return 'Open';
}

function arowin_support_fmt_time(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    return date('d M Y, h:i A', $ts);
}

function arowin_support_fmt_time_short(?string $dt): string
{
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    if ($ts === false) {
        return $dt;
    }
    $now = time();
    if (date('Y-m-d', $ts) === date('Y-m-d', $now)) {
        return date('h:i A', $ts);
    }
    if (date('Y', $ts) === date('Y', $now)) {
        return date('d M', $ts);
    }
    return date('d M Y', $ts);
}

/**
 * Mark a ticket as seen by admin (when opening chat).
 */
function arowin_support_mark_admin_seen(int $threadId): void
{
    arowin_ensure_support_schema();
    if ($threadId < 1) {
        return;
    }
    try {
        arowin_db()->prepare(
            'UPDATE support_threads SET admin_last_seen_at = NOW() WHERE id = ? LIMIT 1'
        )->execute([$threadId]);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Mark open ticket(s) seen by member (clears “new reply” state).
 */
function arowin_support_mark_member_seen(string $memberId, ?int $threadId = null): void
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return;
    }
    try {
        if ($threadId !== null && $threadId > 0) {
            arowin_db()->prepare(
                'UPDATE support_threads SET member_last_seen_at = NOW() WHERE id = ? AND username = ? LIMIT 1'
            )->execute([$threadId, $memberId]);
            return;
        }
        arowin_db()->prepare(
            "UPDATE support_threads
             SET member_last_seen_at = NOW()
             WHERE username = ? AND status = 'open'"
        )->execute([$memberId]);
    } catch (Throwable $e) {
        // ignore
    }
}

/**
 * Unread reply count for member sidebar badge (admin replied, member not opened yet).
 */
function arowin_support_member_unread_count(string $memberId): int
{
    arowin_ensure_support_schema();
    $memberId = arowin_normalize_sponsor_id($memberId);
    if ($memberId === '') {
        return 0;
    }
    try {
        $stmt = arowin_db()->prepare(
            "SELECT COUNT(*) FROM support_threads
             WHERE username = ?
               AND status = 'open'
               AND waiting_on = 'member'
               AND (
                    member_last_seen_at IS NULL
                    OR last_message_at > member_last_seen_at
               )"
        );
        $stmt->execute([$memberId]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Whether inbox row should show unread for admin (member wrote after admin last viewed).
 *
 * @param array<string,mixed> $thread
 */
function arowin_support_thread_unread_for_admin(array $thread): bool
{
    if ((string) ($thread['status'] ?? '') !== 'open') {
        return false;
    }
    if ((string) ($thread['waiting_on'] ?? '') !== 'admin') {
        return false;
    }
    $seen = (string) ($thread['admin_last_seen_at'] ?? '');
    $last = (string) ($thread['last_message_at'] ?? '');
    if ($seen === '') {
        return true;
    }
    if ($last === '') {
        return false;
    }
    return strtotime($last) > strtotime($seen);
}

/**
 * Email member when admin replies (best-effort).
 *
 * @param array<string,mixed> $thread
 */
function arowin_support_notify_member_reply(array $thread, string $reply): void
{
    $email = trim((string) ($thread['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return;
    }
    if (!function_exists('arowin_send_mail')) {
        $mailFile = dirname(__DIR__, 3) . '/includes/mail-service.php';
        if (is_file($mailFile)) {
            require_once $mailFile;
        }
    }
    if (!function_exists('arowin_send_mail')) {
        return;
    }

    $memberId = (string) ($thread['username'] ?? '');
    $subject = trim((string) ($thread['subject'] ?? 'Support update'));
    $ticketId = (int) ($thread['id'] ?? 0);
    $preview = mb_substr(trim(preg_replace('/\s+/', ' ', $reply) ?? ''), 0, 280);
    global $BASE, $APP;
    $supportUrl = function_exists('url') ? url('support') : (arowin_abs($BASE ?? '', '/dashboard/support/'));
    $brand = (string) (($APP['legal_name'] ?? null) ?: ($APP['name'] ?? 'Arowin'));

    $html = '<p>Hello' . ($memberId !== '' ? ' <strong>' . htmlspecialchars($memberId, ENT_QUOTES, 'UTF-8') . '</strong>' : '') . ',</p>'
        . '<p>Support replied on ticket <strong>#' . $ticketId . '</strong>'
        . ($subject !== '' ? ' — ' . htmlspecialchars($subject, ENT_QUOTES, 'UTF-8') : '') . '.</p>'
        . '<blockquote style="border-left:3px solid #ff8c1a;padding:8px 12px;margin:12px 0;color:#333">'
        . nl2br(htmlspecialchars($preview, ENT_QUOTES, 'UTF-8'))
        . '</blockquote>'
        . '<p><a href="' . htmlspecialchars($supportUrl, ENT_QUOTES, 'UTF-8') . '">Open Support in your dashboard</a></p>'
        . '<p style="color:#666;font-size:12px">' . htmlspecialchars($brand, ENT_QUOTES, 'UTF-8') . '</p>';

    try {
        arowin_send_mail([
            'to'      => $email,
            'subject' => 'Arowin Support reply — ticket #' . $ticketId,
            'html'    => $html,
            'text'    => "Support replied on ticket #{$ticketId}.\n\n{$preview}\n\nOpen: {$supportUrl}",
        ]);
    } catch (Throwable $e) {
        error_log('Support notify mail failed: ' . $e->getMessage());
    }
}

/**
 * Lightweight poll payload for admin desk auto-refresh.
 *
 * @return array{waiting:int,open:int,latest_id:int,latest_at:string}
 */
function arowin_support_admin_poll_snapshot(): array
{
    $counts = arowin_support_admin_counts();
    $latestId = 0;
    $latestAt = '';
    try {
        $row = arowin_db()->query(
            'SELECT id, last_message_at FROM support_threads ORDER BY COALESCE(last_message_at, created_at) DESC, id DESC LIMIT 1'
        )->fetch(PDO::FETCH_ASSOC) ?: [];
        $latestId = (int) ($row['id'] ?? 0);
        $latestAt = (string) ($row['last_message_at'] ?? '');
    } catch (Throwable $e) {
        // ignore
    }
    return [
        'waiting'   => (int) ($counts['waiting'] ?? 0),
        'open'      => (int) ($counts['open'] ?? 0),
        'latest_id' => $latestId,
        'latest_at' => $latestAt,
    ];
}
