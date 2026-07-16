<?php
include 'auth.php';
include 'config.php';

$list = $conn->query("SELECT * FROM cashbook_history ORDER BY report_date DESC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Lịch sử chốt ca — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>📚 Lịch sử chốt ca</h1>
      <p>Toàn bộ các ca đã được chốt</p>
    </div>
    <a href="cashbook.php" class="btn btn-primary btn-sm">📒 Ca hôm nay</a>
  </div>

  <div class="tbl-wrap">
    <table class="tbl">
      <thead>
        <tr>
          <th>Ngày</th>
          <th class="text-right">TM đầu ca</th>
          <th class="text-right">DT tiền mặt</th>
          <th class="text-right">DT chuyển khoản</th>
          <th class="text-right">Chi tiền</th>
          <th class="text-right">Nộp DT</th>
          <th class="text-right">TM cuối ca</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($list->num_rows === 0): ?>
        <tr><td colspan="7">
          <div class="empty-state">
            <div class="empty-state-icon">📚</div>
            Chưa có ca nào được chốt
          </div>
        </td></tr>
        <?php else:
        while ($row = $list->fetch_assoc()): ?>
        <tr>
          <td>
            <a href="cashbook.php?date=<?= $row['report_date'] ?>" class="fw-600 c-blue">
              <?= date('d/m/Y', strtotime($row['report_date'])) ?>
            </a>
          </td>
          <td class="text-right money"><?= number_format($row['opening_cash']) ?>đ</td>
          <td class="text-right money c-green"><?= number_format($row['cash_revenue']) ?>đ</td>
          <td class="text-right money c-blue"><?= number_format($row['transfer_revenue']) ?>đ</td>
          <td class="text-right money c-red"><?= number_format($row['expenses']) ?>đ</td>
          <td class="text-right money c-orange"><?= number_format($row['deposits']) ?>đ</td>
          <td class="text-right money fw-700 c-blue"><?= number_format($row['closing_cash']) ?>đ</td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
