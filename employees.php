<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

$error = '';
$successMessages = [
    'added' => 'Đã thêm nhân viên và tạo tài khoản đăng nhập.',
    'updated' => 'Đã cập nhật thông tin nhân viên.',
    'deleted' => 'Đã xóa nhân viên và tài khoản đăng nhập liên kết.',
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

if (isset($_POST['delete'])) {
    $id = (int)($_POST['id'] ?? 0);

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("DELETE FROM users WHERE employee_id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->commit();
        header('Location: employees.php?success=deleted');
        exit;
    } catch (Throwable $e) {
        $conn->rollback();
        $error = employeeFormError($e);
    }
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
                "INSERT INTO users(username, password, role, employee_id) VALUES(?, ?, ?, ?)"
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
        "SELECT e.id, u.id AS user_id
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
                    $stmt = $conn->prepare("UPDATE users SET username = ?, password = ?, role = 'staff' WHERE id = ?");
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
                    "INSERT INTO users(username, password, role, employee_id) VALUES(?, ?, ?, ?)"
                );
                $stmt->bind_param('sssi', $username, $passwordHash, $role, $id);
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
    "SELECT e.*, u.id AS user_id, u.username
     FROM employees e
     LEFT JOIN users u ON u.employee_id = e.id
     ORDER BY e.name"
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
.employee-table { min-width:1050px; }
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
          <th>Mật khẩu mới</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($employees->num_rows === 0): ?>
        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">👥</div>Chưa có nhân viên nào</div></td></tr>
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
          <td><input form="<?= $formId ?>" type="password" name="password" class="form-input" placeholder="Để trống nếu giữ nguyên" minlength="8" autocomplete="new-password"></td>
          <td>
            <div class="employee-actions">
              <form method="post" id="<?= $formId ?>">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" name="update_employee" class="btn btn-sm btn-primary">💾 Lưu</button>
              </form>
              <form method="post" onsubmit="return confirm('Xóa nhân viên <?= htmlspecialchars($row['name'], ENT_QUOTES) ?> và tài khoản đăng nhập?')">
                <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                <button type="submit" name="delete" class="btn btn-sm btn-danger">🗑 Xóa</button>
              </form>
            </div>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
