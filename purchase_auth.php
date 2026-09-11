<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name('ghe_purchase_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self' 'unsafe-inline'");
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

date_default_timezone_set(getenv('POS_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/account_security.php';

function purchaseDatabase(): mysqli
{
    if (!extension_loaded('mysqli')) {
        throw new RuntimeException('Máy chủ chưa bật mysqli.');
    }
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 3);
    $db->real_connect(
        getenv('POS_DB_HOST') ?: 'localhost',
        getenv('POS_DB_USER') ?: 'root',
        getenv('POS_DB_PASS') ?: '',
        getenv('POS_DB_NAME') ?: 'cafe_pos',
        (int)(getenv('POS_DB_PORT') ?: 3306)
    );
    $db->set_charset('utf8mb4');
    return $db;
}

function clearPurchaseSession(): void
{
    unset(
        $_SESSION['purchase_logged_in'],
        $_SESSION['purchase_user_id'],
        $_SESSION['purchase_username'],
        $_SESSION['purchase_session_version']
    );
}

function purchaseLoginRedirect(string $reason = ''): void
{
    $location = 'purchase_login.php';
    if ($reason !== '') {
        $location .= '?reason=' . rawurlencode($reason);
    }
    header('Location: ' . $location);
    exit;
}

function requirePurchaseSession(mysqli $db): array
{
    if (empty($_SESSION['purchase_logged_in'])) {
        purchaseLoginRedirect();
    }

    $userId = (int)($_SESSION['purchase_user_id'] ?? 0);
    $stmt = $db->prepare(
        "SELECT id, username, is_active, session_version
         FROM users
         WHERE id = ? AND role = 'purchaser'
         LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();

    if (!$account
        || (int)$account['is_active'] !== 1
        || (int)$account['session_version'] !== (int)($_SESSION['purchase_session_version'] ?? 0)) {
        clearPurchaseSession();
        session_regenerate_id(true);
        purchaseLoginRedirect('session_expired');
    }

    $_SESSION['purchase_username'] = $account['username'];
    return $account;
}

function purchaseH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function purchaseValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function purchaseValidMonth(string $value): bool
{
    return (bool)preg_match('/^(?:[1-9][0-9]{3})-(?:0[1-9]|1[0-2])$/D', $value);
}

function purchaseMoney($value): string
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}
