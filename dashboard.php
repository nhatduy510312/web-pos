<?php
include 'auth.php';
include 'config.php';

$today = $conn->query("
    SELECT COUNT(*) total_orders, IFNULL(SUM(total_amount),0) revenue
    FROM orders WHERE status='paid' AND DATE(paid_at)=CURDATE()
")->fetch_assoc();

$month = $conn->query("
    SELECT COUNT(*) total_orders, IFNULL(SUM(total_amount),0) revenue
    FROM orders WHERE status='paid'
    AND YEAR(paid_at)=YEAR(CURDATE()) AND MONTH(paid_at)=MONTH(CURDATE())
")->fetch_assoc();

$cash_today = $conn->query("
    SELECT IFNULL(SUM(cash_amount),0) t FROM orders
    WHERE status='paid' AND DATE(paid_at)=CURDATE()
")->fetch_assoc();

$bank_today = $conn->query("
    SELECT IFNULL(SUM(bank_amount),0) t FROM orders
    WHERE status='paid' AND DATE(paid_at)=CURDATE()
")->fetch_assoc();

$top_qty = $conn->query("
    SELECT p.name, SUM(oi.qty) qty
    FROM order_items oi
    JOIN products p ON oi.product_id=p.id
    JOIN orders o ON o.id=oi.order_id
    WHERE o.status='paid'
    GROUP BY p.id,p.name ORDER BY qty DESC LIMIT 10
");

$top_rev = $conn->query("
    SELECT p.name, SUM(oi.qty*oi.unit_price) revenue
    FROM order_items oi
    JOIN products p ON oi.product_id=p.id
    JOIN orders o ON o.id=oi.order_id
    WHERE o.status='paid'
    GROUP BY p.id,p.name ORDER BY revenue DESC LIMIT 10
");

$avg_today = $today['total_orders'] > 0 ? $today['revenue'] / $today['total_orders'] : 0;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Dashboard — GHÉ Coffee</title>
<style>
.rank-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 0;
  border-bottom: 1px solid var(--s100);
  font-size: 13px;
}
.rank-item:last-child { border-bottom: none; }
.rank-num {
  width: 22px; height: 22px;
  border-radius: 50%;
  background: var(--s100);
  color: var(--s500);
  font-size: 11px; font-weight: 700;
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0;
  margin-right: 10px;
}
.rank-num.top { background: var(--p); color: #fff; }
.rank-name { flex: 1; font-weight: 500; color: var(--s900); }
.rank-val { font-weight: 700; color: var(--p); font-size: 13px; }
</style>
</head>
<body>
<?php include 'menu.php'; ?>

<div class="page-content">
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>Dashboard</h1>
      <p>Tổng quan hoạt động quán — <?= date('d/m/Y') ?></p>
    </div>
    <a href="report.php" class="btn btn-secondary btn-sm">📈 Xem báo cáo chi tiết</a>
  </div>

  <!-- Stats today -->
  <p class="section-title" style="margin-bottom:10px;color:var(--s400);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">HÔM NAY</p>
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-label">Doanh thu</div>
      <div class="stat-value c-green money"><?= number_format($today['revenue']) ?>đ</div>
      <div class="stat-sub"><?= $today['total_orders'] ?> hóa đơn</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tiền mặt</div>
      <div class="stat-value c-orange money"><?= number_format($cash_today['t']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Chuyển khoản</div>
      <div class="stat-value c-purple money"><?= number_format($bank_today['t']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Trung bình / đơn</div>
      <div class="stat-value c-blue money"><?= number_format($avg_today) ?>đ</div>
    </div>
  </div>

  <!-- Stats month -->
  <p class="section-title" style="margin-bottom:10px;color:var(--s400);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">THÁNG <?= date('m/Y') ?></p>
  <div class="stats-grid" style="margin-bottom:28px;">
    <div class="stat-card">
      <div class="stat-label">Doanh thu tháng</div>
      <div class="stat-value c-green money"><?= number_format($month['revenue']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Số hóa đơn</div>
      <div class="stat-value c-blue"><?= number_format($month['total_orders']) ?></div>
    </div>
  </div>

  <!-- Top products -->
  <div class="grid g2" style="gap:14px;">
    <div class="card">
      <div class="section-title">🔥 Top 10 bán chạy nhất</div>
      <?php $r=1; while($row=$top_qty->fetch_assoc()): ?>
      <div class="rank-item">
        <div class="rank-num <?= $r<=3?'top':'' ?>"><?= $r++ ?></div>
        <span class="rank-name"><?= htmlspecialchars($row['name']) ?></span>
        <span class="rank-val"><?= number_format($row['qty']) ?></span>
      </div>
      <?php endwhile; ?>
    </div>

    <div class="card">
      <div class="section-title">💰 Top 10 theo doanh thu</div>
      <?php $r=1; while($row=$top_rev->fetch_assoc()): ?>
      <div class="rank-item">
        <div class="rank-num <?= $r<=3?'top':'' ?>"><?= $r++ ?></div>
        <span class="rank-name"><?= htmlspecialchars($row['name']) ?></span>
        <span class="rank-val money"><?= number_format($row['revenue']) ?>đ</span>
      </div>
      <?php endwhile; ?>
    </div>
  </div>
</div>
</body>
</html>
