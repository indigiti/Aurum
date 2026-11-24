<?php
// Copy to aurum-private/config/local.php and edit. Keep this file outside public_html.
return [
    'APP_ENV' => 'staging',              // staging | production
    'STORAGE_DRIVER' => 'json',          // json | mariadb
    'DB_DSN' => '',                      // mysql:host=127.0.0.1;dbname=aurumprop;charset=utf8mb4
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'AURUM_ADMIN_EMAILS' => 'admin@example.com',
];
