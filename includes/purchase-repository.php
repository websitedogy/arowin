<?php
/**
 * Package purchase records (wallet buys activate instantly — admin is history only)
 */
declare(strict_types=1);

function arowin_ensure_purchases_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    arowin_db()->exec(
        "CREATE TABLE IF NOT EXISTS `package_purchases` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `package_code` ENUM('50','150','350','750','1550','3150','6350','12750') NOT NULL,
          `amount_usdt` INT UNSIGNED NOT NULL,
          `network` VARCHAR(20) NOT NULL DEFAULT 'TRC20',
          `tx_hash` VARCHAR(120) NOT NULL,
          `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
          `admin_note` VARCHAR(255) DEFAULT NULL,
          `reviewed_by` VARCHAR(60) DEFAULT NULL,
          `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_purchase_tx` (`tx_hash`),
          KEY `idx_purchase_user_status` (`username`, `status`),
          KEY `idx_purchase_status_created` (`status`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
}

function arowin_company_usdt_address(): string
{
    return trim((string) env('AROWIN_USDT_ADDRESS', ''));
}

function arowin_company_usdt_network(): string
{
    $net = strtoupper(trim((string) env('AROWIN_USDT_NETWORK', 'TRC20')));
    return $net !== '' ? $net : 'TRC20';
}

function arowin_normalize_tx_hash(string $hash): string
{
    $hash = trim($hash);
    $hash = preg_replace('/\s+/', '', $hash) ?? '';
    return $hash;
}

function arowin_is_valid_tx_hash(string $hash): bool
{
    // TRC20 / ERC20 style hashes (flexible for testing)
    return (bool) preg_match('/^[A-Za-z0-9]{10,120}$/', $hash);
}

/**
 * @return array<string, mixed>|null
 */
function arowin_user_pending_purchase(string $username): ?array
{
    arowin_ensure_purchases_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return null;
    }

    $stmt = arowin_db()->prepare(
        "SELECT * FROM package_purchases
         WHERE UPPER(username) = :u AND status = 'pending'
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmt->execute([':u' => $username]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * @return list<array<string, mixed>>
 */
function arowin_user_purchase_history(string $username, int $limit = 10): array
{
    arowin_ensure_purchases_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }

    $limit = max(1, min(50, $limit));
    $stmt = arowin_db()->prepare(
        "SELECT * FROM package_purchases
         WHERE UPPER(username) = :u
         ORDER BY id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([':u' => $username]);
    return $stmt->fetchAll() ?: [];
}

/**
 * @return array{ok:bool, message:string, id?:int}
 */
function arowin_submit_package_purchase(string $username, string $packageCode, string $txHash): array
{
    arowin_ensure_purchases_schema();

    $username = arowin_normalize_sponsor_id($username);
    $packageCode = arowin_normalize_package($packageCode);
    $txHash = arowin_normalize_tx_hash($txHash);

    if ($username === '') {
        return ['ok' => false, 'message' => 'Please sign in again.'];
    }
    if ($packageCode === '') {
        return ['ok' => false, 'message' => 'Select a valid package.'];
    }
    if (!arowin_is_valid_tx_hash($txHash)) {
        return ['ok' => false, 'message' => 'Enter a valid transaction hash / ID.'];
    }
    if (arowin_company_usdt_address() === '') {
        return ['ok' => false, 'message' => 'Company USDT wallet is not configured yet. Contact support.'];
    }

    $info = arowin_package_info($packageCode);
    if (!$info) {
        return ['ok' => false, 'message' => 'Unknown package.'];
    }

    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }

    // Compare against purchased package (active unit is always $50 after split)
    $current = arowin_normalize_package((string) ($user['purchased_package'] ?? $user['node_package'] ?? ''));
    $currentAmt = $current !== '' ? (int) (arowin_package_info($current)['amount'] ?? 0) : 0;
    $newAmt = (int) $info['amount'];

    if ($currentAmt > 0 && $newAmt <= $currentAmt) {
        return ['ok' => false, 'message' => 'Choose a higher package than your current purchased package.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot purchase packages. Use your main ID.'];
    }

    if (arowin_user_pending_purchase($username)) {
        return ['ok' => false, 'message' => 'You already have a pending purchase. Wait for review or contact support.'];
    }

    $dup = arowin_db()->prepare('SELECT id FROM package_purchases WHERE tx_hash = :h LIMIT 1');
    $dup->execute([':h' => $txHash]);
    if ($dup->fetch()) {
        return ['ok' => false, 'message' => 'This transaction hash was already submitted.'];
    }

    try {
        $stmt = arowin_db()->prepare(
            'INSERT INTO package_purchases
                (username, package_code, amount_usdt, network, tx_hash, status)
             VALUES
                (:u, :p, :a, :n, :h, \'pending\')'
        );
        $stmt->execute([
            ':u' => $username,
            ':p' => $packageCode,
            ':a' => $newAmt,
            ':n' => arowin_company_usdt_network(),
            ':h' => $txHash,
        ]);
        return [
            'ok' => true,
            'message' => 'Purchase submitted. Admin will activate your package after verifying the USDT transfer.',
            'id' => (int) arowin_db()->lastInsertId(),
        ];
    } catch (Throwable $e) {
        error_log('Purchase submit error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not submit purchase. Try again.'];
    }
}

/**
 * @return array{pending:int, approved:int, rejected:int, all:int}
 */
function arowin_purchase_status_counts(): array
{
    arowin_ensure_purchases_schema();
    $counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0, 'all' => 0];
    $stmt = arowin_db()->query(
        "SELECT status, COUNT(*) AS c FROM package_purchases GROUP BY status"
    );
    if ($stmt) {
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $status = (string) ($row['status'] ?? '');
            $n = (int) ($row['c'] ?? 0);
            if (isset($counts[$status])) {
                $counts[$status] = $n;
            }
            $counts['all'] += $n;
        }
    }
    return $counts;
}

/**
 * @return list<array<string, mixed>>
 */
function arowin_list_purchases(?string $status = 'pending', int $limit = 100): array
{
    arowin_ensure_purchases_schema();
    $limit = max(1, min(200, $limit));

    if ($status === null || $status === 'all') {
        $stmt = arowin_db()->query(
            "SELECT p.*, n.full_name
             FROM package_purchases p
             LEFT JOIN node_registrations n ON UPPER(n.username) = UPPER(p.username)
             ORDER BY FIELD(p.status,'pending','approved','rejected'), p.id DESC
             LIMIT {$limit}"
        );
        return $stmt ? ($stmt->fetchAll() ?: []) : [];
    }

    $stmt = arowin_db()->prepare(
        "SELECT p.*, n.full_name
         FROM package_purchases p
         LEFT JOIN node_registrations n ON UPPER(n.username) = UPPER(p.username)
         WHERE p.status = :s
         ORDER BY p.id DESC
         LIMIT {$limit}"
    );
    $stmt->execute([':s' => $status]);
    return $stmt->fetchAll() ?: [];
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_review_package_purchase(int $purchaseId, string $decision, string $adminUser, string $note = ''): array
{
    arowin_ensure_purchases_schema();
    $decision = strtolower(trim($decision));
    if (!in_array($decision, ['approved', 'rejected'], true)) {
        return ['ok' => false, 'message' => 'Invalid decision.'];
    }
    if ($purchaseId < 1) {
        return ['ok' => false, 'message' => 'Invalid purchase.'];
    }

    $pdo = arowin_db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM package_purchases WHERE id = :id LIMIT 1 FOR UPDATE');
        $stmt->execute([':id' => $purchaseId]);
        $row = $stmt->fetch();
        if (!$row) {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'Purchase not found.'];
        }
        if (($row['status'] ?? '') !== 'pending') {
            $pdo->rollBack();
            return ['ok' => false, 'message' => 'This purchase was already reviewed.'];
        }

        $upd = $pdo->prepare(
            'UPDATE package_purchases
             SET status = :s, admin_note = :n, reviewed_by = :a, reviewed_at = UTC_TIMESTAMP()
             WHERE id = :id AND status = \'pending\''
        );
        $upd->execute([
            ':s' => $decision,
            ':n' => $note !== '' ? mb_substr($note, 0, 255) : null,
            ':a' => mb_substr($adminUser, 0, 60),
            ':id' => $purchaseId,
        ]);

        $activateMsg = '';
        if ($decision === 'approved') {
            $pkg = arowin_normalize_package((string) $row['package_code']);
            if ($pkg === '') {
                $pdo->rollBack();
                return ['ok' => false, 'message' => 'Invalid package on purchase row.'];
            }

            // Commit purchase row first, then split/activate (power IDs need free queries)
            $pdo->commit();

            $result = arowin_activate_package_with_power_ids(
                (string) $row['username'],
                $pkg
            );
            if (empty($result['ok'])) {
                // Keep purchase approved but surface activation issue
                return [
                    'ok' => false,
                    'message' => 'Purchase marked approved, but activation failed: '
                        . (string) ($result['message'] ?? 'unknown error'),
                ];
            }
            $activateMsg = (string) $result['message'];
            return [
                'ok' => true,
                'message' => 'Purchase approved. ' . $activateMsg,
            ];
        }

        $pdo->commit();
        return [
            'ok' => true,
            'message' => 'Purchase rejected.',
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Purchase review error: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Review failed. Try again.'];
    }
}

function arowin_admin_env(string $key): string
{
    // Read raw .env values (avoid env() bool casting for passwords like "true")
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return '';
    }
    return trim((string) $value);
}

function arowin_admin_credentials_configured(): bool
{
    return arowin_admin_env('ADMIN_USER') !== ''
        && arowin_admin_env('ADMIN_PASSWORD') !== '';
}

function arowin_admin_authenticate(string $user, string $pass): bool
{
    $expectedUser = arowin_admin_env('ADMIN_USER');
    $expectedPass = arowin_admin_env('ADMIN_PASSWORD');
    if ($expectedUser === '' || $expectedPass === '') {
        return false;
    }
    return hash_equals($expectedUser, trim($user))
        && hash_equals($expectedPass, $pass);
}

function arowin_admin_login(string $user): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION['arowin_admin'] = true;
    $_SESSION['arowin_admin_user'] = $user;
    $_SESSION['arowin_admin_at'] = time();
}

function arowin_admin_logout(): void
{
    unset($_SESSION['arowin_admin'], $_SESSION['arowin_admin_user'], $_SESSION['arowin_admin_at']);
}

function arowin_is_admin_logged_in(): bool
{
    return !empty($_SESSION['arowin_admin']);
}

function arowin_admin_username(): string
{
    return (string) ($_SESSION['arowin_admin_user'] ?? 'admin');
}

// CSRF helpers live in includes/security.php (loaded by bootstrap)
