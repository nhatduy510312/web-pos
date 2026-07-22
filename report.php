<?php
include 'auth.php';
include 'config.php';
requireRole('admin');

/* ── Validate date / time inputs ── */
$period = $_GET['period'] ?? 'today';

switch ($period) {
    case 'week':  $from = date('Y-m-d', strtotime('monday this week')); $to = date('Y-m-d'); break;
    case 'month': $from = date('Y-m-01'); $to = date('Y-m-d'); break;
    case 'year':  $from = date('Y-01-01'); $to = date('Y-m-d'); break;
    case 'last_year':
        $from = date('Y-01-01', strtotime('first day of january last year'));
        $to = date('Y-12-31', strtotime('last day of december last year'));
        break;
    case 'custom':
        $from = $_GET['from'] ?? date('Y-m-d');
        $to   = $_GET['to']   ?? date('Y-m-d');
        break;
    default: $from = $to = date('Y-m-d');
}

$from_time = $_GET['from_time'] ?? '00:00';
$to_time   = $_GET['to_time']   ?? '23:59';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d');
if (!preg_match('/^\d{2}:\d{2}$/', $from_time))   $from_time = '00:00';
if (!preg_match('/^\d{2}:\d{2}$/', $to_time))     $to_time   = '23:59';

$dt_from = $from . ' ' . $from_time . ':00';
$dt_to   = $to   . ' ' . $to_time   . ':59';
$is_grouped = in_array($period, ['week', 'month', 'year', 'last_year'], true);
$group_by_month = in_array($period, ['year', 'last_year'], true);

/* ── BUG FIX: use prepared statements instead of interpolation ── */
$stmt = $conn->prepare("
    SELECT COUNT(*) total_orders, IFNULL(SUM(total_amount),0) revenue
    FROM orders WHERE status='paid' AND paid_at BETWEEN ? AND ?
");
$stmt->bind_param("ss", $dt_from, $dt_to);
$stmt->execute();
$summary = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT IFNULL(SUM(cash_amount),0) t FROM orders WHERE status='paid' AND paid_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $dt_from, $dt_to);
$stmt->execute();
$cash = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT IFNULL(SUM(bank_amount),0) t FROM orders WHERE status='paid' AND paid_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $dt_from, $dt_to);
$stmt->execute();
$bank = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT IFNULL(SUM(discount_amount),0) t FROM orders WHERE status='paid' AND paid_at BETWEEN ? AND ?");
$stmt->bind_param("ss", $dt_from, $dt_to);
$stmt->execute();
$disc_total = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("
    SELECT p.name, SUM(oi.qty) qty
    FROM order_items oi
    JOIN products p ON p.id=oi.product_id
    JOIN orders o ON o.id=oi.order_id
    WHERE o.status='paid' AND o.paid_at BETWEEN ? AND ?
    GROUP BY p.id,p.name ORDER BY qty DESC LIMIT 10
");
$stmt->bind_param("ss", $dt_from, $dt_to);
$stmt->execute();
$top_products = $stmt->get_result();

/* Sort for invoice list */
$sort_map = ['payment' => 'payment_method ASC, id DESC', 'time' => 'paid_at DESC', 'id' => 'id DESC'];
$sort     = array_key_exists($_GET['sort'] ?? '', $sort_map) ? $_GET['sort'] : 'id';
$order_by = $sort_map[$sort];

$orders = null;
$revenue_groups = [];

if ($is_grouped) {
    $group_expression = $group_by_month ? "DATE_FORMAT(paid_at, '%Y-%m')" : 'DATE(paid_at)';
    $stmt = $conn->prepare("
        SELECT $group_expression period_key,
               COUNT(*) total_orders,
               IFNULL(SUM(total_amount), 0) revenue,
               IFNULL(SUM(cash_amount), 0) cash_total,
               IFNULL(SUM(bank_amount), 0) bank_total,
               IFNULL(SUM(discount_amount), 0) discount_total
        FROM orders
        WHERE status='paid' AND paid_at BETWEEN ? AND ?
        GROUP BY period_key
        ORDER BY period_key ASC
    ");
    $stmt->bind_param("ss", $dt_from, $dt_to);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $revenue_groups[$row['period_key']] = $row;
    }

    $cursor = new DateTime($group_by_month ? date('Y-m-01', strtotime($from)) : $from);
    $end_cursor = new DateTime($group_by_month ? date('Y-m-01', strtotime($to)) : $to);
    $step = new DateInterval($group_by_month ? 'P1M' : 'P1D');
    while ($cursor <= $end_cursor) {
        $key = $cursor->format($group_by_month ? 'Y-m' : 'Y-m-d');
        if (!isset($revenue_groups[$key])) {
            $revenue_groups[$key] = [
                'period_key' => $key,
                'total_orders' => 0,
                'revenue' => 0,
                'cash_total' => 0,
                'bank_total' => 0,
                'discount_total' => 0,
            ];
        }
        $cursor->add($step);
    }
    ksort($revenue_groups);
} else {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE status='paid' AND paid_at BETWEEN ? AND ? ORDER BY $order_by");
    $stmt->bind_param("ss", $dt_from, $dt_to);
    $stmt->execute();
    $orders = $stmt->get_result();
}

$total_orders = $summary['total_orders'];
$revenue      = $summary['revenue'];
$avg_order    = $total_orders > 0 ? $revenue / $total_orders : 0;

/* ── Payment method label helper ── */
function pmLabel($m) {
    return match($m) {
        'cash'  => '<span class="badge badge-green">💵 Tiền mặt</span>',
        'bank'  => '<span class="badge badge-blue">🏦 Chuyển khoản</span>',
        'mixed' => '<span class="badge badge-yellow">🔀 Hỗn hợp</span>',
        default => '<span class="badge badge-gray">' . htmlspecialchars($m) . '</span>',
    };
}

/* Load one day's invoices on demand for the revenue accordion. */
if (isset($_GET['ajax_day'])) {
    $ajax_day = (string)$_GET['ajax_day'];
    $parsed_day = DateTime::createFromFormat('!Y-m-d', $ajax_day);
    if (!$parsed_day || $parsed_day->format('Y-m-d') !== $ajax_day) {
        http_response_code(400);
        echo '<div class="day-orders-error">Ngày không hợp lệ.</div>';
        exit;
    }

    $day_from = $ajax_day . ' 00:00:00';
    $day_to = (clone $parsed_day)->modify('+1 day')->format('Y-m-d') . ' 00:00:00';
    $day_stmt = $conn->prepare("SELECT * FROM orders WHERE status='paid' AND paid_at >= ? AND paid_at < ? ORDER BY paid_at DESC, id DESC");
    $day_stmt->bind_param('ss', $day_from, $day_to);
    $day_stmt->execute();
    $day_orders = $day_stmt->get_result();
    ?>
    <div class="day-orders-heading">
      <span>📋 Hóa đơn ngày <?= date('d/m/Y', strtotime($ajax_day)) ?></span>
      <span class="badge badge-blue"><?= number_format($day_orders->num_rows) ?> hóa đơn</span>
    </div>
    <?php if ($day_orders->num_rows === 0): ?>
      <div class="day-orders-empty">Ngày này chưa có hóa đơn đã thanh toán.</div>
    <?php else: ?>
      <div class="day-orders-list">
      <?php while ($day_order = $day_orders->fetch_assoc()):
          $item_stmt = $conn->prepare("SELECT oi.qty,oi.unit_price,oi.note,p.name FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
          $item_stmt->bind_param('i', $day_order['id']);
          $item_stmt->execute();
          $day_items = $item_stmt->get_result();
      ?>
        <div class="day-order-card">
          <div class="day-order-head">
            <div class="day-order-main">
              <a href="receipt.php?id=<?= (int)$day_order['id'] ?>" target="_blank" class="fw-600 c-blue">#<?= (int)$day_order['id'] ?></a>
              <span><?= $day_order['customer_name'] ? htmlspecialchars($day_order['customer_name']) : '—' ?></span>
              <?= pmLabel($day_order['payment_method']) ?>
            </div>
            <div class="day-order-meta">
              <span><?= date('H:i', strtotime($day_order['paid_at'])) ?></span>
              <strong class="money"><?= number_format($day_order['total_amount']) ?>đ</strong>
            </div>
          </div>
          <div class="day-order-body">
            <table class="detail-tbl">
              <thead><tr><th>Tên món</th><th>SL</th><th>Đơn giá</th><th class="text-right">Thành tiền</th></tr></thead>
              <tbody>
                <?php while ($day_item = $day_items->fetch_assoc()): ?>
                <tr>
                  <td>
                    <?= htmlspecialchars($day_item['name'] ?? 'Món đã xóa') ?>
                    <?php if ($day_item['note']): ?><div class="text-muted text-sm">↳ <?= htmlspecialchars($day_item['note']) ?></div><?php endif; ?>
                  </td>
                  <td><?= number_format($day_item['qty']) ?></td>
                  <td class="money"><?= number_format($day_item['unit_price']) ?>đ</td>
                  <td class="text-right money fw-600"><?= number_format($day_item['qty'] * $day_item['unit_price']) ?>đ</td>
                </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
            <div class="day-order-totals">
              <span>💵 Tiền mặt: <b class="money"><?= number_format($day_order['cash_amount']) ?>đ</b></span>
              <span>🏦 Chuyển khoản: <b class="money"><?= number_format($day_order['bank_amount']) ?>đ</b></span>
              <?php if ((float)$day_order['discount_amount'] > 0): ?>
                <span class="c-red">🎁 Giảm giá: <b class="money">-<?= number_format($day_order['discount_amount']) ?>đ</b></span>
              <?php endif; ?>
              <span>✅ Tổng: <b class="money"><?= number_format($day_order['total_amount']) ?>đ</b></span>
            </div>
          </div>
        </div>
      <?php endwhile; ?>
      </div>
    <?php endif; ?>
    <?php
    exit;
}

/* Load a month's daily revenue on demand for yearly reports. */
if (isset($_GET['ajax_month'])) {
    $ajax_month = (string)$_GET['ajax_month'];
    $parsed_month = DateTime::createFromFormat('!Y-m', $ajax_month);
    if (!$parsed_month || $parsed_month->format('Y-m') !== $ajax_month) {
        http_response_code(400);
        echo '<div class="day-orders-error">Tháng không hợp lệ.</div>';
        exit;
    }

    $month_start = (clone $parsed_month)->modify('first day of this month');
    $month_end = (clone $parsed_month)->modify('last day of this month');
    if ($ajax_month === date('Y-m')) {
        $month_end = new DateTime('today');
    }
    $month_from = $month_start->format('Y-m-d') . ' 00:00:00';
    $month_to = (clone $month_end)->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

    $month_stmt = $conn->prepare("
        SELECT DATE(paid_at) period_key,
               COUNT(*) total_orders,
               IFNULL(SUM(total_amount), 0) revenue,
               IFNULL(SUM(cash_amount), 0) cash_total,
               IFNULL(SUM(bank_amount), 0) bank_total,
               IFNULL(SUM(discount_amount), 0) discount_total
        FROM orders
        WHERE status='paid' AND paid_at >= ? AND paid_at < ?
        GROUP BY period_key
        ORDER BY period_key ASC
    ");
    $month_stmt->bind_param('ss', $month_from, $month_to);
    $month_stmt->execute();
    $month_result = $month_stmt->get_result();
    $month_days = [];
    while ($month_row = $month_result->fetch_assoc()) {
        $month_days[$month_row['period_key']] = $month_row;
    }

    $day_cursor = clone $month_start;
    while ($day_cursor <= $month_end) {
        $day_key = $day_cursor->format('Y-m-d');
        if (!isset($month_days[$day_key])) {
            $month_days[$day_key] = [
                'period_key' => $day_key,
                'total_orders' => 0,
                'revenue' => 0,
                'cash_total' => 0,
                'bank_total' => 0,
                'discount_total' => 0,
            ];
        }
        $day_cursor->modify('+1 day');
    }
    ksort($month_days);
    ?>
    <div class="day-orders-heading">
      <span>📊 Doanh thu từng ngày — Tháng <?= date('m/Y', strtotime($ajax_month . '-01')) ?></span>
      <span class="badge badge-blue"><?= number_format(count($month_days)) ?> ngày</span>
    </div>
    <div class="tbl-wrap month-days-wrap">
      <table class="tbl revenue-summary month-days-table">
        <thead>
          <tr><th>Ngày</th><th>Số hóa đơn</th><th>Doanh thu</th><th>Tiền mặt</th><th>Chuyển khoản</th><th>Giảm giá</th></tr>
        </thead>
        <tbody>
          <?php foreach ($month_days as $month_day): ?>
          <tr class="day-summary-row">
            <td>
              <button type="button" class="revenue-period-link day-toggle"
                      data-day="<?= htmlspecialchars($month_day['period_key']) ?>"
                      aria-expanded="false"
                      aria-controls="day-detail-<?= htmlspecialchars($month_day['period_key']) ?>">
                <?= date('d/m/Y', strtotime($month_day['period_key'])) ?>
              </button>
            </td>
            <td><?= number_format($month_day['total_orders']) ?></td>
            <td class="money fw-600 c-green"><?= number_format($month_day['revenue']) ?>đ</td>
            <td class="money"><?= number_format($month_day['cash_total']) ?>đ</td>
            <td class="money"><?= number_format($month_day['bank_total']) ?>đ</td>
            <td class="money c-red"><?= number_format($month_day['discount_total']) ?>đ</td>
          </tr>
          <tr class="day-detail-row" id="day-detail-<?= htmlspecialchars($month_day['period_key']) ?>">
            <td colspan="6"><div class="day-detail-content"></div></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Báo cáo — GHÉ Coffee</title>
<style>
.filter-pills { display:flex; gap:6px; flex-wrap:wrap; margin-bottom:14px; }
.filter-pill {
  padding:6px 14px; border-radius:999px; font-size:12px; font-weight:600;
  background:var(--white); color:var(--s500); border:1px solid var(--s200);
  cursor:pointer; transition:var(--tr); text-decoration:none; display:inline-block;
}
.filter-pill:hover { background:var(--s100); color:var(--s700); }
.filter-pill.active { background:var(--p); color:#fff; border-color:var(--p); }

.detail-row { display:none; background:var(--s50); }
.detail-row td { padding:0 !important; }
.detail-inner { padding:14px 16px; border-top:1px solid var(--s200); }
.detail-tbl { width:100%; border-collapse:collapse; font-size:12px; }
.detail-tbl th { color:var(--s400); font-weight:700; text-transform:uppercase; letter-spacing:.04em; font-size:10px; padding:6px 0; border-bottom:1px solid var(--s200); }
.detail-tbl td { padding:7px 0; border-bottom:1px solid var(--s100); color:var(--s700); }
.detail-tbl tbody tr:last-child td { border:none; }
.detail-foot { margin-top:10px; font-size:12px; line-height:2; }
.revenue-summary { width:100%; table-layout:fixed; }
.revenue-summary > thead > tr > th,
.revenue-summary > tbody > tr > td { text-align:center !important; vertical-align:middle; }
.revenue-summary > tbody > tr.day-summary-row:hover,
.revenue-summary > tbody > tr.month-summary-row:hover { background:var(--s50); }
.revenue-period-link {
  color:var(--p); font-weight:700; text-decoration:none;
  display:inline-flex; align-items:center; justify-content:center;
  gap:6px; padding:5px 10px; border:0; border-radius:7px;
  background:transparent; font:inherit; cursor:pointer; transition:var(--tr);
}
.revenue-period-link:hover { background:var(--p50); text-decoration:underline; }
.revenue-period-link::after { content:'▾'; font-size:11px; transition:transform .2s ease; }
.revenue-period-link[aria-expanded="true"]::after { transform:rotate(180deg); }
.day-detail-row { display:none; background:var(--s50); }
.month-detail-row { display:none; background:var(--s50); }
.revenue-summary > tbody > tr.day-detail-row > td,
.revenue-summary > tbody > tr.month-detail-row > td { padding:0 !important; text-align:left !important; }
.day-detail-content { padding:14px; border-top:1px solid var(--s200); }
.month-detail-content { padding:14px; border-top:1px solid var(--s200); }
.month-days-wrap { margin:0; }
.month-days-table { background:var(--white); }
.day-orders-heading { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:12px; font-weight:700; }
.day-orders-list { display:grid; gap:12px; }
.day-order-card { overflow:hidden; background:var(--white); border:1px solid var(--s200); border-radius:10px; }
.day-order-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:10px 12px; border-bottom:1px solid var(--s200); }
.day-order-main, .day-order-meta, .day-order-totals { display:flex; align-items:center; flex-wrap:wrap; gap:12px; }
.day-order-meta { justify-content:flex-end; color:var(--s500); }
.day-order-body { padding:10px 12px 12px; }
.day-order-totals { margin-top:10px; font-size:12px; }
.day-orders-empty, .day-orders-error { padding:18px; text-align:center; color:var(--s500); }
@media (max-width:700px) {
  .day-order-head { align-items:flex-start; flex-direction:column; }
  .day-order-meta { justify-content:flex-start; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>📈 Báo cáo doanh thu</h1>
      <p><?= date('d/m/Y H:i', strtotime($dt_from)) ?> → <?= date('d/m/Y H:i', strtotime($dt_to)) ?></p>
    </div>
    <a class="btn btn-secondary btn-sm no-print"
       href="export_excel.php?from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>">
      📊 Xuất Excel
    </a>
  </div>

  <!-- Period pills -->
  <div class="filter-pills no-print">
    <?php foreach (['today'=>'Hôm nay','week'=>'Tuần này','month'=>'Tháng này','year'=>'Năm nay','last_year'=>'Năm trước'] as $k=>$v): ?>
    <a href="?period=<?= $k ?>" class="filter-pill <?= $period===$k?'active':'' ?>"><?= $v ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Custom date form -->
  <div class="card no-print" style="margin-bottom:16px;">
    <form method="get" class="form-row" style="flex-wrap:wrap;gap:10px;align-items:flex-end;">
      <input type="hidden" name="period" value="custom">
      <div class="form-group">
        <label class="form-label">Từ ngày</label>
        <input type="date" name="from" class="form-input" value="<?= $from ?>" style="width:auto;">
      </div>
      <div class="form-group">
        <label class="form-label">Giờ</label>
        <input type="time" name="from_time" class="form-input" value="<?= $from_time ?>" style="width:auto;">
      </div>
      <div class="form-group">
        <label class="form-label">Đến ngày</label>
        <input type="date" name="to" class="form-input" value="<?= $to ?>" style="width:auto;">
      </div>
      <div class="form-group">
        <label class="form-label">Giờ</label>
        <input type="time" name="to_time" class="form-input" value="<?= $to_time ?>" style="width:auto;">
      </div>
      <button type="submit" class="btn btn-primary" style="height:38px;">Xem</button>
    </form>
  </div>

  <!-- Stats -->
  <div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-card">
      <div class="stat-label">Doanh thu</div>
      <div class="stat-value c-green money"><?= number_format($revenue) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Số hóa đơn</div>
      <div class="stat-value c-blue"><?= number_format($total_orders) ?></div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Trung bình / đơn</div>
      <div class="stat-value money"><?= number_format($avg_order) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tiền mặt</div>
      <div class="stat-value c-orange money"><?= number_format($cash['t']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Chuyển khoản</div>
      <div class="stat-value c-purple money"><?= number_format($bank['t']) ?>đ</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Tổng giảm giá</div>
      <div class="stat-value c-red money"><?= number_format($disc_total['t']) ?>đ</div>
    </div>
  </div>

  <?php if ($is_grouped): ?>
  <!-- Revenue grouped by day/month -->
  <div class="flex-b no-print" style="margin-bottom:10px;">
    <div class="section-title" style="margin-bottom:0;">
      📊 Doanh thu theo <?= $group_by_month ? 'tháng' : 'ngày' ?>
    </div>
  </div>

  <div class="tbl-wrap" style="margin-bottom:20px;">
    <table class="tbl revenue-summary">
      <thead>
        <tr>
          <th><?= $group_by_month ? 'Tháng' : 'Ngày' ?></th>
          <th class="text-right">Số hóa đơn</th>
          <th class="text-right">Doanh thu</th>
          <th class="text-right">Tiền mặt</th>
          <th class="text-right">Chuyển khoản</th>
          <th class="text-right">Giảm giá</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($revenue_groups as $group): ?>
        <tr class="<?= $group_by_month ? 'month-summary-row' : 'day-summary-row' ?>">
          <td class="fw-600">
            <?php if ($group_by_month): ?>
              <button type="button" class="revenue-period-link month-toggle"
                 data-month="<?= htmlspecialchars($group['period_key']) ?>"
                 aria-expanded="false"
                 aria-controls="month-detail-<?= htmlspecialchars($group['period_key']) ?>"
                 title="Xem doanh thu từng ngày trong tháng <?= date('m/Y', strtotime($group['period_key'] . '-01')) ?>">
                Tháng <?= date('m/Y', strtotime($group['period_key'] . '-01')) ?>
              </button>
            <?php else: ?>
              <button type="button" class="revenue-period-link day-toggle"
                 data-day="<?= htmlspecialchars($group['period_key']) ?>"
                 aria-expanded="false"
                 aria-controls="day-detail-<?= htmlspecialchars($group['period_key']) ?>"
                 title="Xem danh sách hóa đơn ngày <?= date('d/m/Y', strtotime($group['period_key'])) ?>">
                <?= date('d/m/Y', strtotime($group['period_key'])) ?>
              </button>
            <?php endif; ?>
          </td>
          <td class="text-right"><?= number_format($group['total_orders']) ?></td>
          <td class="text-right money fw-600 c-green"><?= number_format($group['revenue']) ?>đ</td>
          <td class="text-right money"><?= number_format($group['cash_total']) ?>đ</td>
          <td class="text-right money"><?= number_format($group['bank_total']) ?>đ</td>
          <td class="text-right money c-red"><?= number_format($group['discount_total']) ?>đ</td>
        </tr>
        <?php if ($group_by_month): ?>
        <tr class="month-detail-row" id="month-detail-<?= htmlspecialchars($group['period_key']) ?>">
          <td colspan="6"><div class="month-detail-content"></div></td>
        </tr>
        <?php else: ?>
        <tr class="day-detail-row" id="day-detail-<?= htmlspecialchars($group['period_key']) ?>">
          <td colspan="6"><div class="day-detail-content"></div></td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>

  <!-- Invoice table -->
  <div class="flex-b no-print" style="margin-bottom:10px;">
    <div class="section-title" style="margin-bottom:0;">📋 Danh sách hóa đơn</div>
    <div class="flex gap-8">
      <span style="font-size:12px;color:var(--s500);font-weight:600;">Sắp xếp:</span>
      <select class="form-select" style="width:auto;padding:5px 28px 5px 10px;font-size:12px;"
              onchange="location.href=this.value">
        <option value="?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&sort=id"       <?=$sort==='id'?'selected':''?>>Theo ID</option>
        <option value="?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&sort=payment"  <?=$sort==='payment'?'selected':''?>>Theo thanh toán</option>
        <option value="?from=<?=urlencode($from)?>&to=<?=urlencode($to)?>&sort=time"     <?=$sort==='time'?'selected':''?>>Theo thời gian</option>
      </select>
    </div>
  </div>

  <div class="tbl-wrap" style="margin-bottom:20px;">
    <table class="tbl">
      <thead>
        <tr>
          <th>ID</th>
          <th>Khách</th>
          <th>Tổng tiền</th>
          <th>Thanh toán</th>
          <th>Thời gian</th>
          <th class="no-print">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php while ($row = $orders->fetch_assoc()):
            $cname = $row['customer_name'] ? htmlspecialchars($row['customer_name']) : '—';
        ?>
        <tr>
          <td>
            <a href="#" class="toggle-order fw-600 c-blue" data-id="<?= $row['id'] ?>">
              #<?= $row['id'] ?>
            </a>
          </td>
          <td><?= $cname ?></td>
          <td class="money fw-600"><?= number_format($row['total_amount']) ?>đ</td>
          <td><?= pmLabel($row['payment_method']) ?></td>
          <td class="text-sm text-muted"><?= date('d/m H:i', strtotime($row['paid_at'])) ?></td>
          <td class="no-print">
            <div class="flex gap-4">
              <a href="receipt.php?id=<?= $row['id'] ?>" target="_blank" class="btn btn-sm btn-secondary">🖨</a>
              <a href="order_action.php?action=edit&id=<?= $row['id'] ?>" class="btn btn-sm btn-secondary">✏️</a>
              <?php if (($_SESSION['role'] ?? '') === 'admin'): ?>
              <a href="#" class="btn btn-sm btn-danger"
                 onclick="if(confirm('Huỷ hóa đơn #<?= $row['id'] ?>?')){
                   const f=document.createElement('form');f.method='post';
                   f.action='order_action.php?action=delete&id=<?= $row['id'] ?>';
                   const c=document.createElement('input');c.type='hidden';
                   c.name='csrf_token';c.value='<?= csrf_token() ?>';
                   f.appendChild(c);document.body.appendChild(f);f.submit();
                 }return false;">🗑</a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <tr class="detail-row" id="detail-<?= $row['id'] ?>">
          <td colspan="6">
            <div class="detail-inner">
              <?php
              $stmt2 = $conn->prepare("SELECT oi.qty,oi.unit_price,oi.note,p.name FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=?");
              $stmt2->bind_param("i", $row['id']);
              $stmt2->execute();
              $items = $stmt2->get_result();
              ?>
              <table class="detail-tbl">
                <thead><tr><th>Tên món</th><th>SL</th><th>Đơn giá</th><th class="text-right">Thành tiền</th></tr></thead>
                <tbody>
                  <?php while ($item = $items->fetch_assoc()): ?>
                  <tr>
                    <td>
                      <?= htmlspecialchars($item['name']) ?>
                      <?php if ($item['note']): ?>
                        <div class="text-muted text-sm">↳ <?= htmlspecialchars($item['note']) ?></div>
                      <?php endif; ?>
                    </td>
                    <td><?= $item['qty'] ?></td>
                    <td class="money"><?= number_format($item['unit_price']) ?>đ</td>
                    <td class="text-right money fw-600"><?= number_format($item['qty']*$item['unit_price']) ?>đ</td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
              <div class="detail-foot">
                <div>💵 Tiền mặt: <b class="money"><?= number_format($row['cash_amount']) ?>đ</b></div>
                <div>🏦 Chuyển khoản: <b class="money"><?= number_format($row['bank_amount']) ?>đ</b></div>
                <?php if ((float)$row['discount_amount'] > 0): ?>
                <div style="color:var(--err);">🎁 Giảm giá: <b class="money">-<?= number_format($row['discount_amount']) ?>đ<?= $row['discount_type']==='percent' ? ' ('.$row['discount_value'].'%)' : '' ?></b></div>
                <?php endif; ?>
                <div><b>✅ Tổng: <span class="money"><?= number_format($row['total_amount']) ?>đ</span></b></div>
              </div>
            </div>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <!-- Top products -->
  <div class="card">
    <div class="section-title">🏆 Top 10 món bán chạy</div>
    <div class="tbl-wrap">
      <table class="tbl">
        <thead><tr><th>#</th><th>Tên món</th><th class="text-right">Số lượng</th></tr></thead>
        <tbody>
          <?php $stt=1; while($row=$top_products->fetch_assoc()): ?>
          <tr>
            <td><span class="badge <?= $stt<=3?'badge-blue':'badge-gray' ?>"><?= $stt++ ?></span></td>
            <td><?= htmlspecialchars($row['name']) ?></td>
            <td class="text-right fw-600"><?= number_format($row['qty']) ?></td>
          </tr>
          <?php endwhile; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<script>
document.querySelectorAll('.toggle-order').forEach(btn => {
  btn.addEventListener('click', e => {
    e.preventDefault();
    const row = document.getElementById('detail-' + btn.dataset.id);
    row.style.display = row.style.display === 'table-row' ? 'none' : 'table-row';
  });
});

async function toggleRevenueDetail(btn, type) {
  const value = type === 'month' ? btn.dataset.month : btn.dataset.day;
  const row = document.getElementById(type + '-detail-' + value);
  const content = row.querySelector('.' + type + '-detail-content');
  const isOpen = row.style.display === 'table-row';

  if (isOpen) {
    row.style.display = 'none';
    btn.setAttribute('aria-expanded', 'false');
    return;
  }

  row.style.display = 'table-row';
  btn.setAttribute('aria-expanded', 'true');
  if (row.dataset.loaded === 'true') return;

  const loadingText = type === 'month'
    ? 'Đang tải doanh thu từng ngày...'
    : 'Đang tải danh sách hóa đơn...';
  content.innerHTML = '<div class="day-orders-empty">' + loadingText + '</div>';

  try {
    const parameter = type === 'month' ? 'ajax_month' : 'ajax_day';
    const response = await fetch('report.php?' + parameter + '=' + encodeURIComponent(value), {
      headers: {'X-Requested-With': 'XMLHttpRequest'}
    });
    if (!response.ok) throw new Error('HTTP ' + response.status);
    content.innerHTML = await response.text();
    row.dataset.loaded = 'true';
  } catch (error) {
    content.innerHTML = '<div class="day-orders-error">Không thể tải dữ liệu. Vui lòng thử lại.</div>';
  }
}

document.addEventListener('click', event => {
  const dayBtn = event.target.closest('.day-toggle');
  if (dayBtn) {
    toggleRevenueDetail(dayBtn, 'day');
    return;
  }

  const monthBtn = event.target.closest('.month-toggle');
  if (monthBtn) toggleRevenueDetail(monthBtn, 'month');
});
</script>
</body>
</html>
