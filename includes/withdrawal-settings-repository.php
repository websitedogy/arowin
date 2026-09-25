<?php
/**
 * Withdrawal settings — password + saved payout addresses
 */
declare(strict_types=1);

require_once __DIR__ . '/crypto-helpers.php';

function arowin_ensure_withdrawal_settings_schema(): void
{
    if (!function_exists('arowin_schema_once')) {
        require_once __DIR__ . '/perf.php';
    }
    arowin_schema_once('withdrawal_settings', 1, static function (): void {
    $pdo = arowin_db();

    // Withdrawal password on node_registrations
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM node_registrations')->fetchAll(PDO::FETCH_COLUMN);
        $colSet = array_flip(array_map('strval', $cols ?: []));
        if (!isset($colSet['withdrawal_password_hash'])) {
            $pdo->exec(
                "ALTER TABLE `node_registrations`
                 ADD COLUMN `withdrawal_password_hash` VARCHAR(255) DEFAULT NULL
                 AFTER `password_hash`"
            );
        }
    } catch (Throwable $e) {
        // password_hash column may have different name — try without AFTER
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM node_registrations')->fetchAll(PDO::FETCH_COLUMN);
            $colSet = array_flip(array_map('strval', $cols ?: []));
            if (!isset($colSet['withdrawal_password_hash'])) {
                $pdo->exec(
                    'ALTER TABLE `node_registrations`
                     ADD COLUMN `withdrawal_password_hash` VARCHAR(255) DEFAULT NULL'
                );
            }
        } catch (Throwable $e2) {
            error_log('withdrawal_password_hash migrate: ' . $e2->getMessage());
        }
    }

    // Withdrawal address permission on node_registrations
    try {
        $cols = $pdo->query('SHOW COLUMNS FROM node_registrations')->fetchAll(PDO::FETCH_COLUMN);
        $colSet = array_flip(array_map('strval', $cols ?: []));
        if (!isset($colSet['can_add_withdrawal_address'])) {
            $pdo->exec(
                "ALTER TABLE `node_registrations`
                 ADD COLUMN `can_add_withdrawal_address` TINYINT(1) NOT NULL DEFAULT 1
                 AFTER `withdrawal_password_hash`"
            );
        }
    } catch (Throwable $e) {
        error_log('can_add_withdrawal_address migrate: ' . $e->getMessage());
    }

    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS `withdrawal_addresses` (
          `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
          `username` VARCHAR(60) NOT NULL,
          `label` VARCHAR(80) NOT NULL DEFAULT '',
          `address` VARCHAR(128) NOT NULL,
          `currency` VARCHAR(32) NOT NULL DEFAULT 'usdtbsc',
          `is_default` TINYINT(1) NOT NULL DEFAULT 0,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_wa_user` (`username`),
          KEY `idx_wa_user_addr` (`username`, `address`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    });
}

/**
 * Check if user can add withdrawal addresses
 */
function arowin_can_add_withdrawal_address(string $username): bool
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return false;
    }
    $stmt = arowin_db()->prepare(
        'SELECT can_add_withdrawal_address FROM node_registrations
         WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $value = $stmt->fetchColumn();
    return (bool) $value;
}

/**
 * Set withdrawal address permission for a user
 */
function arowin_set_withdrawal_address_permission(string $username, bool $allow): array
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user.'];
    }
    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations SET can_add_withdrawal_address = :allow
         WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':allow' => $allow ? 1 : 0, ':u' => $username]);
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'User not found.'];
    }
    return [
        'ok' => true,
        'message' => $allow ? 'Withdrawal address adding enabled.' : 'Withdrawal address adding disabled.',
    ];
}

/**
 * Get list of users with disabled withdrawal address permission
 */
function arowin_get_users_with_disabled_withdrawal_address(): array
{
    arowin_ensure_withdrawal_settings_schema();
    $stmt = arowin_db()->prepare(
        'SELECT username, full_name, email, phone, created_at
         FROM node_registrations
         WHERE can_add_withdrawal_address = 0
         ORDER BY created_at DESC
         LIMIT 100'
    );
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * Admin function to add withdrawal address for a user (bypasses permission check)
 */
function arowin_admin_add_withdrawal_address(
    string $adminUsername,
    string $targetUsername,
    string $address,
    string $label = '',
    string $currency = 'usdtbsc',
    bool $makeDefault = false
): array {
    arowin_ensure_withdrawal_settings_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $targetUsername = arowin_normalize_sponsor_id($targetUsername);
    $address = trim($address);
    $label = trim($label);
    $cfg = arowin_nowpayments_config();
    $currency = strtolower(trim($currency !== '' ? $currency : (string) ($cfg['payout_currency'] ?? '')));
    if ($currency === '') {
        $currency = arowin_payout_currency_for_address($address, 'usdtbsc');
    }
    $currency = arowin_normalize_payout_currency($currency, 'usdtbsc');
    if ($targetUsername === '') {
        return ['ok' => false, 'message' => 'Invalid target user.'];
    }
    if ($label === '') {
        $label = 'Wallet ' . (count(arowin_withdrawal_addresses_list($targetUsername)) + 1);
    }
    if (mb_strlen($label) > 80) {
        $label = mb_substr($label, 0, 80);
    }

    $check = arowin_validate_crypto_address($address, $currency);
    if (empty($check['ok'])) {
        return ['ok' => false, 'message' => (string) ($check['message'] ?? 'Invalid address.')];
    }

    $dup = arowin_db()->prepare(
        'SELECT id FROM withdrawal_addresses
         WHERE UPPER(username) = :u AND address = :a LIMIT 1'
    );
    $dup->execute([':u' => $targetUsername, ':a' => $address]);
    if ($dup->fetchColumn()) {
        return ['ok' => false, 'message' => 'This address is already saved for this user.'];
    }

    $existing = arowin_withdrawal_addresses_list($targetUsername);
    if (count($existing) >= 20) {
        return ['ok' => false, 'message' => 'Maximum 20 withdrawal addresses per user.'];
    }
    if ($existing === []) {
        $makeDefault = true;
    }

    $pdo = arowin_db();
    if ($makeDefault) {
        $pdo->prepare(
            'UPDATE withdrawal_addresses SET is_default = 0 WHERE UPPER(username) = :u'
        )->execute([':u' => $targetUsername]);
    }

    $ins = $pdo->prepare(
        'INSERT INTO withdrawal_addresses (username, label, address, currency, is_default)
         VALUES (:u, :l, :a, :c, :d)'
    );
    $ins->execute([
        ':u' => $targetUsername,
        ':l' => $label,
        ':a' => $address,
        ':c' => $currency,
        ':d' => $makeDefault ? 1 : 0,
    ]);

    return [
        'ok' => true,
        'id' => (int) $pdo->lastInsertId(),
        'message' => 'Withdrawal address added for ' . $targetUsername . '.',
    ];
}

/**
 * @return array{has_password:bool, addresses:list<array<string,mixed>>, count:int}
 */
function arowin_withdrawal_settings(string $username): array
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    $has = false;
    if ($username !== '') {
        $stmt = arowin_db()->prepare(
            'SELECT withdrawal_password_hash FROM node_registrations
             WHERE UPPER(username) = :u LIMIT 1'
        );
        $stmt->execute([':u' => $username]);
        $hash = (string) ($stmt->fetchColumn() ?: '');
        $has = $hash !== '';
    }
    $addresses = arowin_withdrawal_addresses_list($username);
    return [
        'has_password' => $has,
        'addresses' => $addresses,
        'count' => count($addresses),
    ];
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_password_set(
    string $username,
    string $password,
    string $confirm = '',
    string $oldPassword = ''
): array {
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    $password = trim($password);
    $confirm = trim($confirm);
    $oldPassword = trim($oldPassword);

    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }
    if (strlen($password) < 6) {
        return ['ok' => false, 'message' => 'Withdrawal password must be at least 6 characters.'];
    }
    if ($confirm !== '' && $password !== $confirm) {
        return ['ok' => false, 'message' => 'Passwords do not match.'];
    }

    $settings = arowin_withdrawal_settings($username);
    if (!empty($settings['has_password'])) {
        if ($oldPassword === '') {
            return ['ok' => false, 'message' => 'Enter your current withdrawal password.'];
        }
        if (!arowin_withdrawal_password_verify($username, $oldPassword)) {
            return ['ok' => false, 'message' => 'Current withdrawal password is incorrect.'];
        }
        if ($oldPassword === $password) {
            return ['ok' => false, 'message' => 'New password must be different from the current one.'];
        }
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = arowin_db()->prepare(
        'UPDATE node_registrations SET withdrawal_password_hash = :h
         WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':h' => $hash, ':u' => $username]);
    if ($stmt->rowCount() < 1) {
        $user = arowin_get_user_by_username($username);
        if (!$user) {
            return ['ok' => false, 'message' => 'Account not found.'];
        }
    }
    return [
        'ok' => true,
        'message' => !empty($settings['has_password'])
            ? 'Withdrawal password updated.'
            : 'Withdrawal password saved.',
    ];
}

function arowin_withdrawal_password_verify(string $username, string $password): bool
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || $password === '') {
        return false;
    }
    $stmt = arowin_db()->prepare(
        'SELECT withdrawal_password_hash FROM node_registrations
         WHERE UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':u' => $username]);
    $hash = (string) ($stmt->fetchColumn() ?: '');
    if ($hash === '') {
        return false;
    }
    return password_verify($password, $hash);
}

/**
 * Verify a Firebase Phone Auth ID token and ensure it belongs to this user.
 */
function arowin_firebase_verify_withdrawal_token(string $token, string $username): array
{
    $token = trim($token);
    $username = arowin_normalize_sponsor_id($username);
    $projectId = trim((string) env('FIREBASE_PROJECT_ID', ''));
    if ($token === '' || $username === '' || $projectId === '') {
        return ['ok' => false, 'message' => 'SMS verification is not configured.'];
    }

    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return ['ok' => false, 'message' => 'Invalid or expired OTP verification.'];
    }
    $decode = static function (string $value): ?array {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
        $json = is_string($decoded) ? json_decode($decoded, true) : null;
        return is_array($json) ? $json : null;
    };
    $header = $decode($parts[0]);
    $claims = $decode($parts[1]);
    $signature = base64_decode(strtr($parts[2], '-_', '+/') . str_repeat('=', (4 - strlen($parts[2]) % 4) % 4), true);
    $kid = (string) ($header['kid'] ?? '');
    if (!$header || !$claims || $kid === '' || !is_string($signature)) {
        return ['ok' => false, 'message' => 'Invalid or expired OTP verification.'];
    }

    static $certs = null;
    static $certsAt = 0;
    if (!is_array($certs) || $certsAt < time()) {
        $certUrl = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
        $json = @file_get_contents($certUrl);
        if (!is_string($json) && function_exists('curl_init')) {
            $curl = curl_init($certUrl);
            curl_setopt_array($curl, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 10,
            ]);
            $json = curl_exec($curl);
            curl_close($curl);
        }
        $certs = is_string($json) ? json_decode($json, true) : null;
        $certsAt = time() + 300;
    }
    $cert = is_array($certs) ? (string) ($certs[$kid] ?? '') : '';
    if ($cert === '' || !openssl_verify($parts[0] . '.' . $parts[1], $signature, $cert, OPENSSL_ALGO_SHA256)) {
        return ['ok' => false, 'message' => 'Invalid or expired OTP verification.'];
    }

    $now = time();
    $issuer = 'https://securetoken.google.com/' . $projectId;
    if ((string) ($claims['aud'] ?? '') !== $projectId
        || (string) ($claims['iss'] ?? '') !== $issuer
        || empty($claims['sub'])
        || (int) ($claims['exp'] ?? 0) <= $now
        || (int) ($claims['iat'] ?? 0) > $now + 60
        || empty($claims['phone_number'])) {
        return ['ok' => false, 'message' => 'Invalid or expired OTP verification.'];
    }

    $user = arowin_get_user_by_username($username);
    $userPhone = preg_replace('/\D+/', '', (string) ($user['phone'] ?? ''));
    $tokenPhone = preg_replace('/\D+/', '', (string) $claims['phone_number']);
    if (!$user || $userPhone === '' || $tokenPhone === '' || substr($userPhone, -10) !== substr($tokenPhone, -10)) {
        return ['ok' => false, 'message' => 'OTP mobile number does not match this account.'];
    }

    return ['ok' => true, 'message' => 'OTP verified.'];
}

/**
 * @return list<array<string,mixed>>
 */
function arowin_withdrawal_addresses_list(string $username): array
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '') {
        return [];
    }
    $stmt = arowin_db()->prepare(
        'SELECT id, username, label, address, currency, is_default, created_at
         FROM withdrawal_addresses
         WHERE UPPER(username) = :u
         ORDER BY is_default DESC, id DESC
         LIMIT 50'
    );
    $stmt->execute([':u' => $username]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array{ok:bool, message:string, id?:int}
 */
function arowin_withdrawal_address_add(
    string $username,
    string $address,
    string $label = '',
    string $currency = 'usdtbsc',
    bool $makeDefault = false
): array {
    arowin_ensure_withdrawal_settings_schema();
    require_once __DIR__ . '/nowpayments-service.php';

    $username = arowin_normalize_sponsor_id($username);
    $address = trim($address);
    $label = trim($label);
    $cfg = arowin_nowpayments_config();
    $currency = strtolower(trim($currency !== '' ? $currency : (string) ($cfg['payout_currency'] ?? 'usdtbsc')));
    $currency = arowin_normalize_payout_currency($currency, 'usdtbsc');

    if ($username === '') {
        return ['ok' => false, 'message' => 'Not signed in.'];
    }

    // Check if user has permission to add withdrawal addresses
    if (!arowin_can_add_withdrawal_address($username)) {
        return ['ok' => false, 'message' => 'Please contact admin to add withdrawal addresses.'];
    }
    if ($label === '') {
        $label = 'Wallet ' . (count(arowin_withdrawal_addresses_list($username)) + 1);
    }
    if (mb_strlen($label) > 80) {
        $label = mb_substr($label, 0, 80);
    }

    $check = arowin_validate_crypto_address($address, $currency);
    if (empty($check['ok'])) {
        return ['ok' => false, 'message' => (string) ($check['message'] ?? 'Invalid address.')];
    }

    $dup = arowin_db()->prepare(
        'SELECT id FROM withdrawal_addresses
         WHERE UPPER(username) = :u AND address = :a LIMIT 1'
    );
    $dup->execute([':u' => $username, ':a' => $address]);
    if ($dup->fetchColumn()) {
        return ['ok' => false, 'message' => 'This address is already saved.'];
    }

    $existing = arowin_withdrawal_addresses_list($username);
    if (count($existing) >= 20) {
        return ['ok' => false, 'message' => 'Maximum 20 withdrawal addresses.'];
    }
    if ($existing === []) {
        $makeDefault = true;
    }

    $pdo = arowin_db();
    if ($makeDefault) {
        $pdo->prepare(
            'UPDATE withdrawal_addresses SET is_default = 0 WHERE UPPER(username) = :u'
        )->execute([':u' => $username]);
    }

    $ins = $pdo->prepare(
        'INSERT INTO withdrawal_addresses (username, label, address, currency, is_default)
         VALUES (:u, :l, :a, :c, :d)'
    );
    $ins->execute([
        ':u' => $username,
        ':l' => $label,
        ':a' => $address,
        ':c' => $currency,
        ':d' => $makeDefault ? 1 : 0,
    ]);

    return [
        'ok' => true,
        'id' => (int) $pdo->lastInsertId(),
        'message' => 'Withdrawal address saved.',
    ];
}

/**
 * Update one of the signed-in user's withdrawal addresses.
 *
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_address_update(
    string $username,
    int $id,
    string $address,
    string $label = '',
    string $currency = 'usdtbsc'
): array {
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    $address = trim($address);
    $label = trim($label);
    $currency = arowin_normalize_payout_currency($currency, 'usdtbsc');

    if ($username === '' || $id < 1) {
        return ['ok' => false, 'message' => 'Invalid address.'];
    }
    if (!arowin_can_add_withdrawal_address($username)) {
        return ['ok' => false, 'message' => 'Please contact admin to manage withdrawal addresses.'];
    }
    if ($label === '') {
        $label = 'Wallet';
    }
    $label = mb_substr($label, 0, 80);

    $check = arowin_validate_crypto_address($address, $currency);
    if (empty($check['ok'])) {
        return ['ok' => false, 'message' => (string) ($check['message'] ?? 'Invalid address.')];
    }

    $existing = arowin_withdrawal_address_get($username, $id);
    if ($existing === null) {
        return ['ok' => false, 'message' => 'Address not found.'];
    }

    $dup = arowin_db()->prepare(
        'SELECT id FROM withdrawal_addresses
         WHERE UPPER(username) = :u AND address = :a AND id <> :id LIMIT 1'
    );
    $dup->execute([':u' => $username, ':a' => $address, ':id' => $id]);
    if ($dup->fetchColumn()) {
        return ['ok' => false, 'message' => 'This address is already saved.'];
    }

    $stmt = arowin_db()->prepare(
        'UPDATE withdrawal_addresses
         SET label = :l, address = :a, currency = :c
         WHERE id = :id AND UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([
        ':l' => $label,
        ':a' => $address,
        ':c' => $currency,
        ':id' => $id,
        ':u' => $username,
    ]);

    return ['ok' => true, 'message' => 'Withdrawal address updated.'];
}

/**
 * @return array{ok:bool, message:string}
 */
function arowin_withdrawal_address_delete(string $username, int $id): array
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || $id < 1) {
        return ['ok' => false, 'message' => 'Invalid address.'];
    }
    $stmt = arowin_db()->prepare(
        'DELETE FROM withdrawal_addresses
         WHERE id = :id AND UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':u' => $username]);
    if ($stmt->rowCount() < 1) {
        return ['ok' => false, 'message' => 'Address not found.'];
    }
    return ['ok' => true, 'message' => 'Address removed.'];
}

/**
 * @return array<string,mixed>|null
 */
function arowin_withdrawal_address_get(string $username, int $id): ?array
{
    arowin_ensure_withdrawal_settings_schema();
    $username = arowin_normalize_sponsor_id($username);
    if ($username === '' || $id < 1) {
        return null;
    }
    $stmt = arowin_db()->prepare(
        'SELECT * FROM withdrawal_addresses
         WHERE id = :id AND UPPER(username) = :u LIMIT 1'
    );
    $stmt->execute([':id' => $id, ':u' => $username]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

/**
 * User withdraw: verify Firebase OTP → wallet limit check →
 * NOWPayments funds? instant send : queue 12–24h (no Master Wallet cut on fail).
 *
 * @return array{ok:bool, message:string, balance?:float, id?:int, status?:string, queued?:bool}
 */
function arowin_withdrawal_submit(
    string $username,
    float $amount,
    int $addressId,
    string $firebaseToken
): array {
    arowin_ensure_withdrawal_settings_schema();
    require_once __DIR__ . '/withdrawal-repository.php';
    require_once __DIR__ . '/nowpayments-service.php';
    require_once __DIR__ . '/wallet-repository.php';
    require_once __DIR__ . '/crypto-helpers.php';

    $username = arowin_normalize_sponsor_id($username);
    $cfg = arowin_nowpayments_config();
    $min = (float) ($cfg['min_withdraw'] ?? 10);
    $max = (float) ($cfg['max_withdraw'] ?? 50000);

    $otpCheck = arowin_firebase_verify_withdrawal_token($firebaseToken, $username);
    if (empty($otpCheck['ok'])) {
        return $otpCheck;
    }

    $user = arowin_get_user_by_username($username);
    if (!$user) {
        return ['ok' => false, 'message' => 'Account not found.'];
    }
    if (!empty($user['power_owner'])) {
        return ['ok' => false, 'message' => 'Power IDs cannot withdraw. Use your main ID.'];
    }

    $addr = arowin_withdrawal_address_get($username, $addressId);
    if ($addr === null) {
        return ['ok' => false, 'message' => 'Select a saved withdrawal address.'];
    }

    $amtCheck = arowin_money_ok($amount, $min, $max, 'Withdrawal');
    if (empty($amtCheck['ok'])) {
        return $amtCheck;
    }
    $amount = (float) $amtCheck['amount'];

    $walletBal = arowin_wallet_balance($username);
    if ($amount > $walletBal + 0.00001) {
        return [
            'ok' => false,
            'message' => 'Limit crossed. You entered $' . number_format($amount, 2)
                . ' but Master Wallet has only $' . number_format($walletBal, 2) . ' USDT.',
            'balance' => $walletBal,
        ];
    }

    $address = trim((string) $addr['address']);
    $currency = arowin_payout_currency_for_address(
        $address,
        (string) ($addr['currency'] ?? $cfg['payout_currency'] ?? 'usdtbsc')
    );
    $addrCheck = arowin_validate_crypto_address($address, $currency);
    if (empty($addrCheck['ok'])) {
        return ['ok' => false, 'message' => (string) ($addrCheck['message'] ?? 'Invalid address.')];
    }

    $np = arowin_nowpayments();
    if (!$np->hasPayoutCredentials()) {
        return [
            'ok' => false,
            'message' => 'Instant payouts are not ready yet. Please try again later.',
            'balance' => $walletBal,
        ];
    }

    $payoutAmount = arowin_withdrawal_payout_amount($amount);

    // NOWPayments custody must be on the SAME network as the destination address.
    $balRes = $np->getBalance();
    if (!empty($balRes['ok'])) {
        $npBal = $np->currencyBalance($currency);
        if ($npBal + 0.00000001 < $payoutAmount) {
            $held = $np->usdtBalances();
            $heldBits = [];
            foreach ($held as $tick => $amt) {
                $heldBits[] = strtoupper($tick) . ' $' . number_format($amt, 2);
            }
            $needLabel = $currency === 'usdttrc20' ? 'USDT-TRC20 (address starts with T)' : 'USDT-BEP20 (0x address)';
            $hasSameNetwork = ($currency === 'usdtbsc' && isset($held['usdtbsc']))
                || ($currency === 'usdttrc20' && isset($held['usdttrc20']));
            if ($heldBits !== []) {
                return [
                    'ok' => false,
                    'balance' => $walletBal,
                    'message' => $hasSameNetwork
                        ? 'The payment service has ' . implode(', ', $heldBits)
                            . ' on the correct ' . $needLabel . ' network, but this withdrawal needs '
                            . number_format($payoutAmount, 2) . ' USDT after the fee. Add funds to that network, then try again.'
                        : 'The payment service has ' . implode(', ', $heldBits)
                            . ' but this withdrawal needs ' . $needLabel
                            . '. Convert funds to that network, then try again.',
                ];
            }
            return arowin_withdrawal_queue_for_later($username, $amount, $address, $currency);
        }
    }

    // Instant path: create unpaid record, then debit+send inside approve.
    // On reject/fail approve restores Master Wallet.
    $created = arowin_withdrawal_create_record($username, $amount, $address, $currency, 'pending');
    if (empty($created['ok'])) {
        return $created;
    }
    $id = (int) ($created['id'] ?? 0);
    $otp = arowin_nowpayments_totp_code();
    $approve = arowin_withdrawal_approve($id, $username, 'Instant user withdraw', $otp);

    if (!empty($approve['queued'])) {
        try {
            require_once __DIR__ . '/mail-service.php';
            arowin_send_transaction_email(
                $username,
                'Withdrawal requested',
                'Your withdrawal request was submitted and is queued for processing.',
                [
                    'Amount' => '$' . number_format($amount, 2) . ' USDT',
                    'Network' => strtoupper($currency),
                    'Wallet address' => $address,
                    'Status' => 'Queued',
                    'New balance' => '$' . number_format((float) ($approve['balance'] ?? 0), 2) . ' USDT',
                ]
            );
        } catch (Throwable $mailEx) {
            error_log('Withdrawal email exception for ' . $username . ': ' . $mailEx->getMessage());
        }
        return [
            'ok' => true,
            'id' => $id,
            'balance' => (float) ($approve['balance'] ?? arowin_wallet_balance($username)),
            'status' => 'queued',
            'queued' => true,
            'message' => (string) ($approve['message']
                ?? 'We will process your amount within 12 hours to 24 hours.'),
        ];
    }

    if (!empty($approve['ok'])) {
        try {
            require_once __DIR__ . '/mail-service.php';
            arowin_send_transaction_email(
                $username,
                'Withdrawal submitted',
                'Your withdrawal was submitted successfully and is being processed.',
                [
                    'Amount' => '$' . number_format($amount, 2) . ' USDT',
                    'Network' => strtoupper($currency),
                    'Wallet address' => $address,
                    'Status' => 'Processing',
                    'New balance' => '$' . number_format(arowin_wallet_balance($username), 2) . ' USDT',
                ]
            );
        } catch (Throwable $mailEx) {
            error_log('Withdrawal email exception for ' . $username . ': ' . $mailEx->getMessage());
        }
        return [
            'ok' => true,
            'id' => $id,
            'balance' => arowin_wallet_balance($username),
            'status' => 'processing',
            'message' => 'Master Wallet deducted $' . number_format($amount, 2)
                . '. $' . number_format($payoutAmount, 2)
                . ' USDT sent after 10% fee.',
        ];
    }

    // Fail / 2FA — Master Wallet restored on payout create fail.
    // If 2FA is pending, payout already exists: keep hold and show processing window.
    $failMsg = (string) ($approve['message'] ?? 'Payout failed.');
    $failLow = strtolower($failMsg);
    if (str_contains($failLow, 'whitelist')) {
        $failMsg = 'This payout address is not approved for payouts. Please contact support to whitelist the address.';
    }
    if (!empty($approve['needs_2fa'])) {
        return [
            'ok' => true,
            'id' => $id,
            'balance' => arowin_wallet_balance($username),
            'status' => 'awaiting_2fa',
            'queued' => true,
            'message' => 'We will process your amount within 12 hours to 24 hours.',
        ];
    }

    return [
        'ok' => false,
        'id' => $id,
        'balance' => (float) ($approve['balance'] ?? arowin_wallet_balance($username)),
        'status' => (string) ($approve['status'] ?? 'failed'),
        'message' => 'Withdrawal failed: ' . $failMsg . ' Master Wallet was not deducted.',
    ];
}
