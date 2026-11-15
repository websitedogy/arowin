<?php

return [
    'host' => getenv('AROWIN_GLOBAL_DB_HOST') ?: '127.0.0.1',
    'port' => getenv('AROWIN_GLOBAL_DB_PORT') ?: '3306',
    'database' => getenv('AROWIN_GLOBAL_DB_NAME') ?: '',
    'username' => getenv('AROWIN_GLOBAL_DB_USER') ?: '',
    'password' => getenv('AROWIN_GLOBAL_DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
];
