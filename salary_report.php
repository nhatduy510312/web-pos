<?php
include 'auth.php';
include 'config.php';
requireRole('admin');

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');

/* BUG FIX: use prepared statement instead of interpolating $month */
$stmt = $conn->prepare("
    SELECT e.id, e.name, e.employee_type,
           COALESCE(SUM(a.work_hours), 0) total_hours,
           COALESCE(SUM(a.salary), 0) total_salary
    FROM employees e
    LEFT JOIN attendance a
      ON e.id = a.employee_id
      AND DATE_FORMAT(a.work_date, '%Y-%m') = ?
    GROUP BY e.id ORDER BY e.name
");
$stmt->bind_param("s", $month);
$stmt->execute();
$list = $stmt->get_result();

/* Totals */
$total_salary_all = 0;
$total_hours_all  = 0;
$rows = [];
while ($r = $list->fetch_assoc()) {
    $total_hours_all  += $r['total_hours'];
    $total_salary_all += $r['total_salary'];
    $rows[] = $r;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Báo cáo lương — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>💰 Báo cáo lương</h1>
      <p>Tháng <?= date('m/Y', strtotime($month . '-01')) ?></p>
    </div>
    <form method="get" class="flex gap-8">
      <input type="month" name="month" class="form-input" value="<?= $month ?>" style="width:auto;">
      <button type="submit" class="btn btn-primary">Xem</button>
    </form>
  </div>

  <!-- Summary -->
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-label">Tổng lương phải trả</div>
      <div class="stat-value c-green money"><?= number_format($total_salary_all) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tổng giờ làm việc</div>
      <div class="stat-value c-blue"><?= number_format($total_hours_all, 1) ?>h</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Số nhân viên</div>
      <div class="stat-value"><?= count($rows) ?></div>
    </div>
  </div>

  <!-- Detail table -->
  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Nhân viên</th>
          <th>Loại</th>
          <th>Lương/giờ</th>
          <th class="text-right">Tổng giờ</th>
          <th class="text-right">Lương tháng</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">💰</div>Chưa có dữ liệu tháng này</div></td></tr>
        <?php else: foreach ($rows as $row): $rate = $row['employee_type']==='official' ? 25000 : 20000; ?>
        <tr>
          <td class="fw-600"><?= htmlspecialchars($row['name']) ?></td>
          <td>
            <?php if ($row['employee_type']==='official'): ?>
            <span class="badge badge-green">Chính thức</span>
            <?php else: ?>
            <span class="badge badge-yellow">Thử việc</span>
            <?php endif; ?>
          </td>
          <td class="money"><?= number_format($rate) ?>đ/h</td>
          <td class="text-right"><?= number_format($row['total_hours'], 1) ?>h</td>
          <td class="text-right fw-700 money <?= $row['total_salary'] > 0 ? 'c-green' : 'text-muted' ?>">
            <?= number_format($row['total_salary']) ?>đ
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <?php if (!empty($rows)): ?>
      <tfoot>
        <tr style="background:var(--s50);">
          <td colspan="3" class="fw-700">Tổng cộng</td>
          <td class="text-right fw-700"><?= number_format($total_hours_all, 1) ?>h</td>
          <td class="text-right fw-700 c-green money"><?= number_format($total_salary_all) ?>đ</td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>

</div>
</body>
</html>
