<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

if (isset($_POST['delete'])) {
    $id   = (int)$_POST['id'];
    $stmt = $conn->prepare("DELETE FROM employees WHERE id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: employees.php"); exit;
}

if (isset($_POST['update_type'])) {
    $id   = (int)$_POST['id'];
    $type = in_array($_POST['employee_type'], ['probation', 'official']) ? $_POST['employee_type'] : 'probation';
    $stmt = $conn->prepare("UPDATE employees SET employee_type=? WHERE id=?");
    $stmt->bind_param("si", $type, $id); $stmt->execute();
    header("Location: employees.php"); exit;
}

if (isset($_POST['add'])) {
    $name = trim($_POST['name']);
    $type = in_array($_POST['employee_type'], ['probation', 'official']) ? $_POST['employee_type'] : 'probation';
    if ($name) {
        $stmt = $conn->prepare("INSERT INTO employees(name,employee_type) VALUES(?,?)");
        $stmt->bind_param("ss", $name, $type); $stmt->execute();
    }
    header("Location: employees.php"); exit;
}

$employees = $conn->query("SELECT * FROM employees ORDER BY name");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nhân viên — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>👥 Quản lý nhân viên</h1>
      <p>Chỉ admin mới có thể quản lý nhân viên</p>
    </div>
  </div>

  <div class="grid g2" style="gap:14px;margin-bottom:20px;">

    <!-- Add form -->
    <div class="card">
      <div class="section-title">➕ Thêm nhân viên</div>
      <form method="post">
        <div class="form-group">
          <label class="form-label">Tên nhân viên</label>
          <input type="text" name="name" class="form-input" placeholder="Nguyễn Văn A" required>
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

    <!-- Info card -->
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
    </div>

  </div>

  <!-- Employee table -->
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>#</th>
          <th>Tên nhân viên</th>
          <th>Loại</th>
          <th>Lương/giờ</th>
          <th>Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($employees->num_rows === 0): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">👥</div>Chưa có nhân viên nào</div></td></tr>
        <?php else: while ($row = $employees->fetch_assoc()): ?>
        <tr>
          <td class="text-muted"><?= $row['id'] ?></td>
          <td class="fw-600"><?= htmlspecialchars($row['name']) ?></td>
          <td>
            <form method="post" style="margin:0;">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <select name="employee_type" class="form-select" style="width:160px;font-size:12px;padding:5px 28px 5px 10px;"
                      onchange="this.form.submit()">
                <option value="probation" <?= $row['employee_type']==='probation'?'selected':'' ?>>Thử việc</option>
                <option value="official"  <?= $row['employee_type']==='official'?'selected':'' ?>>Chính thức</option>
              </select>
              <input type="hidden" name="update_type">
            </form>
          </td>
          <td class="fw-600 money">
            <?= $row['employee_type']==='official' ? '25,000' : '20,000' ?>đ/h
          </td>
          <td>
            <form method="post" onsubmit="return confirm('Xóa nhân viên <?= htmlspecialchars($row['name'],ENT_QUOTES) ?>?')" style="margin:0;">
              <input type="hidden" name="id" value="<?= $row['id'] ?>">
              <button type="submit" name="delete" class="btn btn-sm btn-danger">🗑 Xóa</button>
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
