<?php

function accountClientIp(): string
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function accountUserAgent(): string
{
    return substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
}

function recordLoginHistory(
    mysqli $conn,
    ?int $userId,
    string $username,
    string $result
): void {
    $ip = accountClientIp();
    $userAgent = accountUserAgent();
    $stmt = $conn->prepare(
        "INSERT INTO login_history(user_id, username, result, ip_address, user_agent)
         VALUES(?, ?, ?, ?, ?)"
    );
    $stmt->bind_param('issss', $userId, $username, $result, $ip, $userAgent);
    $stmt->execute();
}

function endAccountSession(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

function accountAccessDenied(string $message, string $redirect): void
{
    if (defined('API_REQUEST') && API_REQUEST) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message,
            'redirect' => $redirect,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

function enforceAccountSession(mysqli $conn): void
{
    if (empty($_SESSION['logged_in']) || PHP_SAPI === 'cli') {
        return;
    }

    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare(
        "SELECT u.id, u.username, u.role, u.employee_id, u.is_active,
                u.must_change_password, u.session_version,
                e.is_active AS employee_active
         FROM users u
         LEFT JOIN employees e ON e.id = u.employee_id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();

    $sessionVersion = (int)($_SESSION['session_version'] ?? 0);
    $employeeIsInactive = $account
        && $account['employee_id'] !== null
        && (int)$account['employee_active'] !== 1;
    if (!$account || !(int)$account['is_active'] || $employeeIsInactive
        || (int)$account['session_version'] !== $sessionVersion) {
        endAccountSession();
        accountAccessDenied('Phiên đăng nhập đã hết hiệu lực.', 'login.php?reason=session_expired');
    }

    // Tài khoản mua hàng dùng phiên đăng nhập và khu vực riêng, tuyệt đối không vào POS.
    if (($account['role'] ?? '') === 'purchaser') {
        endAccountSession();
        accountAccessDenied('Tài khoản mua hàng không được truy cập POS.', 'purchase_login.php');
    }

    if (($account['role'] ?? '') === 'bakery_admin') {
        endAccountSession();
        accountAccessDenied('Tài khoản bếp bánh không được truy cập POS.', 'bakery_login.php');
    }

    $_SESSION['username'] = $account['username'];
    $_SESSION['role'] = $account['role'] ?: 'staff';
    $_SESSION['employee_id'] = $account['employee_id'] !== null ? (int)$account['employee_id'] : null;
    $_SESSION['must_change_password'] = (int)$account['must_change_password'];

    $currentPage = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ((int)$account['must_change_password'] === 1
        && !in_array($currentPage, ['change_password.php', 'logout.php'], true)) {
        accountAccessDenied('Bạn cần đổi mật khẩu trước khi tiếp tục.', 'change_password.php?required=1');
    }
}
