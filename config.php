<?php

$appTimezone = getenv('POS_TIMEZONE') ?: 'Asia/Ho_Chi_Minh';
date_default_timezone_set($appTimezone);

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once __DIR__ . '/db_config.php';
try {
    $dbSettings = posDatabaseSettings();
    $conn = new mysqli(
        $dbSettings['host'],
        $dbSettings['user'],
        $dbSettings['password'],
        $dbSettings['name'],
        $dbSettings['port']
    );
}
catch (mysqli_sql_exception $e) { error_log('POS database connection failed: ' . $e->getMessage()); http_response_code(500); exit('Service temporarily unavailable.'); }
catch (RuntimeException $e) { error_log('POS database configuration failed: ' . $e->getMessage()); http_response_code(500); exit('Service temporarily unavailable.'); }

$conn->set_charset("utf8mb4");

require_once __DIR__ . '/account_security.php';
enforceAccountSession($conn);

require_once __DIR__ . '/shift_guard.php';
$autoClosedShifts = autoCloseDueShifts($conn);
enforceClosedShiftForApi($conn);
