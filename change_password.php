<?php
include 'auth.php';
include 'config.php';
requireCsrfForFormPost();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = (string)($_POST['current_password'] ?? '');
    $newPassword = (string)($_POST['new_password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');
    $userId = (int)($_SESSION['user_id'] ?? 0);

    $stmt = $conn->prepare("SELECT username, password, session_version FROM users WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $account = $stmt->get_result()->fetch_assoc();

    if (!$account || !password_verify($currentPassword, $account['password'])) {
        $error = 'Mật khẩu hiện tại không đúng.';
    } elseif (strlen($newPassword) < 8) {
        $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif ($newPassword !== $confirmPassword) {
        $error = 'Xác nhận mật khẩu mới không khớp.';
    } elseif (password_verify($newPassword, $account['password'])) {
        $error = 'Mật khẩu mới phải khác mật khẩu hiện tại.';
    } else {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare(
            "UPDATE users
             SET password = ?, must_change_password = 0, session_version = session_version + 1
             WHERE id = ?"
        );
        $stmt->bind_param('si', $passwordHash, $userId);
        $stmt->execute();

        $_SESSION['session_version'] = (int)$account['session_version'] + 1;
        $_SESSION['must_change_password'] = 0;
        session_regenerate_id(true);
        recordLoginHistory($conn, $userId, $account['username'], 'password_changed');
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Đổi mật khẩu — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content" style="max-width:520px;margin:0 auto;">
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🔑 Đổi mật khẩu</h1>
      <p>Bảo vệ tài khoản đăng nhập của bạn</p>
    </div>
  </div>

  <?php if (isset($_GET['required'])): ?>
  <div class="alert alert-warn" style="margin-bottom:14px;">Bạn phải đổi mật khẩu tạm thời trước khi sử dụng hệ thống.</div>
  <?php endif; ?>
  <?php if ($error): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">✅ Đã đổi mật khẩu. Các phiên đăng nhập cũ đã bị thu hồi.</div>
  <?php endif; ?>

  <div class="card">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="form-group">
        <label class="form-label">Mật khẩu hiện tại</label>
        <input type="password" name="current_password" class="form-input" required autocomplete="current-password">
      </div>
      <div class="form-group">
        <label class="form-label">Mật khẩu mới</label>
        <input type="password" name="new_password" class="form-input" minlength="8" required autocomplete="new-password">
      </div>
      <div class="form-group">
        <label class="form-label">Nhập lại mật khẩu mới</label>
        <input type="password" name="confirm_password" class="form-input" minlength="8" required autocomplete="new-password">
      </div>
      <button type="submit" class="btn btn-primary btn-block">Lưu mật khẩu mới</button>
    </form>
  </div>
</div>
</body>
</html>
