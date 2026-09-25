<?php
/**
 * CSRF helpers (shared — kept light for fast auth boot)
 */
declare(strict_types=1);

function arowin_csrf_token(): string
{
    if (empty($_SESSION['arowin_csrf'])) {
        $_SESSION['arowin_csrf'] = bin2hex(random_bytes(16));
    }
    return (string) $_SESSION['arowin_csrf'];
}

function arowin_csrf_validate(?string $token): bool
{
    $session = (string) ($_SESSION['arowin_csrf'] ?? '');
    return $session !== '' && is_string($token) && hash_equals($session, $token);
}

/**
 * Validate CSRF on POST; exits with 403 on failure.
 */
function arowin_require_post_csrf(): void
{
    $token = (string) ($_POST['csrf'] ?? $_POST['csrf_token'] ?? '');
    if (!arowin_csrf_validate($token)) {
        http_response_code(403);
        exit('Invalid CSRF token.');
    }
}
