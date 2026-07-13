<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$dbHost = getenv('POS_DB_HOST') ?: 'localhost';
$dbUser = getenv('POS_DB_USER') ?: 'root';
$dbPass = getenv('POS_DB_PASS');
$dbPass = ($dbPass === false) ? '' : $dbPass;
$dbName = getenv('POS_DB_NAME') ?: 'cafe_pos';
try { $conn = new mysqli($dbHost, $dbUser, $dbPass, $dbName); }
catch (mysqli_sql_exception $e) { error_log('POS database connection failed: ' . $e->getMessage()); http_response_code(500); exit('Service temporarily unavailable.'); }

$conn->set_charset("utf8mb4");
