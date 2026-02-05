<?php

declare(strict_types=1);

function get_db(): mysqli
{
    static $db = null;

    if ($db instanceof mysqli) {
        return $db;
    }

    $config = require __DIR__ . '/config.php';

    mysqli_report(MYSQLI_REPORT_OFF);

    $db = new mysqli(
        $config['db_host'],
        $config['db_user'],
        $config['db_pass'],
        $config['db_name']
    );

    if ($db->connect_error) {
        http_response_code(500);
        exit('Datenbankverbindung fehlgeschlagen.');
    }

    if (!$db->set_charset('utf8mb4')) {
        http_response_code(500);
        exit('UTF-8 Zeichensatz konnte nicht gesetzt werden.');
    }

    return $db;
}
