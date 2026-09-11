<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

$error = '';
$successMessages = [
    'added' => 'Đã tạo tài khoản mua hàng.',
    'updated' => 'Đã cập nhật tài khoản mua hàng.',
    'disabled' => 'Đã khóa tài khoản và thu hồi các phiên đăng nhập.',
    'enabled' => 'Đã kích hoạt tài khoản mua hàng.',
];
$success = $successMessages[$_GET['success'] ?? ''] ?? '';

function validPurchaseUsername(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $username);
}

function purchaseAccountError(Throwable $exception): string
{
    if ($exception instanceof mysqli_sql_exception && (int)$exception->getCode() === 1062) {
        return 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
    }
    error_log('Purchase account update failed: ' . $exception->getMessage());
    return 'Không thể lưu tài khoản mua hàng. Vui lòng thử lại.';
}

if (isset($_POST['add_account'])) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!validPurchaseUsername($username)) {
        $error = 'Tên đăng nhập phải có 3–50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } else {
        try {
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'purchaser';
            $stmt = $conn->prepare(
                'INSERT INTO users
                    (username, password, role, employee_id, is_active, must_change_password, session_version)
                 VALUES (?, ?, ?, NULL, 1, 0, 1)'
            );
            $stmt->bind_param('sss', $username, $passwordHash, $role);
            $stmt->execute();
            header('Location: purchase_accounts.php?success=added');
            exit;
        } catch (Throwable $exception) {
            $error = purchaseAccountError($exception);
        }
    }
}

if (isset($_POST['update_account'])) {
    $id = (int)($_POST['id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!validPurchaseUsername($username)) {
        $error = 'Tên đăng nhập phải có 3–50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } else {
        try {
            if ($password !== '') {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare(
                    "UPDATE users
                     SET username = ?, password = ?, session_version = session_version + 1
                     WHERE id = ? AND role = 'purchaser'"
                );
                $stmt->bind_param('ssi', $username, $passwordHash, $id);
            } else {
                $stmt = $conn->prepare(
                    "UPDATE users SET username = ? WHERE id = ? AND role = 'purchaser'"
                );
                $stmt->bind_param('si', $username, $id);
            }
            $stmt->execute();
            header('Location: purchase_accounts.php?success=updated');
            exit;
        } catch (Throwable $exception) {
            $error = purchaseAccountError($exception);
        }
    }
}

if (isset($_POST['set_active'])) {
    $id = (int)($_POST['id'] ?? 0);
    $active = (int)$_POST['set_active'] === 1 ? 1 : 0;
    $stmt = $conn->prepare(
        "UPDATE users
         SET is_active = ?, session_version = session_version + 1
         WHERE id = ? AND role = 'purchaser'"
    );
    $stmt->bind_param('ii', $active, $id);
    $stmt->execute();
    header('Location: purchase_accounts.php?success=' . ($active ? 'enabled' : 'disabled'));
    exit;
}

$accounts = $conn->query(
    "SELECT id, username, is_active, last_login_at, created_at
     FROM users WHERE role = 'purchaser'
     ORDER BY is_active DESC, username"
);
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Tài khoản mua hàng — GHÉ</title>
<style>
.purchase-account-layout{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:16px;align-items:start}.purchase-account-table{min-width:760px}.purchase-account-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}@media(max-width:780px){.purchase-account-layout{grid-template-columns:1fr}}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<main class="page-content">
  <header class="page-hdr"><div class="page-hdr-left"><h1>🧾 Tài khoản mua hàng</h1><p>Tài khoản riêng chỉ truy cập trang nhập thông tin mua hàng, không vào được POS.</p></div><a class="btn btn-secondary" href="purchase_login.php" target="_blank" rel="noopener">Mở trang đăng nhập riêng</a></header>
  <?php if ($error !== ''): ?><div class="alert alert-err" style="margin-bottom:14px">⚠️ <?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($success !== ''): ?><div class="alert alert-ok" style="margin-bottom:14px">✅ <?= htmlspecialchars($success) ?></div><?php endif; ?>
  <div class="purchase-account-layout">
    <section class="card">
      <div class="section-title">Tạo tài khoản mua hàng</div>
      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-group"><label class="form-label" for="new-username">Tên đăng nhập</label><input class="form-input" id="new-username" name="username" minlength="3" maxlength="50" required autocomplete="off" placeholder="muahang"></div>
        <div class="form-group"><label class="form-label" for="new-password">Mật khẩu</label><input class="form-input" type="password" id="new-password" name="password" minlength="8" required autocomplete="new-password" placeholder="Ít nhất 8 ký tự"></div>
        <button class="btn btn-primary btn-block" type="submit" name="add_account">Tạo tài khoản</button>
      </form>
      <div class="alert alert-info" style="margin-top:14px">Gửi cho nhân viên đường dẫn <strong>purchase_login.php</strong>. Không gửi đường dẫn đăng nhập POS.</div>
    </section>
    <section class="tbl-wrap">
      <table class="tbl purchase-account-table">
        <thead><tr><th>Tài khoản</th><th>Trạng thái</th><th>Đăng nhập cuối</th><th>Mật khẩu mới</th><th>Thao tác</th></tr></thead>
        <tbody>
        <?php if ($accounts->num_rows === 0): ?><tr><td colspan="5"><div class="empty-state">Chưa có tài khoản mua hàng.</div></td></tr><?php endif; ?>
        <?php while ($account = $accounts->fetch_assoc()): $formId = 'purchase-account-' . (int)$account['id']; ?>
          <tr>
            <td><input form="<?= $formId ?>" class="form-input" name="username" minlength="3" maxlength="50" required value="<?= htmlspecialchars($account['username']) ?>"></td>
            <td><span class="badge <?= (int)$account['is_active'] === 1 ? 'badge-green' : 'badge-gray' ?>"><?= (int)$account['is_active'] === 1 ? 'Hoạt động' : 'Đã khóa' ?></span></td>
            <td class="text-sm text-muted"><?= $account['last_login_at'] ? date('d/m/Y H:i', strtotime($account['last_login_at'])) : '—' ?></td>
            <td><input form="<?= $formId ?>" class="form-input" type="password" name="password" minlength="8" autocomplete="new-password" placeholder="Để trống nếu giữ nguyên"></td>
            <td><div class="purchase-account-actions">
              <form method="post" id="<?= $formId ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><button class="btn btn-sm btn-primary" name="update_account">Lưu</button></form>
              <form method="post" onsubmit="return confirm('<?= (int)$account['is_active'] === 1 ? 'Khóa tài khoản này?' : 'Kích hoạt tài khoản này?' ?>')"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$account['id'] ?>"><button class="btn btn-sm <?= (int)$account['is_active'] === 1 ? 'btn-danger' : 'btn-success' ?>" name="set_active" value="<?= (int)$account['is_active'] === 1 ? 0 : 1 ?>"><?= (int)$account['is_active'] === 1 ? 'Khóa' : 'Kích hoạt' ?></button></form>
            </div></td>
          </tr>
        <?php endwhile; ?>
        </tbody>
      </table>
    </section>
  </div>
</main>
</body>
</html>
