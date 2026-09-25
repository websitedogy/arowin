<?php
/**
 * Guest List helpers — invited friends + joiners under your sponsor ID
 */
declare(strict_types=1);

require_once __DIR__ . '/guest-options.php';

function arowin_ensure_guest_list_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = arowin_db();
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `guest_list` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `owner_username` VARCHAR(60) NOT NULL,
          `full_name` VARCHAR(120) NOT NULL,
          `phone` VARCHAR(30) DEFAULT NULL,
          `profession` VARCHAR(80) DEFAULT NULL,
          `relation_type` VARCHAR(80) DEFAULT NULL,
          `state` VARCHAR(80) DEFAULT NULL,
          `district` VARCHAR(80) DEFAULT NULL,
          `note` VARCHAR(255) DEFAULT NULL,
          `status` ENUM('invited','joined','dropped') NOT NULL DEFAULT 'invited',
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_guest_owner_status` (`owner_username`, `status`),
          KEY `idx_guest_owner_created` (`owner_username`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Upgrade older tables that only had note
    $cols = $pdo->query('SHOW COLUMNS FROM guest_list')->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip(array_map('strval', $cols ?: []));
    $alters = [
        'profession' => "ADD COLUMN `profession` VARCHAR(80) DEFAULT NULL AFTER `phone`",
        'relation_type' => "ADD COLUMN `relation_type` VARCHAR(80) DEFAULT NULL AFTER `profession`",
        'state' => "ADD COLUMN `state` VARCHAR(80) DEFAULT NULL AFTER `relation_type`",
        'district' => "ADD COLUMN `district` VARCHAR(80) DEFAULT NULL AFTER `state`",
    ];
    foreach ($alters as $col => $sql) {
        if (!isset($colSet[$col])) {
            $pdo->exec('ALTER TABLE `guest_list` ' . $sql);
        }
    }
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_guest_list_rows(string $ownerUsername, string $status = 'all'): array
{
    arowin_ensure_guest_list_schema();
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    if ($ownerUsername === '') {
        return [];
    }

    $sql = 'SELECT * FROM guest_list WHERE UPPER(owner_username) = :u';
    $params = [':u' => $ownerUsername];
    $status = strtolower(trim($status));
    if (in_array($status, ['invited', 'joined', 'dropped'], true)) {
        $sql .= ' AND status = :s';
        $params[':s'] = $status;
    } elseif ($status === 'active' || $status === 'history') {
        $sql .= " AND status NOT IN ('dropped','joined')";
    }
    $sql .= ' ORDER BY id DESC LIMIT 500';

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll() ?: [];
}

/**
 * Total guests saved under this owner (optional status filter).
 */
function arowin_guest_list_count(string $ownerUsername, string $status = 'all'): int
{
    arowin_ensure_guest_list_schema();
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    if ($ownerUsername === '') {
        return 0;
    }

    $sql = 'SELECT COUNT(*) FROM guest_list WHERE UPPER(owner_username) = :u';
    $params = [':u' => $ownerUsername];
    $status = strtolower(trim($status));
    if (in_array($status, ['invited', 'joined', 'dropped'], true)) {
        $sql .= ' AND status = :s';
        $params[':s'] = $status;
    } elseif ($status === 'active' || $status === 'history') {
        $sql .= " AND status NOT IN ('dropped','joined')";
    }

    $stmt = arowin_db()->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_guest_joiners(string $ownerUsername): array
{
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    if ($ownerUsername === '') {
        return [];
    }

    $stmt = arowin_db()->prepare(
        "SELECT username, full_name, phone, status, created_at, node_package, purchased_package
         FROM node_registrations
         WHERE UPPER(sponsor_id) = :u
           AND (power_owner IS NULL OR power_owner = '')
         ORDER BY id DESC
         LIMIT 500"
    );
    $stmt->execute([':u' => $ownerUsername]);
    return $stmt->fetchAll() ?: [];
}

/**
 * @param array{full_name?:string,phone?:string,profession?:string,relation_type?:string,state?:string,district?:string,note?:string} $data
 * @return array{ok:bool, message:string}
 */
function arowin_guest_add(string $ownerUsername, array $data): array
{
    arowin_ensure_guest_list_schema();
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    $fullName = trim((string) ($data['full_name'] ?? ''));
    $phone = preg_replace('/\D+/', '', (string) ($data['phone'] ?? '')) ?? '';
    $profession = trim((string) ($data['profession'] ?? ''));
    $relation = trim((string) ($data['relation_type'] ?? ''));
    $state = trim((string) ($data['state'] ?? ''));
    $district = trim((string) ($data['district'] ?? ''));
    $note = trim((string) ($data['note'] ?? ''));

    if ($ownerUsername === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if ($fullName === '' || mb_strlen($fullName) < 2) {
        return ['ok' => false, 'message' => 'Enter friend full name.'];
    }
    if ($phone === '' || !preg_match('/^[6-9]\d{9}$/', $phone)) {
        return ['ok' => false, 'message' => 'Enter a valid 10-digit mobile number.'];
    }
    if ($profession === '' || !in_array($profession, arowin_guest_professions(), true)) {
        return ['ok' => false, 'message' => 'Select profession.'];
    }
    if ($relation === '' || !in_array($relation, arowin_guest_relations(), true)) {
        return ['ok' => false, 'message' => 'Select relation type.'];
    }
    if ($state === '' || !in_array($state, arowin_guest_states(), true)) {
        return ['ok' => false, 'message' => 'Select state.'];
    }
    $districts = arowin_guest_districts_for_state($state);
    if ($district === '' || !in_array($district, $districts, true)) {
        return ['ok' => false, 'message' => 'Select district.'];
    }

    $dup = arowin_db()->prepare(
        'SELECT id FROM guest_list WHERE owner_username = :o AND phone = :p LIMIT 1'
    );
    $dup->execute([':o' => $ownerUsername, ':p' => $phone]);
    if ($dup->fetch()) {
        return ['ok' => false, 'message' => 'This mobile number is already in your guest list.'];
    }

    if (mb_strlen($fullName) > 120) {
        $fullName = mb_substr($fullName, 0, 120);
    }
    if (mb_strlen($note) > 255) {
        $note = mb_substr($note, 0, 255);
    }

    arowin_db()->prepare(
        'INSERT INTO guest_list
            (owner_username, full_name, phone, profession, relation_type, state, district, note, status)
         VALUES
            (:o, :n, :p, :prof, :rel, :st, :dist, :note, \'invited\')'
    )->execute([
        ':o' => $ownerUsername,
        ':n' => $fullName,
        ':p' => $phone,
        ':prof' => $profession,
        ':rel' => $relation,
        ':st' => $state,
        ':dist' => $district,
        ':note' => $note !== '' ? $note : null,
    ]);

    return ['ok' => true, 'message' => 'Friend added to guest list.'];
}

/**
 * @return array<string,mixed>|null
 */
function arowin_guest_get(string $ownerUsername, int $guestId): ?array
{
    arowin_ensure_guest_list_schema();
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    if ($ownerUsername === '' || $guestId < 1) {
        return null;
    }
    $stmt = arowin_db()->prepare(
        'SELECT * FROM guest_list
         WHERE id = :id AND UPPER(owner_username) = :o
         LIMIT 1'
    );
    $stmt->execute([':id' => $guestId, ':o' => $ownerUsername]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_guest_set_status(string $ownerUsername, int $guestId, string $status): array
{
    arowin_ensure_guest_list_schema();
    $ownerUsername = arowin_normalize_sponsor_id($ownerUsername);
    $status = strtolower(trim($status));
    if (!in_array($status, ['invited', 'joined', 'dropped'], true)) {
        return ['ok' => false, 'message' => 'Invalid status.'];
    }
    if ($ownerUsername === '' || $guestId < 1) {
        return ['ok' => false, 'message' => 'Invalid guest.'];
    }

    $stmt = arowin_db()->prepare(
        'UPDATE guest_list SET status = :s
         WHERE id = :id AND UPPER(owner_username) = :o
         LIMIT 1'
    );
    $stmt->execute([':s' => $status, ':id' => $guestId, ':o' => $ownerUsername]);
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Guest not found.'];
    }
    return ['ok' => true, 'message' => 'Guest updated.'];
}

function arowin_guest_format_date(?string $createdAt): string
{
    $createdAt = trim((string) $createdAt);
    if ($createdAt === '') {
        return '—';
    }
    try {
        return (new DateTimeImmutable($createdAt))->format('d M Y');
    } catch (Throwable $e) {
        return $createdAt;
    }
}
