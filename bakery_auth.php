<?php

if (session_status() === PHP_SESSION_NONE) {
    session_name('ghe_bakery_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en'], true)) {
    $_SESSION['bakery_lang'] = $_GET['lang'];
}
require_once __DIR__ . '/bakery_i18n.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:; style-src 'self'; script-src 'self' 'unsafe-inline'");
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');

date_default_timezone_set(getenv('POS_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/account_security.php';

function bakeryDatabase(): mysqli
{
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

function bakeryDbOrFail(): mysqli
{
    try {
        return bakeryDatabase();
    } catch (Throwable $exception) {
        error_log('Bakery database connection failed: ' . $exception->getMessage());
        http_response_code(503);
        exit(bakeryT('Chưa kết nối được hệ thống bếp bánh. Vui lòng thử lại sau.'));
    }
}

function clearBakerySession(): void
{
    unset($_SESSION['bakery_logged_in'], $_SESSION['bakery_user_id'], $_SESSION['bakery_username'], $_SESSION['bakery_session_version']);
}

function bakeryLoginRedirect(string $reason = ''): void
{
    header('Location: bakery_login.php' . ($reason !== '' ? '?reason=' . rawurlencode($reason) : ''));
    exit;
}

function requireBakerySession(mysqli $db): array
{
    if (empty($_SESSION['bakery_logged_in'])) bakeryLoginRedirect();
    $userId = (int)($_SESSION['bakery_user_id'] ?? 0);
    $stmt = $db->prepare("SELECT id, username, is_active, session_version FROM users WHERE id=? AND role='bakery_admin' LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();
    if (!$account || (int)$account['is_active'] !== 1
        || (int)$account['session_version'] !== (int)($_SESSION['bakery_session_version'] ?? 0)) {
        clearBakerySession();
        session_regenerate_id(true);
        bakeryLoginRedirect('session_expired');
    }
    $_SESSION['bakery_username'] = $account['username'];
    return $account;
}

function bakeryH($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function bakeryValidDate(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
}

function bakeryValidMonth(string $value): bool
{
    return (bool)preg_match('/^(?:[1-9][0-9]{3})-(?:0[1-9]|1[0-2])$/D', $value);
}

function bakeryMonthRange(array $source = []): array
{
    $current = date('Y-m');
    $legacyMonth = trim((string)($source['month'] ?? ''));
    $fallback = bakeryValidMonth($legacyMonth) ? $legacyMonth : $current;
    $fromMonth = trim((string)($source['from_month'] ?? $fallback));
    $toMonth = trim((string)($source['to_month'] ?? $fallback));
    if (!bakeryValidMonth($fromMonth)) $fromMonth = $current;
    if (!bakeryValidMonth($toMonth)) $toMonth = $current;
    if ($fromMonth > $toMonth) [$fromMonth, $toMonth] = [$toMonth, $fromMonth];
    $fromDate = $fromMonth . '-01';
    $toDate = (new DateTimeImmutable($toMonth . '-01'))->format('Y-m-t');
    return [
        'from_month' => $fromMonth,
        'to_month' => $toMonth,
        'from_date' => $fromDate,
        'to_date' => $toDate,
    ];
}

function bakeryMonthRangeQuery(array $range, array $extra = []): string
{
    return http_build_query(array_merge([
        'from_month' => $range['from_month'],
        'to_month' => $range['to_month'],
    ], $extra));
}

function bakeryMoneyInput(string $value): ?string
{
    $value = trim(str_replace(' ', '', $value));
    if (!preg_match('/^(?:\d+|\d{1,3}(?:[.,]\d{3})+)$/D', $value)) return null;
    $digits = ltrim(str_replace(['.', ','], '', $value), '0');
    if ($digits === '' || strlen($digits) > 10) return null;
    return $digits . '.00';
}

function bakeryDecimalInput(string $value, int $wholeDigits = 10, int $decimalDigits = 3): ?string
{
    $value = trim(str_replace(',', '.', $value));
    if (!preg_match('/^\d{1,' . $wholeDigits . '}(?:\.\d{1,' . $decimalDigits . '})?$/D', $value)
        || (float)$value <= 0) return null;
    return number_format((float)$value, $decimalDigits, '.', '');
}

function bakeryMoney($value): string
{
    return number_format((float)$value, 0, ',', '.') . 'đ';
}

function bakeryNumber($value): string
{
    return rtrim(rtrim(number_format((float)$value, 3, '.', ''), '0'), '.');
}

function bakeryCurrentStock(mysqli $db, int $materialId, int $excludeMovementId = 0): float
{
    $stmt = $db->prepare(
        "SELECT COALESCE(SUM(CASE WHEN movement_type IN ('purchase','adjustment_in') THEN quantity ELSE -quantity END),0) stock
         FROM bakery_stock_movements WHERE material_id=? AND id<>?"
    );
    $stmt->bind_param('ii', $materialId, $excludeMovementId);
    $stmt->execute();
    return (float)$stmt->get_result()->fetch_assoc()['stock'];
}
