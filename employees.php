<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

$error = '';
$successMessages = [
    'added' => 'Đã thêm nhân viên và tạo tài khoản đăng nhập.',
    'updated' => 'Đã cập nhật thông tin nhân viên.',
    'disabled' => 'Đã ngừng hoạt động nhân viên và thu hồi các phiên đăng nhập.',
    'enabled' => 'Đã kích hoạt lại nhân viên.',
    'shared_disabled' => 'Đã khóa tài khoản user dùng chung.',
];
$success = $successMessages[$_GET['success'] ?? ''] ?? '';

function validEmployeeUsername(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username);
}

function employeeFormError(Throwable $e): string
{
    if ($e instanceof mysqli_sql_exception && (int)$e->getCode() === 1062) {
        return 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
    }

    error_log('Employee account update failed: ' . $e->getMessage());
    return 'Không thể lưu thông tin nhân viên. Vui lòng thử lại.';
}

if (isset($_POST['set_active'])) {
    $id = (int)($_POST['id'] ?? 0);
    $isActive = (int)($_POST['set_active'] ?? 0) === 1 ? 1 : 0;

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE employees SET is_active = ? WHERE id = ?");
        $stmt->bind_param('ii', $isActive, $id);
        $stmt->execute();
        $stmt = $conn->prepare(
            "UPDATE users
             SET is_active = ?, session_version = session_version + 1
             WHERE employee_id = ?"
        );
        $stmt->bind_param('ii', $isActive, $id);
        $stmt->execute();
        $conn->commit();
        header('Location: employees.php?success=' . ($isActive ? 'enabled' : 'disabled'));
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $error = employeeFormError($e);
    }
}

if (isset($_POST['disable_shared_user'])) {
    $stmt = $conn->prepare(
        "UPDATE users
         SET is_active = 0, session_version = session_version + 1
         WHERE LOWER(username) = 'user' AND employee_id IS NULL"
    );
    $stmt->execute();
    header('Location: employees.php?success=shared_disabled');
    exit;
}

if (isset($_POST['add'])) {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $type = in_array($_POST['employee_type'] ?? '', ['probation', 'official'], true)
        ? $_POST['employee_type']
        : 'probation';

    if ($name === '') {
        $error = 'Vui lòng nhập tên nhân viên.';
    } elseif (!validEmployeeUsername($username)) {
        $error = 'Tên đăng nhập phải có 3–50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } else {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("INSERT INTO employees(name, employee_type) VALUES(?, ?)");
            $stmt->bind_param('ss', $name, $type);
            $stmt->execute();
            $employeeId = (int)$conn->insert_id;
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'staff';
            $stmt = $conn->prepare(
                "INSERT INTO users
                    (username, password, role, employee_id, is_active, must_change_password, session_version)
                 VALUES(?, ?, ?, ?, 1, 1, 1)"
            );
            $stmt->bind_param('sssi', $username, $passwordHash, $role, $employeeId);
            $stmt->execute();
            $conn->commit();
            header('Location: employees.php?success=added');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = employeeFormError($e);
        }
    }
}

if (isset($_POST['update_employee'])) {
    $id = (int)($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $type = in_array($_POST['employee_type'] ?? '', ['probation', 'official'], true)
        ? $_POST['employee_type']
        : 'probation';

    $stmt = $conn->prepare(
        "SELECT e.id, e.is_active, u.id AS user_id
         FROM employees e
         LEFT JOIN users u ON u.employee_id = e.id
         WHERE e.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $current = $stmt->get_result()->fetch_assoc();

    if (!$current) {
        $error = 'Không tìm thấy nhân viên.';
    } elseif ($name === '') {
        $error = 'Vui lòng nhập tên nhân viên.';
    } elseif (!validEmployeeUsername($username)) {
        $error = 'Tên đăng nhập phải có 3–50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    } elseif ($password !== '' && strlen($password) < 8) {
        $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
    } elseif (!$current['user_id'] && $password === '') {
        $error = 'Nhân viên chưa có tài khoản. Vui lòng nhập mật khẩu để tạo tài khoản.';
    } else {
        try {
            $conn->begin_transaction();
            $stmt = $conn->prepare("UPDATE employees SET name = ?, employee_type = ? WHERE id = ?");
            $stmt->bind_param('ssi', $name, $type, $id);
            $stmt->execute();

            if ($current['user_id']) {
                $userId = (int)$current['user_id'];
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare(
                        "UPDATE users
                         SET username = ?, password = ?, role = 'staff', must_change_password = 1,
                             session_version = session_version + 1
                         WHERE id = ?"
                    );
                    $stmt->bind_param('ssi', $username, $passwordHash, $userId);
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, role = 'staff' WHERE id = ?");
                    $stmt->bind_param('si', $username, $userId);
                }
                $stmt->execute();
            } else {
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'staff';
                $stmt = $conn->prepare(
                    "INSERT INTO users
                        (username, password, role, employee_id, is_active, must_change_password, session_version)
                     VALUES(?, ?, ?, ?, ?, 1, 1)"
                );
                $employeeActive = (int)$current['is_active'];
                $stmt->bind_param('sssii', $username, $passwordHash, $role, $id, $employeeActive);
                $stmt->execute();
            }

            $conn->commit();
            header('Location: employees.php?success=updated');
            exit;
        } catch (Throwable $e) {
            $conn->rollback();
            $error = employeeFormError($e);
        }
    }
}

$employees = $conn->query(
    "SELECT e.*, u.id AS user_id, u.username, u.is_active AS account_active,
            u.must_change_password, u.last_login_at
     FROM employees e
     LEFT JOIN users u ON u.employee_id = e.id
     ORDER BY e.is_active DESC, e.name"
);
$sharedUser = $conn->query(
    "SELECT id, username, is_active, last_login_at
     FROM users
     WHERE LOWER(username) = 'user' AND employee_id IS NULL
     LIMIT 1"
)->fetch_assoc();
$loginHistory = $conn->query(
    "SELECT h.*, COALESCE(e.name, h.username) AS display_name
     FROM login_history h
     LEFT JOIN users u ON u.id = h.user_id
     LEFT JOIN employees e ON e.id = u.employee_id
     ORDER BY h.id DESC LIMIT 100"
);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Nhân viên — GHÉ Coffee</title>
<style>
.employee-table { min-width:1250px; }
.employee-table .form-input, .employee-table .form-select { font-size:12px;padding:6px 9px; }
.employee-actions { display:flex;gap:6px;align-items:center;white-space:nowrap; }
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>👥 Quản lý nhân viên</h1>
      <p>Admin quản lý hồ sơ và tài khoản đăng nhập riêng của từng nhân viên</p>
    </div>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if ($sharedUser): ?>
  <div class="card" style="margin-bottom:14px;border-color:var(--warn-bd);">
    <div class="flex-b" style="gap:12px;">
      <div>
        <div class="section-title" style="margin-bottom:4px;">⚠️ Tài khoản dùng chung: <?= htmlspecialchars($sharedUser['username']) ?></div>
        <p class="text-muted text-sm">
          Trạng thái: <?= (int)$sharedUser['is_active'] === 1 ? 'đang hoạt động' : 'đã khóa' ?>.
          Chỉ khóa sau khi đã cấp tài khoản riêng cho toàn bộ nhân viên.
        </p>
      </div>
      <?php if ((int)$sharedUser['is_active'] === 1): ?>
      <form method="post" onsubmit="return confirm('Khóa tài khoản user dùng chung và đăng xuất mọi thiết bị đang dùng tài khoản này?')">
        <button type="submit" name="disable_shared_user" class="btn btn-danger">🔒 Khóa tài khoản dùng chung</button>
      </form>
      <?php else: ?>
      <span class="badge badge-gray">🔒 Đã khóa</span>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <div class="grid g2" style="gap:14px;margin-bottom:20px;">
    <div class="card">
      <div class="section-title">➕ Thêm nhân viên và tài khoản</div>
      <form method="post" autocomplete="off">
        <div class="form-group">
          <label class="form-label">Tên nhân viên</label>
          <input type="text" name="name" class="form-input" placeholder="Nguyễn Văn A" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Tên đăng nhập</label>
            <input type="text" name="username" class="form-input" placeholder="nguyenvana" minlength="3" maxlength="50" required autocomplete="off">
          </div>
          <div class="form-group">
            <label class="form-label">Mật khẩu</label>
            <input type="password" name="password" class="form-input" placeholder="Ít nhất 8 ký tự" minlength="8" required autocomplete="new-password">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Loại hợp đồng</label>
          <select name="employee_type" class="form-select">
            <option value="probation">Thử việc (20,000đ/h)</option>
            <option value="official">Chính thức (25,000đ/h)</option>
          </select>
        </div>
        <button type="submit" name="add" class="btn btn-primary btn-block">Thêm nhân viên</button>
      </form>
    </div>

    <div class="card">
      <div class="section-title">💡 Mức lương theo giờ</div>
      <div style="display:flex;flex-direction:column;gap:10px;margin-top:8px;">
        <div class="stat-card" style="padding:14px 16px;">
          <div class="stat-label">Thử việc</div>
          <div class="stat-value c-orange money">20,000đ/h</div>
        </div>
        <div class="stat-card" style="padding:14px 16px;">
          <div class="stat-label">Chính thức</div>
          <div class="stat-value c-green money">25,000đ/h</div>
        </div>
      </div>
      <div class="alert alert-warn" style="margin-top:12px;">
        Mật khẩu cũ không hiển thị. Để trống ô “Mật khẩu mới” nếu không muốn đổi.
      </div>
    </div>
  </div>

  <div class="tbl-wrap">
    <table class="tbl employee-table">
      <thead>
        <tr>
          <th>#</th>
          <th>Tên nhân viên</th>
          <th>Loại</th>
          <th>Lương/giờ</th>
          <th>Tên đăng nhập</th>
          <th>Trạng thái</th>
          <th>Đăng nhập cuối</th>
          <th>Mật khẩu mới</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($employees->num_rows === 0): ?>
        <tr><td colspan="9"><div class="empty-state"><div class="empty-state-icon">👥</div>Chưa có nhân viên nào</div></td></tr>
        <?php else: while ($row = $employees->fetch_assoc()): $formId = 'employee-' . (int)$row['id']; ?>
        <tr>
          <td class="text-muted"><?= (int)$row['id'] ?></td>
          <td><input form="<?= $formId ?>" type="text" name="name" class="form-input" value="<?= htmlspecialchars($row['name']) ?>" required></td>
          <td>
            <select form="<?= $formId ?>" name="employee_type" class="form-select" style="width:135px;">
              <option value="probation" <?= $row['employee_type']==='probation'?'selected':'' ?>>Thử việc</option>
              <option value="official" <?= $row['employee_type']==='official'?'selected':'' ?>>Chính thức</option>
            </select>
          </td>
          <td class="fw-600 money"><?= $row['employee_type']==='official' ? '25,000' : '20,000' ?>đ/h</td>
          <td>
            <input form="<?= $formId ?>" type="text" name="username" class="form-input" value="<?= htmlspecialchars($row['username'] ?? '') ?>" placeholder="Chưa liên kết" minlength="3" maxlength="50" required>
            <?php if (!$row['user_id']): ?><span class="badge badge-yellow" style="margin-top:4px;">Chưa có tài khoản</span><?php endif; ?>
          </td>
          <td>
            <?php if (!(int)$row['is_active']): ?>
            <span class="badge badge-gray">Ngừng hoạt động</span>
            <?php elseif (!$row['user_id']): ?>
            <span class="badge badge-yellow">Chưa có tài khoản</span>
            <?php elseif ((int)$row['must_change_password']): ?>
            <span class="badge badge-yellow">Chờ đổi mật khẩu</span>
            <?php else: ?>
            <span class="badge badge-green">Đang hoạt động</span>
            <?php endif; ?>
          </td>
          <td class="text-sm text-muted"><?= $row['last_login_at'] ? date('d/m/Y H:i', strtotime($row['last_login_at'])) : '—' ?></td>
          <td><input form="<?= $formId ?>" type="password" name="password" class="form-input" placeholder="Để trống nếu giữ nguyên" minlength="8" autocomplete="new-password"></td>
          <td>
            <div class="employee-actions">
              <form method="post" id="<?= $formId ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" name="update_employee" class="btn btn-sm btn-primary">💾 Lưu</button>
              </form>
              <form method="post" onsubmit="return confirm('<?= (int)$row['is_active'] ? 'Ngừng hoạt động' : 'Kích hoạt lại' ?> nhân viên <?= htmlspecialchars($row['name'], ENT_QUOTES) ?>?')">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" name="set_active" value="<?= (int)$row['is_active'] ? 0 : 1 ?>" class="btn btn-sm <?= (int)$row['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                  <?= (int)$row['is_active'] ? '⏸ Ngừng hoạt động' : '▶ Kích hoạt' ?>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

  <div class="section-title" style="margin-top:20px;">🔐 100 lần đăng nhập gần nhất</div>
  <div class="tbl-wrap">
    <table class="tbl">
      <thead><tr><th>Thời gian</th><th>Tài khoản/Nhân viên</th><th>Kết quả</th><th>IP</th><th>Thiết bị</th></tr></thead>
      <tbody>
        <?php if ($loginHistory->num_rows === 0): ?>
        <tr><td colspan="5"><div class="empty-state">Chưa có lịch sử đăng nhập</div></td></tr>
        <?php else: while ($history = $loginHistory->fetch_assoc()): ?>
        <tr>
          <td class="text-sm"><?= date('d/m/Y H:i:s', strtotime($history['created_at'])) ?></td>
          <td class="fw-600"><?= htmlspecialchars($history['display_name']) ?> <span class="text-muted">(<?= htmlspecialchars($history['username']) ?>)</span></td>
          <td><span class="badge <?= $history['result'] === 'success' ? 'badge-green' : ($history['result'] === 'password_changed' ? 'badge-blue' : 'badge-red') ?>"><?= htmlspecialchars($history['result']) ?></span></td>
          <td><?= htmlspecialchars($history['ip_address'] ?: '—') ?></td>
          <td class="text-sm text-muted" style="max-width:360px;white-space:normal;"><?= htmlspecialchars($history['user_agent'] ?: '—') ?></td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
