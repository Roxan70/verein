<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('APP_NAME', 'EU Windhound Race Suite');
define('APP_BASE', '/eu_race');
define('APP_DEFAULT_LANG', 'de');
define('APP_LANGS', 'de,en,hu,cs,sk');

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'eu_race');

define('PAGE_SIZE', 25);
?>
