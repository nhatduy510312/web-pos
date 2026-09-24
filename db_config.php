<?php

/**
 * Cấu hình database dùng chung cho POS, Mua hàng và Bakery.
 *
 * Production phải khai báo đủ POS_DB_HOST, POS_DB_USER, POS_DB_PASS và
 * POS_DB_NAME. Môi trường XAMPP local vẫn dùng giá trị mặc định để phát triển.
 */
function posDatabaseSettings(): array
{
    $configuredEnvironment = getenv('POS_APP_ENV');
    if ($configuredEnvironment === false || trim((string)$configuredEnvironment) === '') {
        // Tương thích ngược với các server XAMPP đang dùng cấu hình
        // mặc định. Chỉ bật chế độ fail-closed khi quản trị viên chủ động
        // khai báo POS_APP_ENV=production.
        $environment = 'development';
        if (PHP_SAPI !== 'cli') {
            error_log('POS_APP_ENV is not configured; using legacy database defaults.');
        }
    } else {
        $environment = strtolower(trim((string)$configuredEnvironment));
    }
    $isProduction = in_array($environment, ['production', 'prod'], true);
    $required = ['POS_DB_HOST', 'POS_DB_USER', 'POS_DB_PASS', 'POS_DB_NAME'];

    if ($isProduction) {
        foreach ($required as $name) {
            $value = getenv($name);
            if ($value === false || trim((string)$value) === '') {
                error_log('Missing required production environment variable: ' . $name);
                throw new RuntimeException('Database configuration is incomplete.');
            }
        }
    }

    $portValue = getenv('POS_DB_PORT');
    $port = $portValue === false || $portValue === '' ? 3306 : filter_var(
        $portValue,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 65535]]
    );
    if ($port === false) {
        throw new RuntimeException('Database port is invalid.');
    }

    $password = getenv('POS_DB_PASS');

    return [
        'host' => getenv('POS_DB_HOST') ?: 'localhost',
        'user' => getenv('POS_DB_USER') ?: 'root',
        'password' => $password === false ? '' : (string)$password,
        'name' => getenv('POS_DB_NAME') ?: 'cafe_pos',
        'port' => (int)$port,
        'environment' => $environment,
    ];
}
