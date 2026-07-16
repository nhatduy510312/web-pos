<?php
date_default_timezone_set('Asia/Ho_Chi_Minh'); // luôn dùng giờ GMT+7, không phụ thuộc timezone máy chủ
include 'auth.php';
include 'config.php';
requireRole(['admin', 'staff', 'user']);
requireCsrfForFormPost();

if (isset($_POST['delete'])) {
    $id   = (int)$_POST['delete'];
    $stmt = $conn->prepare("DELETE FROM attendance WHERE id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: attendance.php"); exit;
}

if (isset($_POST['save'])) {
    $eid       = (int)$_POST['employee_id'];
    $work_date = $_POST['work_date'];
    $check_in  = $_POST['check_in'];
    $check_out = $_POST['check_out'];

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $work_date)) $work_date = date('Y-m-d');

    $work_hours = round((strtotime($check_out) - strtotime($check_in)) / 3600, 2);
    if ($work_hours < 0) $work_hours = 0;

    /* BUG FIX: use prepared statement instead of interpolating $eid */
    $stmt = $conn->prepare("SELECT employee_type FROM employees WHERE id=?");
    $stmt->bind_param("i", $eid); $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc();
    $hour_salary = ($emp['employee_type'] ?? 'probation') === 'probation' ? 20000 : 25000;
    $salary = $work_hours * $hour_salary;

    $stmt = $conn->prepare("INSERT INTO attendance(employee_id,work_date,check_in,check_out,work_hours,salary) VALUES(?,?,?,?,?,?)");
    $stmt->bind_param("isssdd", $eid, $work_date, $check_in, $check_out, $work_hours, $salary);
    $stmt->execute();
    header("Location: attendance.php"); exit;
}

$employees = $conn->query("SELECT * FROM employees ORDER BY name");
$list      = $conn->query("
    SELECT a.*, e.name emp_name, e.employee_type
    FROM attendance a
    LEFT JOIN employees e ON a.employee_id=e.id
    ORDER BY a.work_date DESC, a.id DESC
");
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
      <p>Ghi nhận giờ làm việc nhân viên</p>
    </div>
    <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
    <a href="salary_report.php" class="btn btn-secondary btn-sm">💰 Xem báo cáo lương</a>
    <?php endif; ?>
  </div>

  <!-- Form -->
  <div class="card" style="margin-bottom:14px;">
    <div class="section-title">➕ Thêm ca làm việc</div>
    <form method="post">
      <div class="form-row">
        <div class="form-group" style="flex:2;">
          <label class="form-label">Nhân viên</label>
          <select name="employee_id" class="form-select" required>
            <option value="">-- Chọn nhân viên --</option>
            <?php while ($e = $employees->fetch_assoc()): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['name']) ?></option>
            <?php endwhile; ?>
          </select>
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

  <!-- List -->
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Ngày</th>
          <th>Nhân viên</th>
          <th>Loại</th>
          <th>Giờ vào</th>
          <th>Giờ ra</th>
          <th class="text-right">Số giờ</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list->num_rows === 0): ?>
        <tr><td colspan="7"><div class="empty-state"><div class="empty-state-icon">🕒</div>Chưa có dữ liệu chấm công</div></td></tr>
        <?php else: while ($row = $list->fetch_assoc()): ?>
        <tr>
          <td class="fw-600"><?= date('d/m/Y', strtotime($row['work_date'])) ?></td>
          <td><?= htmlspecialchars($row['emp_name'] ?? '—') ?></td>
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
              <button type="submit" name="delete" value="<?= $row['id'] ?>" class="btn btn-sm btn-danger">🗑</button>
            </form>
          </td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
