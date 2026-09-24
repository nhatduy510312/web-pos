<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/db_config.php';

use Composer\InstalledVersions;

$failures = 0;
$warnings = 0;

function preflightResult(string $status, string $message): void
{
    global $failures, $warnings;
    if ($status === 'FAIL') $failures++;
    if ($status === 'WARN') $warnings++;
    echo '[' . $status . '] ' . $message . PHP_EOL;
}

preflightResult(
    version_compare(PHP_VERSION, '8.2.0', '>=') ? 'PASS' : 'FAIL',
    'PHP ' . PHP_VERSION . ' (yêu cầu PHP 8.2 trở lên)'
);

foreach (['mysqli', 'fileinfo', 'dom', 'gd', 'zip', 'mbstring', 'xmlreader', 'xmlwriter'] as $extension) {
    preflightResult(
        extension_loaded($extension) ? 'PASS' : 'FAIL',
        'PHP extension: ' . $extension
    );
}

$spreadsheetVersion = InstalledVersions::getPrettyVersion('phpoffice/phpspreadsheet') ?: 'unknown';
preflightResult(
    version_compare(ltrim($spreadsheetVersion, 'v'), '5.8.1', '>=') ? 'PASS' : 'FAIL',
    'PhpSpreadsheet ' . $spreadsheetVersion
);

$environment = strtolower(trim((string)(getenv('POS_APP_ENV') ?: 'development')));
preflightResult(
    in_array($environment, ['production', 'prod'], true) ? 'PASS' : 'WARN',
    'POS_APP_ENV=' . $environment . ' (server thật nên là production)'
);

if (filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN)) {
    preflightResult('WARN', 'display_errors đang bật cho PHP CLI; hãy kiểm tra lại PHP-FPM/Apache.');
} else {
    preflightResult('PASS', 'display_errors đang tắt.');
}

foreach ([
    dirname(__DIR__) . '/data' => 'Thư mục công thức',
    dirname(__DIR__) . '/site/storage' => 'Thư mục dữ liệu website',
] as $path => $label) {
    preflightResult(is_dir($path) && is_writable($path) ? 'PASS' : 'FAIL', $label . ' có quyền ghi');
}

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $settings = posDatabaseSettings();
    $database = new mysqli(
        $settings['host'],
        $settings['user'],
        $settings['password'],
        $settings['name'],
        $settings['port']
    );
    $database->set_charset('utf8mb4');
    $database->query('SELECT 1');
    preflightResult('PASS', 'Kết nối database.');

    $requiredIndexes = [
        ['orders', 'idx_orders_status_paid_at'],
        ['orders', 'idx_orders_table_status'],
        ['order_items', 'idx_order_items_order_product'],
    ];
    $indexStatement = $database->prepare(
        'SELECT COUNT(*) total FROM information_schema.statistics '
        . 'WHERE table_schema=DATABASE() AND table_name=? AND index_name=?'
    );
    foreach ($requiredIndexes as [$table, $index]) {
        $indexStatement->bind_param('ss', $table, $index);
        $indexStatement->execute();
        $found = (int)$indexStatement->get_result()->fetch_assoc()['total'] > 0;
        preflightResult($found ? 'PASS' : 'FAIL', 'Index ' . $table . '.' . $index);
    }
    $database->close();
} catch (Throwable $exception) {
    preflightResult('FAIL', 'Database/config: ' . $exception->getMessage());
}

echo PHP_EOL . 'Kết quả: ' . $failures . ' lỗi, ' . $warnings . ' cảnh báo.' . PHP_EOL;
exit($failures > 0 ? 1 : 0);
