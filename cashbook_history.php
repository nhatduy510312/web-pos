<?php
include 'auth.php';
include 'config.php';

$list = $conn->query("SELECT * FROM cashbook_history ORDER BY report_date DESC");
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Lịch sử chốt ca — GHÉ Coffee</title>
<style>
.cashbook-history { width:100%; min-width:980px; table-layout:fixed; }
.cashbook-history th { text-align:center !important; vertical-align:middle; white-space:nowrap; }
.cashbook-history thead tr:first-child th { padding-top:11px; padding-bottom:7px; }
.cashbook-history thead tr:last-child th { padding-top:5px; padding-bottom:9px; font-size:10px; }
.cashbook-history .date-cell { text-align:center; white-space:nowrap; }
.cashbook-history .money-cell { text-align:right; padding-right:20px; white-space:nowrap; font-variant-numeric:tabular-nums; }
.cashbook-history tbody tr:nth-child(even) { background:var(--s50); }
.cashbook-history tbody tr:hover { background:var(--p50); }
.cashbook-history td:nth-child(3),
.cashbook-history td:nth-child(5),
.cashbook-history td:nth-child(7),
.cashbook-history .group-start { border-left:1px solid var(--s200); }
.cashbook-history .closing-cell { background:rgba(37,99,235,.035); }
@media (max-width:1050px) {
  .cashbook-history .money-cell { padding-right:14px; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>📚 Lịch sử chốt ca</h1>
      <p>Toàn bộ các ca đã được chốt</p>
    </div>
    <div class="flex" style="flex-wrap:wrap">
      <?php if (($_SESSION['role']??'')==='admin'): ?><a href="expense_report.php" class="btn btn-secondary btn-sm">💸 Báo cáo tiền chi theo tháng</a><?php endif; ?>
      <a href="cashbook.php" class="btn btn-primary btn-sm">📒 Ca hôm nay</a>
    </div>
  </div>

  <div class="tbl-wrap">
    <table class="tbl cashbook-history">
      <colgroup>
        <col style="width:12%">
        <col style="width:14%">
        <col style="width:15%">
        <col style="width:16%">
        <col style="width:14%">
        <col style="width:14%">
        <col style="width:15%">
      </colgroup>
      <thead>
        <tr>
          <th rowspan="2">Ngày</th>
          <th rowspan="2">Tiền mặt<br>đầu ca</th>
          <th colspan="2" class="group-start">Doanh thu</th>
          <th rowspan="2" class="group-start">Chi tiền</th>
          <th rowspan="2">Nộp doanh thu</th>
          <th rowspan="2" class="group-start">Tiền mặt<br>cuối ca</th>
        </tr>
        <tr>
          <th>Tiền mặt</th>
          <th>Chuyển khoản</th>
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
          <td class="date-cell">
            <a href="cashbook.php?date=<?= $row['report_date'] ?>" class="fw-600 c-blue">
              <?= date('d/m/Y', strtotime($row['report_date'])) ?>
            </a>
          </td>
          <td class="money-cell money"><?= number_format($row['opening_cash']) ?>đ</td>
          <td class="money-cell money c-green"><?= number_format($row['cash_revenue']) ?>đ</td>
          <td class="money-cell money c-blue"><?= number_format($row['transfer_revenue']) ?>đ</td>
          <td class="money-cell money c-red"><?= number_format($row['expenses']) ?>đ</td>
          <td class="money-cell money c-orange"><?= number_format($row['deposits']) ?>đ</td>
          <td class="money-cell money fw-700 c-blue closing-cell"><?= number_format($row['closing_cash']) ?>đ</td>
        </tr>
        <?php endwhile; endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
