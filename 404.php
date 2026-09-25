<?php
/**
 * Root 404 handler
 */
declare(strict_types=1);

http_response_code(404);
require __DIR__ . '/public/pages/404.php';
