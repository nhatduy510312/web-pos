<?php
include 'auth.php';
include 'config.php';

$bakeryOrderJoin = "LEFT JOIN (
    SELECT oi.order_id,
           SUM(CASE WHEN LOWER(TRIM(c.name))='bakery' THEN oi.qty*oi.unit_price ELSE 0 END) bakery_gross,
           SUM(oi.qty*oi.unit_price) order_gross
    FROM order_items oi
    JOIN products p ON p.id=oi.product_id
    LEFT JOIN categories c ON c.id=p.category_id
    GROUP BY oi.order_id
) bakery_items ON bakery_items.order_id=o.id";
$bakeryRevenueSql = "CASE WHEN COALESCE(bakery_items.order_gross,0)>0
    THEN GREATEST(0, bakery_items.bakery_gross - LEAST(bakery_items.bakery_gross,
         COALESCE(o.discount_amount,0)*bakery_items.bakery_gross/bakery_items.order_gross))
    ELSE 0 END";
$bakeryPaymentTotalSql = "(COALESCE(o.cash_amount,0)+COALESCE(o.bank_amount,0))";
$bakeryCashSql = "CASE WHEN $bakeryPaymentTotalSql>0
    THEN ($bakeryRevenueSql)*COALESCE(o.cash_amount,0)/$bakeryPaymentTotalSql
    WHEN o.payment_method='cash' THEN ($bakeryRevenueSql) ELSE 0 END";
$bakeryBankSql = "CASE WHEN $bakeryPaymentTotalSql>0
    THEN ($bakeryRevenueSql)*COALESCE(o.bank_amount,0)/$bakeryPaymentTotalSql
    WHEN o.payment_method='bank' THEN ($bakeryRevenueSql) ELSE 0 END";

$today = $conn->query("
    SELECT COUNT(*) total_orders, IFNULL(SUM(total_amount),0) revenue
    FROM orders
    WHERE status='paid' AND paid_at>=CURDATE() AND paid_at<CURDATE()+INTERVAL 1 DAY
")->fetch_assoc();

$month = $conn->query("
    SELECT COUNT(*) total_orders, IFNULL(SUM(total_amount),0) revenue
    FROM orders WHERE status='paid'
    AND paid_at>=DATE_FORMAT(CURDATE(), '%Y-%m-01')
    AND paid_at<DATE_FORMAT(CURDATE()+INTERVAL 1 MONTH, '%Y-%m-01')
")->fetch_assoc();

$cash_today = $conn->query("
    SELECT IFNULL(SUM(cash_amount),0) t FROM orders
    WHERE status='paid' AND paid_at>=CURDATE() AND paid_at<CURDATE()+INTERVAL 1 DAY
")->fetch_assoc();

$bank_today = $conn->query("
    SELECT IFNULL(SUM(bank_amount),0) t FROM orders
    WHERE status='paid' AND paid_at>=CURDATE() AND paid_at<CURDATE()+INTERVAL 1 DAY
")->fetch_assoc();

$bakery_today = $conn->query("SELECT
    IFNULL(SUM($bakeryRevenueSql),0) revenue,
    IFNULL(SUM($bakeryCashSql),0) cash,
    IFNULL(SUM($bakeryBankSql),0) bank
    FROM orders o $bakeryOrderJoin
    WHERE o.status='paid' AND o.paid_at>=CURDATE() AND o.paid_at<CURDATE()+INTERVAL 1 DAY
")->fetch_assoc();

$bakery_month = $conn->query("SELECT IFNULL(SUM($bakeryRevenueSql),0) revenue
    FROM orders o $bakeryOrderJoin
    WHERE o.status='paid'
    AND o.paid_at>=DATE_FORMAT(CURDATE(), '%Y-%m-01')
    AND o.paid_at<DATE_FORMAT(CURDATE()+INTERVAL 1 MONTH, '%Y-%m-01')
")->fetch_assoc();

$cafe_today = [
    'revenue' => (float)$today['revenue'] - (float)$bakery_today['revenue'],
    'cash' => (float)$cash_today['t'] - (float)$bakery_today['cash'],
    'bank' => (float)$bank_today['t'] - (float)$bakery_today['bank'],
];
$cafe_month = (float)$month['revenue'] - (float)$bakery_month['revenue'];

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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
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

  <p class="section-title" style="margin-bottom:10px;color:var(--s400);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">BAKERY — HÔM NAY</p>
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-label">Doanh thu Bakery</div>
      <div class="stat-value c-green money"><?= number_format($bakery_today['revenue']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tiền mặt Bakery</div>
      <div class="stat-value c-orange money"><?= number_format($bakery_today['cash']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Chuyển khoản Bakery</div>
      <div class="stat-value c-purple money"><?= number_format($bakery_today['bank']) ?>đ</div>
    </div>
  </div>

  <p class="section-title" style="margin-bottom:10px;color:var(--s400);font-size:11px;text-transform:uppercase;letter-spacing:.06em;font-weight:700;">CAFE — HÔM NAY</p>
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-label">Doanh thu Cafe</div>
      <div class="stat-value c-green money"><?= number_format($cafe_today['revenue']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tiền mặt Cafe</div>
      <div class="stat-value c-orange money"><?= number_format($cafe_today['cash']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Chuyển khoản Cafe</div>
      <div class="stat-value c-purple money"><?= number_format($cafe_today['bank']) ?>đ</div>
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
    <div class="stat-card">
      <div class="stat-label">Doanh thu Bakery tháng</div>
      <div class="stat-value c-green money"><?= number_format($bakery_month['revenue']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Doanh thu Cafe tháng</div>
      <div class="stat-value c-green money"><?= number_format($cafe_month) ?>đ</div>
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
