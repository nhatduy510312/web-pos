<?php
include 'auth.php';
include 'config.php';
requireRole(['admin', 'staff', 'user']);
requireCsrfForFormPost();

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$error = '';
$success = isset($_GET['saved']) ? 'Đã lưu ca làm việc.' : (isset($_GET['deleted']) ? 'Đã xóa ca làm việc.' : '');
$currentEmployee = null;

if (!$isAdmin) {
    $userId = (int)($_SESSION['user_id'] ?? 0);
    $stmt = $conn->prepare(
        "SELECT e.id, e.name, e.employee_type
         FROM users u
         JOIN employees e ON e.id = u.employee_id
         WHERE u.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $currentEmployee = $stmt->get_result()->fetch_assoc();
}

if (isset($_POST['delete'])) {
    $attendanceId = (int)$_POST['delete'];

    if ($isAdmin) {
        $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ?");
        $stmt->bind_param('i', $attendanceId);
    } elseif ($currentEmployee) {
        $employeeId = (int)$currentEmployee['id'];
        $stmt = $conn->prepare("DELETE FROM attendance WHERE id = ? AND employee_id = ?");
        $stmt->bind_param('ii', $attendanceId, $employeeId);
    } else {
        $stmt = null;
        $error = 'Tài khoản chưa được liên kết với nhân viên nên không thể sửa chấm công.';
    }

    if ($stmt) {
        $stmt->execute();
        header('Location: attendance.php?deleted=1');
        exit;
    }
}

if (isset($_POST['save'])) {
    $eid = $isAdmin
        ? (int)($_POST['employee_id'] ?? 0)
        : (int)($currentEmployee['id'] ?? 0);
    $workDate = $_POST['work_date'] ?? '';
    $checkIn = $_POST['check_in'] ?? '';
    $checkOut = $_POST['check_out'] ?? '';

    if ($eid <= 0) {
        $error = $isAdmin ? 'Vui lòng chọn nhân viên.' : 'Tài khoản chưa được liên kết với nhân viên.';
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate)) {
        $error = 'Ngày làm việc không hợp lệ.';
    } elseif (!preg_match('/^\d{2}:\d{2}$/', $checkIn) || !preg_match('/^\d{2}:\d{2}$/', $checkOut)) {
        $error = 'Giờ vào hoặc giờ ra không hợp lệ.';
    } else {
        $startAt = strtotime($workDate . ' ' . $checkIn);
        $endAt = strtotime($workDate . ' ' . $checkOut);

        if ($endAt <= $startAt) {
            $error = 'Giờ ra phải sau giờ vào.';
        } else {
            $stmt = $conn->prepare("SELECT employee_type FROM employees WHERE id = ? LIMIT 1");
            $stmt->bind_param('i', $eid);
            $stmt->execute();
            $employee = $stmt->get_result()->fetch_assoc();

            if (!$employee) {
                $error = 'Không tìm thấy nhân viên.';
            } else {
                $workHours = round(($endAt - $startAt) / 3600, 2);
                $hourSalary = $employee['employee_type'] === 'official' ? 25000 : 20000;
                $salary = $workHours * $hourSalary;
                $stmt = $conn->prepare(
                    "INSERT INTO attendance(employee_id, work_date, check_in, check_out, work_hours, salary)
                     VALUES(?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param('isssdd', $eid, $workDate, $checkIn, $checkOut, $workHours, $salary);
                $stmt->execute();
                header('Location: attendance.php?saved=1');
                exit;
            }
        }
    }
}

$employees = $isAdmin ? $conn->query("SELECT * FROM employees ORDER BY name") : null;

if ($isAdmin) {
    $list = $conn->query(
        "SELECT a.*, e.name AS emp_name, e.employee_type
         FROM attendance a
         LEFT JOIN employees e ON a.employee_id = e.id
         ORDER BY a.work_date DESC, a.id DESC"
    );
} elseif ($currentEmployee) {
    $employeeId = (int)$currentEmployee['id'];
    $stmt = $conn->prepare(
        "SELECT a.*, e.name AS emp_name, e.employee_type
         FROM attendance a
         JOIN employees e ON a.employee_id = e.id
         WHERE a.employee_id = ?
         ORDER BY a.work_date DESC, a.id DESC"
    );
    $stmt->bind_param('i', $employeeId);
    $stmt->execute();
    $list = $stmt->get_result();
} else {
    $list = null;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Chấm công — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🕒 Chấm công</h1>
      <p><?= $isAdmin ? 'Quản lý giờ làm việc của tất cả nhân viên' : 'Chỉ ghi nhận và xem giờ làm việc của chính bạn' ?></p>
    </div>
    <?php if ($isAdmin): ?>
    <a href="salary_report.php" class="btn btn-secondary btn-sm">💰 Xem báo cáo lương</a>
    <?php endif; ?>
  </div>

  <?php if ($error): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">⚠️ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">✅ <?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <?php if (!$isAdmin && !$currentEmployee): ?>
  <div class="alert alert-warn">
    ⚠️ Tài khoản này chưa được liên kết với hồ sơ nhân viên. Vui lòng nhờ admin vào mục Nhân viên để tạo tài khoản riêng.
  </div>
  <?php else: ?>
  <div class="card" style="margin-bottom:14px;">
    <div class="section-title">➕ Thêm ca làm việc</div>
    <form method="post">
      <div class="form-row">
        <div class="form-group" style="flex:2;">
          <label class="form-label">Nhân viên</label>
          <?php if ($isAdmin): ?>
          <select name="employee_id" class="form-select" required>
            <option value="">-- Chọn nhân viên --</option>
            <?php while ($employee = $employees->fetch_assoc()): ?>
            <option value="<?= (int)$employee['id'] ?>"><?= htmlspecialchars($employee['name']) ?></option>
            <?php endwhile; ?>
          </select>
          <?php else: ?>
          <input type="text" class="form-input" value="<?= htmlspecialchars($currentEmployee['name']) ?>" readonly>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Ngày làm</label>
          <input type="date" name="work_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Giờ vào</label>
          <input type="time" name="check_in" class="form-input" required>
        </div>
        <div class="form-group">
          <label class="form-label">Giờ ra</label>
          <input type="time" name="check_out" class="form-input" required>
        </div>
        <button type="submit" name="save" class="btn btn-primary" style="height:38px;">Lưu ca</button>
      </div>
    </form>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Ngày</th>
          <?php if ($isAdmin): ?><th>Nhân viên</th><?php endif; ?>
          <th>Loại</th>
          <th>Giờ vào</th>
          <th>Giờ ra</th>
          <th class="text-right">Số giờ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$list || $list->num_rows === 0): ?>
        <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>"><div class="empty-state"><div class="empty-state-icon">🕒</div>Chưa có dữ liệu chấm công</div></td></tr>
        <?php else: while ($row = $list->fetch_assoc()): ?>
        <tr>
          <td class="fw-600"><?= date('d/m/Y', strtotime($row['work_date'])) ?></td>
          <?php if ($isAdmin): ?><td><?= htmlspecialchars($row['emp_name'] ?? '—') ?></td><?php endif; ?>
          <td>
            <?php if ($row['employee_type'] === 'official'): ?>
            <span class="badge badge-green">Chính thức</span>
            <?php else: ?>
            <span class="badge badge-yellow">Thử việc</span>
            <?php endif; ?>
          </td>
          <td><?= substr($row['check_in'], 0, 5) ?></td>
          <td><?= substr($row['check_out'], 0, 5) ?></td>
          <td class="text-right"><?= number_format($row['work_hours'], 1) ?>h</td>
          <td>
            <form method="post" onsubmit="return confirm('Xóa ca này?')" style="margin:0;">
              <button type="submit" name="delete" value="<?= (int)$row['id'] ?>" class="btn btn-sm btn-danger">🗑</button>
            </form>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

</div>
</body>
</html>
