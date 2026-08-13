<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/config.php';

if (empty($autoClosedShifts)) {
    echo '[' . date('Y-m-d H:i:s') . '] Không có ca nào cần chốt.' . PHP_EOL;
    exit;
}

foreach ($autoClosedShifts as $date) {
    echo '[' . date('Y-m-d H:i:s') . '] Đã tự động chốt ca ' . $date . '.' . PHP_EOL;
}
