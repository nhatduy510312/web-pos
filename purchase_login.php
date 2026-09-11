<?php
require_once __DIR__ . '/purchase_auth.php';
require_once __DIR__ . '/login_rate_limit.php';

try {
    $purchaseDb = purchaseDatabase();
} catch (Throwable $exception) {
    error_log('Purchase login database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Chưa kết nối được hệ thống mua hàng. Vui lòng thử lại sau.');
}
$error = isset($_GET['reason'])
    ? 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.'
    : '';

if (!empty($_SESSION['purchase_logged_in'])) {
    try {
        requirePurchaseSession($purchaseDb);
        header('Location: purchases.php');
        exit;
    } catch (Throwable $exception) {
        clearPurchaseSession();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $username = substr(trim((string)($_POST['username'] ?? '')), 0, 50);
    $password = (string)($_POST['password'] ?? '');

    if (loginIsRateLimited('purchase:' . $username)) {
        recordLoginHistory($purchaseDb, null, $username, 'purchase_rate_limited');
        $error = 'Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.';
    } else {
        $stmt = $purchaseDb->prepare(
            "SELECT id, username, password, is_active, session_version
             FROM users
             WHERE LOWER(username) = LOWER(?) AND role = 'purchaser'
             LIMIT 1"
        );
        $stmt->bind_param('s', $username);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();

        if ($account && (int)$account['is_active'] === 1
            && password_verify($password, $account['password'])) {
            clearLoginFailures('purchase:' . $username);
            clearPosSession();
            session_regenerate_id(true);
            $_SESSION['purchase_logged_in'] = true;
            $_SESSION['purchase_user_id'] = (int)$account['id'];
            $_SESSION['purchase_username'] = $account['username'];
            $_SESSION['purchase_session_version'] = (int)$account['session_version'];

            $userId = (int)$account['id'];
            $stmt = $purchaseDb->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            recordLoginHistory($purchaseDb, $userId, $account['username'], 'purchase_success');

            header('Location: purchases.php');
            exit;
        }

        recordLoginFailure('purchase:' . $username);
        recordLoginHistory(
            $purchaseDb,
            $account ? (int)$account['id'] : null,
            $username,
            $account && !(int)$account['is_active'] ? 'purchase_inactive' : 'purchase_failed'
        );
        $error = 'Sai tài khoản hoặc mật khẩu.';
    }
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="assets/purchasing.css">
<title>Đăng nhập mua hàng — GHÉ</title>
</head>
<body class="purchase-login-body">
<main class="purchase-login-wrap">
  <div class="purchase-brand"><span>🧾</span><h1>GHÉ · Mua hàng</h1><p>Khu vực dành riêng cho nhân viên mua hàng</p></div>
  <section class="purchase-card purchase-login-card">
    <?php if ($error !== ''): ?><div class="purchase-alert purchase-alert-error"><?= purchaseH($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="on">
      <?= csrf_field() ?>
      <label class="purchase-label" for="username">Tài khoản</label>
      <input class="purchase-input" id="username" name="username" maxlength="50" required autofocus autocomplete="username" value="<?= purchaseH($_POST['username'] ?? '') ?>">
      <label class="purchase-label" for="password">Mật khẩu</label>
      <input class="purchase-input" type="password" id="password" name="password" required autocomplete="current-password">
      <button class="purchase-button purchase-button-primary purchase-button-block" type="submit">Đăng nhập</button>
    </form>
  </section>
  <p class="purchase-login-note">Tài khoản này không thể truy cập hệ thống POS.</p>
</main>
</body>
</html>
