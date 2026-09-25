<?php
/**
 * Package → $50 unit split + auto power IDs on left/right legs
 *
 * Rules:
 *  - unit = $50
 *  - self always keeps 1 × $50 active
 *  - remaining units split equally: left (n-1)/2, right (n-1)/2
 *  - power IDs copy owner details, get new ARW IDs, package $50, status active
 *  - sponsor_id = binary_parent_id (tree parent), power_owner = package buyer
 *  - placement under each buyer leg:
 *      foreign/real member on that leg → only same-side child (never touch their opposite leg)
 *      buyer's own power-ID subtree → BFS left+right fill
 *    NOT extreme-left / extreme-right spillover (that is only for live registrations)
 */
declare(strict_types=1);

function arowin_ensure_power_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once __DIR__ . '/perf.php';
    }
    arowin_schema_once('package_power', 1, static function (): void {
    $pdo = arowin_db();
    $cols = $pdo->query('SHOW COLUMNS FROM node_registrations')->fetchAll(PDO::FETCH_COLUMN);
    $colSet = array_flip(array_map('strval', $cols ?: []));

    if (!isset($colSet['power_owner'])) {
        $pdo->exec(
            'ALTER TABLE `node_registrations`
             ADD COLUMN `power_owner` VARCHAR(60) NULL DEFAULT NULL AFTER `node_package`'
        );
    }
    if (!isset($colSet['purchased_package'])) {
        $pdo->exec(
            "ALTER TABLE `node_registrations`
             ADD COLUMN `purchased_package` ENUM(
               '50','150','350','750','1550','3150','6350','12750'
             ) NULL DEFAULT NULL AFTER `power_owner`"
        );
    }

    try {
        $pdo->exec(
            'CREATE INDEX `idx_reg_power_owner` ON `node_registrations` (`power_owner`)'
        );
    } catch (Throwable $e) {
        // index may already exist
    }
    });
}

/**
 * @return array{unit:int, units:int, self:int, left:int, right:int, amount:int}|null
 */
function arowin_package_unit_plan(string $packageCode): ?array
{
    $info = arowin_package_info($packageCode);
    if (!$info) {
        return null;
    }

    $unit = 50;
    $amount = (int) $info['amount'];
    if ($amount < $unit || $amount % $unit !== 0) {
        return null;
    }

    $units = intdiv($amount, $unit);
    $remaining = $units - 1;
    if ($remaining % 2 !== 0) {
        return null;
    }

    $side = intdiv($remaining, 2);
    return [
        'unit'   => $unit,
        'units'  => $units,
        'self'   => 1,
        'left'   => $side,
        'right'  => $side,
        'amount' => $amount,
    ];
}

/**
 * power_owner for a username (empty string if none / missing).
 */
function arowin_node_power_owner(string $username): string
{
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return '';
    }
    arowin_ensure_power_schema();
    $stmt = arowin_db()->prepare(
        'SELECT power_owner FROM node_registrations WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    return arowin_normalize_sponsor_id((string) ($stmt->fetchColumn() ?: ''));
}

/**
 * Power IDs sidebar/page: only the human who bought/picked a $150+ pack.
 * Hidden for $50, pending, and Power ID logins.
 */
function arowin_user_can_view_power_ids(?array $user): bool
{
    if (!$user || !empty($user['power_owner'])) {
        return false;
    }
    $code = '';
    if (function_exists('arowin_normalize_package')) {
        $code = arowin_normalize_package((string) ($user['purchased_package'] ?? ''));
    } else {
        $code = trim((string) ($user['purchased_package'] ?? ''));
    }
    if ($code === '' || $code === '50') {
        return false;
    }
    $amount = 0;
    if (function_exists('arowin_package_info')) {
        $info = arowin_package_info($code);
        $amount = (int) ($info['amount'] ?? 0);
    } elseif (ctype_digit($code)) {
        $amount = (int) $code;
    }
    return $amount >= 150;
}

function arowin_count_owned_power_ids(string $owner): int
{
    $owner = arowin_normalize_sponsor_id($owner);
    if ($owner === '') {
        return 0;
    }
    arowin_ensure_power_schema();
    try {
        $stmt = arowin_db()->prepare(
            'SELECT COUNT(*) FROM node_registrations WHERE UPPER(power_owner) = :o'
        );
        $stmt->execute([':o' => $owner]);
        return (int) $stmt->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function arowin_is_owned_power_id(string $username, string $ownerId): bool
{
    $ownerId = arowin_normalize_sponsor_id($ownerId);
    return $ownerId !== '' && arowin_node_power_owner($username) === $ownerId;
}

/**
 * First empty slot inside a subtree using BFS (left before right at each node).
 *
 * @return array{parent:string,leg:string}|null
 */
function arowin_bfs_first_empty_in_subtree(string $startId): ?array
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $startId = arowin_normalize_sponsor_id($startId);
    if ($startId === '' || !getUserByUserId($startId)) {
        return null;
    }

    $queue = [$startId];
    $guard = 0;
    while ($queue !== [] && $guard < 1024) {
        $guard++;
        $node = array_shift($queue);
        if ($node === null || $node === '') {
            continue;
        }
        foreach (['left', 'right'] as $side) {
            $child = getBinaryTreeChild($node, $side);
            if ($child === null) {
                return ['parent' => $node, 'leg' => $side];
            }
            $queue[] = $child;
        }
    }

    return null;
}

/**
 * Next empty slot under buyer's chosen leg for power-ID placement.
 *
 * Example — buyer left already has foreign member Pogula:
 *   1) Pogula.left (never Pogula.right)
 *   2) that power ID's left
 *   3) that power ID's right
 *   …
 *
 * If leg head is buyer's own power ID (or empty), BFS left+right under that head.
 *
 * @return array{parent:string,leg:string}|null
 */
function arowin_find_next_bfs_slot_under_leg(string $rootId, string $leg, ?string $ownerId = null): ?array
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $rootId = arowin_normalize_sponsor_id($rootId);
    $ownerId = arowin_normalize_sponsor_id($ownerId ?? $rootId);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($rootId === '' || $ownerId === '' || !getUserByUserId($rootId)) {
        return null;
    }

    $direct = getBinaryTreeChild($rootId, $leg);
    if ($direct === null) {
        return ['parent' => $rootId, 'leg' => $leg];
    }

    // Walk same-side only through foreign/real members; BFS once inside owned power IDs.
    $current = $direct;
    $guard = 0;
    while ($current !== null && $current !== '' && $guard < 128) {
        $guard++;

        if (arowin_is_owned_power_id($current, $ownerId)) {
            return arowin_bfs_first_empty_in_subtree($current);
        }

        $sameSide = getBinaryTreeChild($current, $leg);
        if ($sameSide === null) {
            // Foreign node: only place on the same leg side (e.g. Pogula.left).
            return ['parent' => $current, 'leg' => $leg];
        }
        $current = $sameSide;
    }

    return null;
}

/**
 * Count power IDs owned by $owner that sit under owner's left/right leg.
 */
function arowin_count_power_ids_on_leg(string $owner, string $leg): int
{
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    arowin_ensure_power_schema();
    $owner = arowin_normalize_sponsor_id($owner);
    $leg = $leg === 'right' ? 'right' : 'left';
    if ($owner === '') {
        return 0;
    }

    $child = getBinaryTreeChild($owner, $leg);
    if ($child === null) {
        return 0;
    }

    $count = 0;
    $queue = [$child];
    $guard = 0;
    while ($queue !== [] && $guard < 1024) {
        $guard++;
        $uid = array_shift($queue);
        if ($uid === null) {
            continue;
        }
        if (!getUserByUserId($uid)) {
            continue;
        }
        if (arowin_is_owned_power_id($uid, $owner)) {
            $count++;
        }
        $left = getBinaryTreeChild($uid, 'left');
        $right = getBinaryTreeChild($uid, 'right');
        if ($left !== null) {
            $queue[] = $left;
        }
        if ($right !== null) {
            $queue[] = $right;
        }
    }

    return $count;
}

/**
 * @return array{id:int,username:string,full_name:string,email:?string,phone:?string,password_hash:string,sponsor_id:?string,usdt_wallet:?string,country:?string,purchased_package:?string,node_package:?string}|null
 */
function arowin_get_user_row_for_power(string $username): ?array
{
    arowin_ensure_power_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }

    $stmt = arowin_db()->prepare(
        'SELECT id, username, full_name, email, phone, password_hash, sponsor_id,
                usdt_wallet, country, node_package, purchased_package, power_owner, status
         FROM node_registrations
         WHERE UPPER(username) = :u
         LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return array{ok:bool, message:string, created_left:int, created_right:int, plan?:array}
 */
function arowin_activate_package_with_power_ids(string $username, string $purchasedPackage): array
{
    arowin_ensure_power_schema();
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $username = arowin_normalize_sponsor_id($username);
    $purchasedPackage = arowin_normalize_package($purchasedPackage);
    $plan = arowin_package_unit_plan($purchasedPackage);
    if ($username === '' || $plan === null) {
        return ['ok' => false, 'message' => 'Invalid package activation plan.', 'created_left' => 0, 'created_right' => 0];
    }

    $owner = arowin_get_user_row_for_power($username);
    if (!$owner) {
        return ['ok' => false, 'message' => 'User not found for activation.', 'created_left' => 0, 'created_right' => 0];
    }
    if (!empty($owner['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot purchase packages.', 'created_left' => 0, 'created_right' => 0];
    }

    $existingPurchased = arowin_normalize_package((string) ($owner['purchased_package'] ?? ''));
    $existingAmt = $existingPurchased !== '' ? (int) (arowin_package_info($existingPurchased)['amount'] ?? 0) : 0;
    $newAmt = (int) $plan['amount'];
    if ($existingAmt > 0 && $newAmt < $existingAmt) {
        return ['ok' => false, 'message' => 'Cannot activate a lower package than already purchased.', 'created_left' => 0, 'created_right' => 0];
    }

    $needLeft = max(0, (int) $plan['left'] - arowin_count_power_ids_on_leg($username, 'left'));
    $needRight = max(0, (int) $plan['right'] - arowin_count_power_ids_on_leg($username, 'right'));

    $pdo = arowin_db();
    $createdLeft = 0;
    $createdRight = 0;
    $ownTx = !$pdo->inTransaction();

    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        // Activate owner as $50 unit + store purchased package
        // ENUM binds must be strings (MySQL treats integers as enum indexes).
        $act = $pdo->prepare(
            "UPDATE node_registrations
             SET node_package = :unit,
                 purchased_package = :pp,
                 status = 'active',
                 power_owner = NULL
             WHERE UPPER(username) = :u
             LIMIT 1"
        );
        $act->bindValue(':unit', '50', PDO::PARAM_STR);
        $act->bindValue(':pp', $purchasedPackage, PDO::PARAM_STR);
        $act->bindValue(':u', $username, PDO::PARAM_STR);
        $act->execute();
        if ($act->rowCount() < 1) {
            if ($ownTx && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'message' => 'Could not activate owner package.', 'created_left' => 0, 'created_right' => 0];
        }

        $insert = $pdo->prepare(
            'INSERT INTO node_registrations
                (full_name, email, phone, username, password_hash, sponsor_id, preferred_leg,
                 node_package, purchased_package, power_owner, usdt_wallet, country, consent, status,
                 binary_parent_id, binary_placement)
             VALUES
                (:full_name, NULL, :phone, :username, :password_hash, :sponsor_id, :preferred_leg,
                 \'50\', NULL, :power_owner, :usdt_wallet, :country, 1, \'active\',
                 :binary_parent_id, :binary_placement)'
        );

        $createSide = static function (string $side, int $need) use (
            &$createdLeft,
            &$createdRight,
            $insert,
            $owner,
            $username
        ): void {
            for ($i = 0; $i < $need; $i++) {
                $slot = arowin_find_next_bfs_slot_under_leg($username, $side, $username);
                if ($slot === null) {
                    throw new RuntimeException('No binary slot available on ' . $side . ' leg.');
                }

                $newId = arowin_generate_user_id();

                $insert->execute([
                    ':full_name'         => (string) $owner['full_name'],
                    ':phone'             => (string) ($owner['phone'] ?? ''),
                    ':username'          => $newId,
                    ':password_hash'     => (string) $owner['password_hash'],
                    // Sponsor follows binary parent (tree placement), not always the buyer.
                    ':sponsor_id'        => $slot['parent'],
                    ':preferred_leg'     => $slot['leg'],
                    ':power_owner'       => $username,
                    ':usdt_wallet'       => $owner['usdt_wallet'] ?: null,
                    ':country'           => $owner['country'] ?: null,
                    ':binary_parent_id'  => $slot['parent'],
                    ':binary_placement'  => $slot['leg'],
                ]);

                if ($side === 'left') {
                    $createdLeft++;
                } else {
                    $createdRight++;
                }
            }
        };

        $createSide('left', $needLeft);
        $createSide('right', $needRight);

        if ($ownTx) {
            $pdo->commit();
        }
        if (function_exists('invalidateBinaryTreeIndex')) {
            invalidateBinaryTreeIndex();
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Power ID activation error: ' . $e->getMessage());
        return [
            'ok' => false,
            'message' => 'Activation failed: ' . $e->getMessage(),
            'created_left' => $createdLeft,
            'created_right' => $createdRight,
            'plan' => $plan,
        ];
    }

    $incomeMsg = '';
    if (!function_exists('arowin_process_activation_income')) {
        require_once __DIR__ . '/income-repository.php';
    }
    if (function_exists('arowin_process_activation_income')) {
        try {
            $income = arowin_process_activation_income($username, $purchasedPackage);
            if (!empty($income['message'])) {
                $incomeMsg = ' ' . (string) $income['message'];
            }
        } catch (Throwable $e) {
            error_log('Activation income error: ' . $e->getMessage());
            $incomeMsg = ' Income processing deferred.';
        }
    }

    // Pure $50 → enroll $0.25/day × 400; higher packages stop any prior yield.
    if (!function_exists('arowin_daily_yield_enroll')) {
        require_once __DIR__ . '/daily-yield-repository.php';
    }
    if (function_exists('arowin_daily_yield_enroll')) {
        try {
            if (arowin_daily_yield_is_pure_fifty($purchasedPackage)) {
                arowin_daily_yield_enroll($username);
                arowin_daily_yield_process_user($username);
            } else {
                arowin_daily_yield_stop_if_upgraded($username, $purchasedPackage);
            }
        } catch (Throwable $e) {
            error_log('Daily yield hook error: ' . $e->getMessage());
        }
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Activated $%s → self $50 + %d left + %d right power IDs (created L:%d R:%d).%s',
            number_format($plan['amount']),
            $plan['left'],
            $plan['right'],
            $createdLeft,
            $createdRight,
            $incomeMsg
        ),
        'created_left' => $createdLeft,
        'created_right' => $createdRight,
        'plan' => $plan,
    ];
}

/**
 * Re-seat existing power IDs under owner using BFS (fixes extreme-chain mistakes).
 *
 * @return array{ok:bool, message:string, left:int, right:int}
 */
function arowin_reposition_power_ids_bfs(string $username): array
{
    arowin_ensure_power_schema();
    if (!function_exists('getBinaryTreeChild')) {
        require_once __DIR__ . '/genealogy-helper.php';
    }

    $username = arowin_normalize_sponsor_id($username);
    $owner = arowin_get_user_row_for_power($username);
    if (!$owner) {
        return ['ok' => false, 'message' => 'User not found.', 'left' => 0, 'right' => 0];
    }
    if (!empty($owner['power_owner'])) {
        return ['ok' => false, 'message' => 'Target is a power ID.', 'left' => 0, 'right' => 0];
    }

    $purchased = arowin_normalize_package((string) ($owner['purchased_package'] ?? ''));
    if ($purchased === '') {
        $purchased = arowin_normalize_package((string) ($owner['node_package'] ?? ''));
    }
    $plan = arowin_package_unit_plan($purchased !== '' ? $purchased : '50');
    if ($plan === null) {
        return ['ok' => false, 'message' => 'No package plan for reposition.', 'left' => 0, 'right' => 0];
    }

    $pdo = arowin_db();
    $stmt = $pdo->prepare(
        'SELECT username FROM node_registrations
         WHERE UPPER(power_owner) = :u
         ORDER BY id ASC'
    );
    $stmt->execute([':u' => $username]);
    $powerIds = array_values(array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []));
    if ($powerIds === []) {
        return ['ok' => true, 'message' => 'No power IDs to reposition.', 'left' => 0, 'right' => 0];
    }

    $leftNeed = (int) $plan['left'];
    $rightNeed = (int) $plan['right'];

    $ownTx = !$pdo->inTransaction();
    try {
        if ($ownTx) {
            $pdo->beginTransaction();
        }

        // Detach all power IDs from the binary tree first
        $clear = $pdo->prepare(
            'UPDATE node_registrations
             SET binary_parent_id = NULL, binary_placement = NULL
             WHERE UPPER(username) = :u
             LIMIT 1'
        );
        foreach ($powerIds as $pid) {
            $clear->execute([':u' => arowin_normalize_sponsor_id($pid)]);
        }

        $place = $pdo->prepare(
            'UPDATE node_registrations
             SET binary_parent_id = :p,
                 binary_placement = :leg,
                 preferred_leg = :pref,
                 sponsor_id = :p
             WHERE UPPER(username) = :u
             LIMIT 1'
        );

        $placedLeft = 0;
        $placedRight = 0;
        $index = 0;
        $total = count($powerIds);

        for ($i = 0; $i < $leftNeed && $index < $total; $i++, $index++) {
            $slot = arowin_find_next_bfs_slot_under_leg($username, 'left', $username);
            if ($slot === null) {
                throw new RuntimeException('No BFS left slot for ' . $powerIds[$index]);
            }
            $place->execute([
                ':p'    => $slot['parent'],
                ':leg'  => $slot['leg'],
                ':pref' => 'left',
                ':u'    => arowin_normalize_sponsor_id($powerIds[$index]),
            ]);
            $placedLeft++;
        }

        for ($i = 0; $i < $rightNeed && $index < $total; $i++, $index++) {
            $slot = arowin_find_next_bfs_slot_under_leg($username, 'right', $username);
            if ($slot === null) {
                throw new RuntimeException('No BFS right slot for ' . $powerIds[$index]);
            }
            $place->execute([
                ':p'    => $slot['parent'],
                ':leg'  => $slot['leg'],
                ':pref' => 'right',
                ':u'    => arowin_normalize_sponsor_id($powerIds[$index]),
            ]);
            $placedRight++;
        }

        // Any extras (edge cases) continue BFS left then right
        while ($index < $total) {
            $side = ($index - $placedLeft - $placedRight) % 2 === 0 ? 'left' : 'right';
            $slot = arowin_find_next_bfs_slot_under_leg($username, $side, $username);
            if ($slot === null) {
                $slot = arowin_find_next_bfs_slot_under_leg(
                    $username,
                    $side === 'left' ? 'right' : 'left',
                    $username
                );
            }
            if ($slot === null) {
                throw new RuntimeException('No BFS slot for extra power ID ' . $powerIds[$index]);
            }
            $place->execute([
                ':p'    => $slot['parent'],
                ':leg'  => $slot['leg'],
                ':pref' => $side,
                ':u'    => arowin_normalize_sponsor_id($powerIds[$index]),
            ]);
            if ($side === 'left') {
                $placedLeft++;
            } else {
                $placedRight++;
            }
            $index++;
        }

        if ($ownTx) {
            $pdo->commit();
        }
        if (function_exists('invalidateBinaryTreeIndex')) {
            invalidateBinaryTreeIndex();
        }
    } catch (Throwable $e) {
        if ($ownTx && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Power ID BFS reposition error: ' . $e->getMessage());
        return [
            'ok' => false,
            'message' => 'BFS reposition failed: ' . $e->getMessage(),
            'left' => 0,
            'right' => 0,
        ];
    }

    return [
        'ok' => true,
        'message' => sprintf(
            'Repositioned power IDs with BFS under %s (L:%d R:%d).',
            $username,
            $placedLeft,
            $placedRight
        ),
        'left' => $placedLeft,
        'right' => $placedRight,
    ];
}

/**
 * Sync power-ID sponsor_id to binary_parent_id (tree parent).
 *
 * @return array{ok:bool, updated:int, message:string}
 */
function arowin_sync_power_id_sponsors(?string $ownerUsername = null): array
{
    arowin_ensure_power_schema();
    $pdo = arowin_db();

    $sql = "UPDATE node_registrations
            SET sponsor_id = binary_parent_id,
                preferred_leg = COALESCE(binary_placement, preferred_leg)
            WHERE power_owner IS NOT NULL
              AND power_owner <> ''
              AND binary_parent_id IS NOT NULL
              AND binary_parent_id <> ''
              AND (
                    sponsor_id IS NULL
                    OR UPPER(sponsor_id) <> UPPER(binary_parent_id)
                    OR preferred_leg IS NULL
                    OR preferred_leg = ''
                    OR (binary_placement IS NOT NULL AND preferred_leg <> binary_placement)
                  )";
    $params = [];
    if ($ownerUsername !== null && trim($ownerUsername) !== '') {
        $sql .= ' AND UPPER(power_owner) = :o';
        $params[':o'] = arowin_normalize_sponsor_id($ownerUsername);
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $n = $stmt->rowCount();

    return [
        'ok' => true,
        'updated' => $n,
        'message' => 'Synced sponsor_id → binary_parent_id for ' . $n . ' power ID(s).',
    ];
}
