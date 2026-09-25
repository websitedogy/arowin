<?php
/**
 * NOWPayments API client — payments, invoices, auth, payouts, IPN verify
 */
declare(strict_types=1);

final class ArowinNowPaymentsClient
{
    private string $apiKey;
    private string $ipnSecret;
    private string $email;
    private string $password;
    private string $apiBase;
    private int $timeout;
    private ?string $jwt = null;
    private int $jwtExpiresAt = 0;

    /** @param array<string,mixed>|null $config */
    public function __construct(?array $config = null)
    {
        $cfg = $config ?? arowin_nowpayments_config();
        $this->apiKey = trim((string) ($cfg['api_key'] ?? ''));
        $this->ipnSecret = trim((string) ($cfg['ipn_secret'] ?? ''));
        $this->email = trim((string) ($cfg['email'] ?? ''));
        $this->password = (string) ($cfg['password'] ?? '');
        $sandbox = !empty($cfg['sandbox']);
        $this->apiBase = $sandbox
            ? 'https://api-sandbox.nowpayments.io/v1'
            : 'https://api.nowpayments.io/v1';
        $this->timeout = max(5, min(30, (int) ($cfg['timeout'] ?? 15)));
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function hasIpnSecret(): bool
    {
        return $this->ipnSecret !== '';
    }

    public function hasPayoutCredentials(): bool
    {
        return $this->isConfigured() && $this->email !== '' && $this->password !== '';
    }

    /**
     * GET /v1/balance — merchant custody balances.
     *
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function getBalance(): array
    {
        return $this->request('GET', '/balance');
    }

    /**
     * Available amount for a payout currency ticker (e.g. usdtbsc).
     */
    public function currencyBalance(string $currency): float
    {
        $currency = strtolower(trim($currency));
        if ($currency === '') {
            return 0.0;
        }
        $res = $this->getBalance();
        if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
            return 0.0;
        }
        $data = $res['data'];
        $entry = $data[$currency] ?? null;
        if (!is_array($entry)) {
            // Some responses nest under currency keys with different casing
            foreach ($data as $key => $val) {
                if (strtolower((string) $key) === $currency && is_array($val)) {
                    $entry = $val;
                    break;
                }
            }
        }
        if (!is_array($entry)) {
            return 0.0;
        }
        $amount = $entry['amount'] ?? $entry['available'] ?? $entry['balance'] ?? 0;
        return round((float) $amount, 8);
    }

    /**
     * @return array<string,float> ticker => available amount
     */
    public function usdtBalances(): array
    {
        $res = $this->getBalance();
        if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
            return [];
        }
        $out = [];
        foreach ($res['data'] as $key => $val) {
            $ticker = strtolower((string) $key);
            if (!str_starts_with($ticker, 'usdt') || !is_array($val)) {
                continue;
            }
            $amt = (float) ($val['amount'] ?? $val['available'] ?? $val['balance'] ?? 0);
            if ($amt > 0) {
                $out[$ticker] = round($amt, 8);
            }
        }
        return $out;
    }

    /**
     * Whether an API error indicates insufficient merchant funds.
     */
    public static function isInsufficientFundsMessage(string $message): bool
    {
        $m = strtolower($message);
        return str_contains($m, 'insufficient')
            || str_contains($m, 'not enough')
            || str_contains($m, 'lack of fund')
            || str_contains($m, 'balance is too low')
            || str_contains($m, 'no enough')
            || str_contains($m, 'low balance')
            || str_contains($m, 'exceeds available');
    }

    /**
     * POST /v1/payment — create a fixed-currency payment.
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function createPayment(array $payload): array
    {
        $body = [
            'price_amount' => round((float) ($payload['price_amount'] ?? 0), 2),
            'price_currency' => strtolower((string) ($payload['price_currency'] ?? 'usd')),
            'pay_currency' => strtolower((string) ($payload['pay_currency'] ?? '')),
            'order_id' => (string) ($payload['order_id'] ?? ''),
            'order_description' => (string) ($payload['order_description'] ?? 'Wallet deposit'),
            'ipn_callback_url' => (string) ($payload['ipn_callback_url'] ?? ''),
        ];
        if ($body['pay_currency'] === '') {
            return ['ok' => false, 'message' => 'pay_currency is required for /v1/payment.'];
        }
        if (!empty($payload['is_fixed_rate'])) {
            $body['is_fixed_rate'] = true;
        }
        if (!empty($payload['is_fee_paid_by_user'])) {
            $body['is_fee_paid_by_user'] = true;
        }

        return $this->request('POST', '/payment', $body);
    }

    /**
     * POST /v1/invoice — hosted checkout page (invoice_url).
     *
     * @param array<string,mixed> $payload
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function createInvoice(array $payload): array
    {
        $body = [
            'price_amount' => round((float) ($payload['price_amount'] ?? 0), 2),
            'price_currency' => strtolower((string) ($payload['price_currency'] ?? 'usd')),
            'order_id' => (string) ($payload['order_id'] ?? ''),
            'order_description' => (string) ($payload['order_description'] ?? 'Wallet deposit'),
            'ipn_callback_url' => (string) ($payload['ipn_callback_url'] ?? ''),
        ];
        $payCurrency = strtolower(trim((string) ($payload['pay_currency'] ?? '')));
        if ($payCurrency !== '') {
            $body['pay_currency'] = $payCurrency;
        }
        if (!empty($payload['success_url'])) {
            $body['success_url'] = (string) $payload['success_url'];
        }
        if (!empty($payload['cancel_url'])) {
            $body['cancel_url'] = (string) $payload['cancel_url'];
        }

        return $this->request('POST', '/invoice', $body);
    }

    /**
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function getPaymentStatus(string $paymentId): array
    {
        $paymentId = rawurlencode(trim($paymentId));
        if ($paymentId === '') {
            return ['ok' => false, 'message' => 'payment_id is required.'];
        }
        return $this->request('GET', '/payment/' . $paymentId);
    }

    /**
     * Find a payment by merchant order_id (invoice / missed payment_id).
     *
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function findPaymentByOrderId(string $orderId): array
    {
        $orderId = trim($orderId);
        if ($orderId === '') {
            return ['ok' => false, 'message' => 'order_id is required.'];
        }
        $res = $this->request('GET', '/payment/?limit=40&page=0&sortBy=created_at&orderBy=desc');
        if (empty($res['ok']) || !is_array($res['data'] ?? null)) {
            return ['ok' => false, 'message' => (string) ($res['message'] ?? 'Could not list payments.')];
        }
        $rows = $res['data']['data'] ?? $res['data'];
        if (!is_array($rows)) {
            return ['ok' => false, 'message' => 'Unexpected payment list.'];
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (strcasecmp(trim((string) ($row['order_id'] ?? '')), $orderId) === 0) {
                return ['ok' => true, 'message' => 'OK', 'data' => $row];
            }
        }
        return ['ok' => false, 'message' => 'Payment not found for order.'];
    }

    /**
     * Validate payout address for a currency.
     *
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function validateAddress(string $address, string $currency, ?string $extraId = null): array
    {
        $body = [
            'address' => trim($address),
            'currency' => strtolower(trim($currency)),
        ];
        if ($extraId !== null && $extraId !== '') {
            $body['extra_id'] = $extraId;
        }
        return $this->request('POST', '/payout/validate-address', $body);
    }

    /**
     * Obtain JWT for payout operations (valid ~5 minutes).
     *
     * @return array{ok:bool, message:string, token?:string}
     */
    public function authenticate(): array
    {
        if ($this->jwt !== null && time() < ($this->jwtExpiresAt - 30)) {
            return ['ok' => true, 'message' => 'OK', 'token' => $this->jwt];
        }
        if ($this->email === '' || $this->password === '') {
            return ['ok' => false, 'message' => 'Payment service credentials are missing.'];
        }

        $result = $this->request('POST', '/auth', [
            'email' => $this->email,
            'password' => $this->password,
        ], false);

        if (empty($result['ok']) || empty($result['data']['token'])) {
            return [
                'ok' => false,
                'message' => (string) ($result['message'] ?? 'Payment service authentication failed.'),
            ];
        }

        $this->jwt = (string) $result['data']['token'];
        $this->jwtExpiresAt = time() + 240;
        return ['ok' => true, 'message' => 'OK', 'token' => $this->jwt];
    }

    /**
     * POST /v1/payout — create mass payout batch.
     *
     * @param list<array<string,mixed>> $withdrawals
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function createPayout(array $withdrawals, string $ipnCallbackUrl = ''): array
    {
        $auth = $this->authenticate();
        if (empty($auth['ok'])) {
            return $auth;
        }

        $body = ['withdrawals' => array_values($withdrawals)];
        if ($ipnCallbackUrl !== '') {
            $body['ipn_callback_url'] = $ipnCallbackUrl;
        }

        return $this->request('POST', '/payout', $body, true, (string) $auth['token']);
    }

    /**
     * POST /v1/payout/{batch-id}/verify — confirm with 2FA email code.
     *
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function verifyPayout(string $batchId, string $verificationCode): array
    {
        $batchId = trim($batchId);
        $verificationCode = trim($verificationCode);
        if ($batchId === '' || $verificationCode === '') {
            return ['ok' => false, 'message' => 'Batch ID and verification code are required.'];
        }

        $auth = $this->authenticate();
        if (empty($auth['ok'])) {
            return $auth;
        }

        return $this->request(
            'POST',
            '/payout/' . rawurlencode($batchId) . '/verify',
            ['verification_code' => $verificationCode],
            true,
            (string) $auth['token']
        );
    }

    /**
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    public function getPayoutStatus(string $payoutId): array
    {
        $payoutId = trim($payoutId);
        if ($payoutId === '') {
            return ['ok' => false, 'message' => 'payout_id is required.'];
        }
        $auth = $this->authenticate();
        if (empty($auth['ok'])) {
            return $auth;
        }
        return $this->request(
            'GET',
            '/payout/' . rawurlencode($payoutId),
            [],
            true,
            (string) $auth['token']
        );
    }

    /**
     * Verify NOWPayments IPN HMAC-SHA512 signature.
     *
     * @param array<string,mixed> $payload
     */
    public function verifyIpnSignature(array $payload, string $receivedSignature): bool
    {
        if ($this->ipnSecret === '' || $receivedSignature === '') {
            return false;
        }
        $receivedSignature = strtolower(trim($receivedSignature));
        foreach ($this->ipnSignatureCandidates($payload) as $hmac) {
            if (hash_equals($hmac, $receivedSignature)) {
                return true;
            }
        }
        return false;
    }

    /**
     * NOWPayments HMAC samples differ (recursive vs top-level ksort, unicode flags).
     *
     * @return list<string>
     */
    private function ipnSignatureCandidates(array $payload): array
    {
        $recursive = $payload;
        $this->ksortRecursive($recursive);
        $flat = $payload;
        ksort($flat);
        $out = [];
        foreach ([$recursive, $flat] as $sorted) {
            foreach ([JSON_UNESCAPED_SLASHES, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE] as $flags) {
                $json = json_encode($sorted, $flags);
                if ($json === false) {
                    continue;
                }
                $out[] = hash_hmac('sha512', $json, $this->ipnSecret);
            }
        }
        return array_values(array_unique($out));
    }

    /**
     * @param array<string,mixed> $body
     * @return array{ok:bool, message:string, data?:array<string,mixed>}
     */
    private function request(
        string $method,
        string $path,
        array $body = [],
        bool $needApiKey = true,
        ?string $bearer = null
    ): array {
        if ($needApiKey && !$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Payment service is not configured.'];
        }

        $url = rtrim($this->apiBase, '/') . $path;
        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Could not initialize the payment request.'];
        }

        $headers = ['Accept: application/json'];
        if ($needApiKey) {
            $headers[] = 'x-api-key: ' . $this->apiKey;
        }
        if ($bearer !== null && $bearer !== '') {
            $headers[] = 'Authorization: Bearer ' . $bearer;
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => max($this->timeout, 45),
            CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_NOSIGNAL => true,
            CURLOPT_TCP_NODELAY => true,
            CURLOPT_ENCODING => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
        ];
        if (defined('CURL_HTTP_VERSION_1_1')) {
            $opts[CURLOPT_HTTP_VERSION] = CURL_HTTP_VERSION_1_1;
        }
        // Prefer system CA bundle when available (fixes intermittent SSL on XAMPP).
        $cainfo = (string) ini_get('curl.cainfo');
        if ($cainfo === '') {
            $cainfo = (string) ini_get('openssl.cafile');
        }
        if ($cainfo !== '' && is_file($cainfo)) {
            $opts[CURLOPT_CAINFO] = $cainfo;
        }

        if (strtoupper($method) !== 'GET') {
            $headers[] = 'Content-Type: application/json';
            $opts[CURLOPT_HTTPHEADER] = $headers;
            $opts[CURLOPT_POSTFIELDS] = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        curl_setopt_array($ch, $opts);

        $raw = false;
        $errno = 0;
        $error = '';
        $status = 0;
        $attempts = 0;
        while ($attempts < 2) {
            $attempts++;
            $raw = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // Retry once on transient network / TLS failures.
            if ($errno === 0 && $raw !== false) {
                break;
            }
            if ($attempts >= 2) {
                break;
            }
            usleep(400000);
        }
        curl_close($ch);

        if ($errno !== 0 || $raw === false) {
            error_log('NOWPayments cURL error [' . $path . ']: (' . $errno . ') ' . $error);
            $debug = filter_var(env('APP_DEBUG', false), FILTER_VALIDATE_BOOLEAN);
            return [
                'ok' => false,
                'message' => $debug && $error !== ''
                    ? ('Could not reach the payment service: ' . $error)
                    : 'Could not reach the payment service. Try again.',
            ];
        }

        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $plain = trim((string) $raw);
            // Some endpoints (e.g. validate-address) return plain "OK" on success.
            if ($status >= 200 && $status < 300 && strcasecmp($plain, 'OK') === 0) {
                return ['ok' => true, 'message' => 'OK', 'data' => ['status' => true]];
            }
            error_log('NOWPayments invalid JSON HTTP ' . $status . ' [' . $path . ']: ' . substr((string) $raw, 0, 400));
            return ['ok' => false, 'message' => 'Invalid response from the payment service.'];
        }

        if ($status < 200 || $status >= 300) {
            $msg = (string) ($data['message'] ?? $data['error'] ?? 'Payment request failed.');
            if (isset($data['code']) && is_scalar($data['code'])) {
                $msg = '[' . (string) $data['code'] . '] ' . $msg;
            }
            error_log('NOWPayments API HTTP ' . $status . ' [' . $path . ']: ' . $msg);
            return ['ok' => false, 'message' => $msg, 'data' => $data];
        }

        return ['ok' => true, 'message' => 'OK', 'data' => $data];
    }

    /** @param array<mixed> $array */
    private function ksortRecursive(array &$array): void
    {
        ksort($array);
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->ksortRecursive($value);
            }
        }
        unset($value);
    }
}

/**
 * Backward-compatible alias used across the app.
 */
class_alias(ArowinNowPaymentsClient::class, 'ArowinNowPaymentsService');

function arowin_nowpayments(): ArowinNowPaymentsClient
{
    static $svc = null;
    if (!$svc instanceof ArowinNowPaymentsClient) {
        $svc = new ArowinNowPaymentsClient();
    }
    return $svc;
}

function arowin_nowpayments_config(): array
{
    static $cfg = null;
    if (!is_array($cfg)) {
        $cfg = require dirname(__DIR__) . '/config/nowpayments.php';
    }
    return $cfg;
}

/**
 * Generate current TOTP code from NOWPAYMENTS_2FA_SECRET (Google Authenticator).
 * Empty string when secret is not configured.
 */
function arowin_nowpayments_totp_code(?string $secret = null, ?int $timestamp = null): string
{
    $cfg = arowin_nowpayments_config();
    $secret = strtoupper(preg_replace('/\s+/', '', (string) ($secret ?? ($cfg['totp_secret'] ?? ''))));
    if ($secret === '') {
        return '';
    }

    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    for ($i = 0, $len = strlen($secret); $i < $len; $i++) {
        $val = strpos($alphabet, $secret[$i]);
        if ($val === false) {
            continue;
        }
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $bytes = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $bytes .= chr((int) bindec($chunk));
        }
    }
    if ($bytes === '') {
        return '';
    }

    $time = (int) floor(($timestamp ?? time()) / 30);
    $binTime = pack('N*', 0, $time);
    $hash = hash_hmac('sha1', $binTime, $bytes, true);
    $offset = ord($hash[19]) & 0x0f;
    $code = (
        ((ord($hash[$offset]) & 0x7f) << 24)
        | ((ord($hash[$offset + 1]) & 0xff) << 16)
        | ((ord($hash[$offset + 2]) & 0xff) << 8)
        | (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;

    return str_pad((string) $code, 6, '0', STR_PAD_LEFT);
}
