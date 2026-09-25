<?php
/**
 * Lightweight outbound mail (SMTP or PHP mail())
 */
declare(strict_types=1);

/**
 * @param array{to:string,subject:string,html?:string,text?:string,reply_to?:string} $message
 * @return array{ok:bool,message:string}
 */
function arowin_send_mail(array $message): array
{
    $to = strtolower(trim((string) ($message['to'] ?? '')));
    $subject = trim((string) ($message['subject'] ?? ''));
    $html = (string) ($message['html'] ?? '');
    $text = trim((string) ($message['text'] ?? ''));
    $replyTo = trim((string) ($message['reply_to'] ?? ''));

    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'Invalid recipient email.'];
    }
    if ($subject === '') {
        return ['ok' => false, 'message' => 'Subject is required.'];
    }
    if ($html === '' && $text === '') {
        return ['ok' => false, 'message' => 'Message body is empty.'];
    }
    if ($text === '' && $html !== '') {
        $text = trim(html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    $fromAddress = trim((string) env('MAIL_FROM_ADDRESS', (string) ($GLOBALS['APP']['contact_email'] ?? 'noreply@arowin.com')));
    $fromName = trim((string) env('MAIL_FROM_NAME', (string) ($GLOBALS['APP']['name'] ?? 'Arowin')));
    if ($fromAddress === '' || !filter_var($fromAddress, FILTER_VALIDATE_EMAIL)) {
        $fromAddress = 'noreply@arowin.com';
    }

    $mailer = strtolower(trim((string) env('MAIL_MAILER', 'mail')));
    if ($mailer === 'smtp') {
        return arowin_send_mail_smtp($to, $subject, $html, $text, $fromAddress, $fromName, $replyTo);
    }

    return arowin_send_mail_php($to, $subject, $html, $text, $fromAddress, $fromName, $replyTo);
}

/**
 * @return array{ok:bool,message:string}
 */
function arowin_send_mail_php(
    string $to,
    string $subject,
    string $html,
    string $text,
    string $fromAddress,
    string $fromName,
    string $replyTo
): array {
    $boundary = 'b_' . bin2hex(random_bytes(12));
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromHeader = arowin_mail_address_header($fromAddress, $fromName);

    $headers = [
        'MIME-Version: 1.0',
        'From: ' . $fromHeader,
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Arowin',
    ];
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $headers[] = 'Reply-To: ' . $replyTo;
    }

    $body = "--{$boundary}\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--{$boundary}\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html !== '' ? $html : nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))))
        . "--{$boundary}--\r\n";

    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        error_log('arowin_send_mail_php failed for ' . $to);
        return ['ok' => false, 'message' => 'Could not send email.'];
    }

    return ['ok' => true, 'message' => 'Email sent.'];
}

/**
 * Minimal SMTP client (AUTH LOGIN, STARTTLS / SMTPS).
 *
 * @return array{ok:bool,message:string}
 */
function arowin_send_mail_smtp(
    string $to,
    string $subject,
    string $html,
    string $text,
    string $fromAddress,
    string $fromName,
    string $replyTo
): array {
    $host = trim((string) env('MAIL_HOST', ''));
    $port = (int) env('MAIL_PORT', 587);
    $user = (string) env('MAIL_USERNAME', '');
    $pass = (string) env('MAIL_PASSWORD', '');
    $encryption = strtolower(trim((string) env('MAIL_ENCRYPTION', 'tls')));

    if ($host === '' || $port < 1) {
        return ['ok' => false, 'message' => 'SMTP is not configured.'];
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host;
    $errno = 0;
    $errstr = '';
    $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
    if (!$fp) {
        error_log("SMTP connect failed: {$errstr} ({$errno})");
        return ['ok' => false, 'message' => 'Could not connect to mail server.'];
    }
    stream_set_timeout($fp, 20);

    $read = static function () use ($fp): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $write = static function (string $cmd) use ($fp): void {
        fwrite($fp, $cmd . "\r\n");
    };
    $expect = static function (string $resp, string $code) use ($fp): bool {
        return str_starts_with(trim($resp), $code);
    };

    try {
        $greeting = $read();
        if (!$expect($greeting, '220')) {
            throw new RuntimeException('Bad SMTP greeting: ' . trim($greeting));
        }

        $ehloHost = 'localhost';
        if (!empty($_SERVER['HTTP_HOST'])) {
            $ehloHost = preg_replace('/:\d+$/', '', (string) $_SERVER['HTTP_HOST']) ?: 'localhost';
        }
        $write('EHLO ' . $ehloHost);
        $ehlo = $read();
        if (!$expect($ehlo, '250')) {
            $write('HELO ' . $ehloHost);
            $ehlo = $read();
            if (!$expect($ehlo, '250')) {
                throw new RuntimeException('EHLO/HELO failed.');
            }
        }

        if ($encryption === 'tls') {
            $write('STARTTLS');
            $tls = $read();
            if (!$expect($tls, '220')) {
                throw new RuntimeException('STARTTLS failed.');
            }
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('TLS negotiation failed.');
            }
            $write('EHLO ' . $ehloHost);
            $ehlo = $read();
            if (!$expect($ehlo, '250')) {
                throw new RuntimeException('EHLO after STARTTLS failed.');
            }
        }

        if ($user !== '') {
            $write('AUTH LOGIN');
            if (!$expect($read(), '334')) {
                throw new RuntimeException('AUTH LOGIN not accepted.');
            }
            $write(base64_encode($user));
            if (!$expect($read(), '334')) {
                throw new RuntimeException('SMTP username rejected.');
            }
            $write(base64_encode($pass));
            if (!$expect($read(), '235')) {
                throw new RuntimeException('SMTP authentication failed.');
            }
        }

        $write('MAIL FROM:<' . $fromAddress . '>');
        if (!$expect($read(), '250')) {
            throw new RuntimeException('MAIL FROM rejected.');
        }
        $write('RCPT TO:<' . $to . '>');
        $rcpt = $read();
        if (!$expect($rcpt, '250') && !$expect($rcpt, '251')) {
            throw new RuntimeException('RCPT TO rejected.');
        }
        $write('DATA');
        if (!$expect($read(), '354')) {
            throw new RuntimeException('DATA not accepted.');
        }

        $boundary = 'b_' . bin2hex(random_bytes(12));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = arowin_mail_address_header($fromAddress, $fromName);

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'X-Mailer: Arowin',
        ];
        if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        $payload = implode("\r\n", $headers) . "\r\n\r\n"
            . "--{$boundary}\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--{$boundary}\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html !== '' ? $html : nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))))
            . "--{$boundary}--\r\n.";

        $write($payload);
        if (!$expect($read(), '250')) {
            throw new RuntimeException('Message rejected by SMTP server.');
        }
        $write('QUIT');
        fclose($fp);

        return ['ok' => true, 'message' => 'Email sent.'];
    } catch (Throwable $e) {
        error_log('SMTP send error: ' . $e->getMessage());
        if (isset($fp) && is_resource($fp)) {
            fclose($fp);
        }
        return ['ok' => false, 'message' => 'Could not send email.'];
    }
}

function arowin_mail_address_header(string $email, string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return '<' . $email . '>';
    }
    $safe = preg_replace('/[\r\n"]+/', '', $name) ?? $name;
    return '=?UTF-8?B?' . base64_encode($safe) . '?= <' . $email . '>';
}

/**
 * Send a consistent black/orange transaction notification to a member.
 *
 * @param array<string,string> $details
 * @return array{ok:bool,message:string,sent?:bool}
 */
function arowin_send_transaction_email(string $username, string $event, string $summary, array $details): array
{
    if (!function_exists('arowin_get_user_by_username')) {
        return ['ok' => false, 'message' => 'User lookup is unavailable.', 'sent' => false];
    }
    $user = arowin_get_user_by_username($username);
    $email = strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true, 'message' => 'No email on profile.', 'sent' => false];
    }

    $name = trim((string) ($user['full_name'] ?? $username));
    $appName = (string) ($GLOBALS['APP']['name'] ?? 'Arowin Trading Portal');
    $support = (string) ($GLOBALS['APP']['contact_email'] ?? 'support@arowin.com');
    $when = function_exists('arowin_format_india_time')
        ? arowin_format_india_time(gmdate('Y-m-d H:i:s'), 'd M Y, h:i A')
        : gmdate('d M Y, H:i') . ' UTC';
    $safe = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');

    $text = "Hi {$name},\n\n{$summary}\n\nUser ID: {$username}\n";
    $rows = '<tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;width:38%;">User ID</td><td style="padding:10px 12px;border:1px solid #fed7aa;font-weight:700;">' . $safe($username) . '</td></tr>';
    foreach ($details as $label => $value) {
        $text .= $label . ': ' . $value . "\n";
        $rows .= '<tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">' . $safe((string) $label) . '</td><td style="padding:10px 12px;border:1px solid #fed7aa;font-weight:700;">' . $safe((string) $value) . '</td></tr>';
    }
    $text .= "Time: {$when}\n\nSupport: {$support}\n";
    $rows .= '<tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Time</td><td style="padding:10px 12px;border:1px solid #fed7aa;">' . $safe($when) . '</td></tr>';
    $html = '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>' . $safe($event) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#0b0b0b;font-family:Segoe UI,Arial,sans-serif;color:#171717;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b0b0b;padding:24px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #f97316;"><tr><td style="background:#111;color:#fff;padding:22px 24px;border-bottom:4px solid #f97316;font-size:19px;font-weight:700;">' . $safe($appName) . '</td></tr>'
        . '<tr><td style="padding:24px;"><p style="margin:0 0 10px;font-size:17px;">Hi <strong>' . $safe($name) . '</strong>,</p><p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#404040;">' . $safe($summary) . '</p><table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">' . $rows . '</table><p style="margin:20px 0 0;font-size:12px;color:#737373;">Support: ' . $safe($support) . '</p></td></tr></table></td></tr></table></body></html>';

    $result = arowin_send_mail([
        'to' => $email,
        'subject' => $appName . ' — ' . $event,
        'text' => $text,
        'html' => $html,
        'reply_to' => $support,
    ]);
    return ['ok' => !empty($result['ok']), 'message' => (string) ($result['message'] ?? ''), 'sent' => !empty($result['ok'])];
}

/**
 * Notify a member when a transaction attempt fails.
 *
 * @return array{ok:bool,message:string,sent?:bool}
 */
function arowin_send_transaction_failure_email(
    string $username,
    string $transaction,
    string $reason,
    array $details = []
): array {
    $details = array_merge(['Status' => 'Failed', 'Reason' => $reason], $details);
    return arowin_send_transaction_email(
        $username,
        $transaction . ' failed',
        'Your ' . strtolower($transaction) . ' could not be completed.',
        $details
    );
}

/**
 * Notify member when a deposit is credited to Master Wallet.
 * No-ops if the account has no valid email.
 *
 * @return array{ok:bool,message:string,sent?:bool}
 */
function arowin_notify_deposit_credited(
    string $username,
    float $amountUsd,
    string $reference = '',
    ?float $balanceUsd = null
): array {
    $username = function_exists('arowin_normalize_sponsor_id')
        ? arowin_normalize_sponsor_id($username)
        : strtoupper(trim($username));
    if ($username === '') {
        return ['ok' => false, 'message' => 'Invalid user.', 'sent' => false];
    }

    $user = function_exists('arowin_get_user_by_username')
        ? arowin_get_user_by_username($username)
        : null;
    if (!$user) {
        return ['ok' => false, 'message' => 'User not found.', 'sent' => false];
    }

    $email = strtolower(trim((string) ($user['email'] ?? '')));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => true, 'message' => 'No email on profile.', 'sent' => false];
    }

    $amountUsd = round($amountUsd, 2);
    if ($amountUsd < 0.01) {
        return ['ok' => false, 'message' => 'Invalid amount.', 'sent' => false];
    }

    if ($balanceUsd === null && function_exists('arowin_wallet_balance')) {
        $balanceUsd = arowin_wallet_balance($username);
    }

    $fullName = trim((string) ($user['full_name'] ?? $username));
    $appName = (string) ($GLOBALS['APP']['name'] ?? 'Arowin Trading Portal');
    $support = (string) ($GLOBALS['APP']['contact_email'] ?? 'support@arowin.com');
    $walletUrl = function_exists('url') ? url('wallet') : '';
    $historyUrl = function_exists('url') ? url('history') : '';
    $amountLabel = '$' . number_format($amountUsd, 2);
    $balanceLabel = $balanceUsd !== null ? ('$' . number_format((float) $balanceUsd, 2)) : '—';
    $refLabel = trim($reference) !== '' ? trim($reference) : '—';
    $when = function_exists('arowin_format_india_time')
        ? arowin_format_india_time(gmdate('Y-m-d H:i:s'), 'd M Y, h:i A')
        : gmdate('d M Y, H:i') . ' UTC';

    $subject = $appName . ' — Deposit credited ' . $amountLabel;

    $text = "Hi {$fullName},\n\n"
        . "Your deposit has been credited to Master Wallet.\n\n"
        . "User ID: {$username}\n"
        . "Amount: {$amountLabel} USDT\n"
        . "Reference: {$refLabel}\n"
        . "New balance: {$balanceLabel} USDT\n"
        . "Time: {$when}\n\n"
        . ($walletUrl !== '' ? "Wallet: {$walletUrl}\n" : '')
        . ($historyUrl !== '' ? "History: {$historyUrl}\n" : '')
        . "\nSupport: {$support}\n";

    $safeName = htmlspecialchars($fullName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeUid = htmlspecialchars($username, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeAmt = htmlspecialchars($amountLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeBal = htmlspecialchars($balanceLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeRef = htmlspecialchars($refLabel, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeWhen = htmlspecialchars($when, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeApp = htmlspecialchars($appName, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeSupport = htmlspecialchars($support, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $safeWallet = htmlspecialchars($walletUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $cta = $walletUrl !== ''
        ? '<p style="margin:22px 0 8px;text-align:center;"><a href="' . $safeWallet . '" style="display:inline-block;background:#f97316;color:#111;text-decoration:none;padding:12px 22px;border-radius:8px;font-weight:700;font-size:14px;">Open Wallet</a></p>'
        : '';

    $html = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><title>{$safeApp}</title></head>
<body style="margin:0;padding:0;background:#0b0b0b;font-family:Segoe UI,Arial,sans-serif;color:#111827;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b0b0b;padding:24px 12px;">
    <tr><td align="center">
            <table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border-radius:10px;overflow:hidden;border:1px solid #f97316;">
                <tr><td style="background:#111;color:#fff;padding:20px 24px;border-bottom:4px solid #f97316;font-size:18px;font-weight:700;">{$safeApp}</td></tr>
        <tr><td style="padding:24px;">
          <p style="margin:0 0 12px;font-size:16px;">Hi <strong>{$safeName}</strong>,</p>
          <p style="margin:0 0 18px;font-size:14px;line-height:1.5;color:#374151;">Your deposit was credited to Master Wallet.</p>
          <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="font-size:14px;border-collapse:collapse;">
            <tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;width:38%;color:#9a3412;">User ID</td><td style="padding:10px 12px;border:1px solid #fed7aa;font-weight:700;">{$safeUid}</td></tr>
            <tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Amount</td><td style="padding:10px 12px;border:1px solid #fed7aa;font-weight:700;">{$safeAmt} USDT</td></tr>
            <tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Reference</td><td style="padding:10px 12px;border:1px solid #fed7aa;">{$safeRef}</td></tr>
            <tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">New balance</td><td style="padding:10px 12px;border:1px solid #fed7aa;font-weight:700;">{$safeBal} USDT</td></tr>
            <tr><td style="padding:10px 12px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">Time</td><td style="padding:10px 12px;border:1px solid #fed7aa;">{$safeWhen}</td></tr>
          </table>
          {$cta}
          <p style="margin:18px 0 0;font-size:12px;color:#9ca3af;">Support: {$safeSupport}</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;

    $result = arowin_send_mail([
        'to' => $email,
        'subject' => $subject,
        'text' => $text,
        'html' => $html,
        'reply_to' => $support,
    ]);

    return [
        'ok' => !empty($result['ok']),
        'message' => (string) ($result['message'] ?? ''),
        'sent' => !empty($result['ok']),
    ];
}
