<?php
include 'auth.php';
include 'config.php';

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) { header('Location: report.php'); exit; }

/* BUG FIX: use prepared statements instead of interpolating $order_id */
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
$stmt->bind_param("i", $order_id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) { header('Location: report.php'); exit; }

$stmt = $conn->prepare("
    SELECT oi.*, p.name FROM order_items oi
    LEFT JOIN products p ON oi.product_id=p.id
    WHERE oi.order_id=?
");
$stmt->bind_param("i", $order_id); $stmt->execute();
$items = $stmt->get_result();

$discount = (float)($order['discount_amount'] ?? 0);
$subtotal = $order['total_amount'] + $discount;

$pmLabel = match ($order['payment_method']) {
    'cash'  => '<span class="badge badge-green">💵 Tiền mặt</span>',
    'bank'  => '<span class="badge badge-blue">🏦 Chuyển khoản</span>',
    'mixed' => '<span class="badge badge-yellow">🔀 Hỗn hợp</span>',
    default => '<span class="badge badge-gray">' . htmlspecialchars($order['payment_method']) . '</span>',
};
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Hóa đơn #<?= $order_id ?> — GHÉ Coffee</title>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>Hóa đơn #<?= $order_id ?></h1>
      <p><?= date('d/m/Y H:i', strtotime($order['paid_at'] ?? $order['created_at'])) ?>
        <?php if ($order['customer_name']): ?>
        · 👤 <?= htmlspecialchars($order['customer_name']) ?>
        <?php endif; ?>
      </p>
    </div>
    <div class="flex gap-8">
      <a href="receipt.php?id=<?= $order_id ?>" target="_blank" class="btn btn-secondary btn-sm">🖨 In hóa đơn</a>
      <a href="report.php" class="btn btn-ghost btn-sm">← Quay lại</a>
    </div>
  </div>

  <div class="grid g2" style="gap:14px;">

    <!-- Items -->
    <div class="card">
      <div class="section-title">🛒 Danh sách món</div>
      <div class="tbl-wrap" style="margin-top:10px;">
        <table class="tbl">
          <thead>
            <tr>
              <th>Tên món</th>
              <th class="text-right" style="width:50px;">SL</th>
              <th class="text-right" style="width:100px;">Đơn giá</th>
              <th class="text-right" style="width:110px;">Thành tiền</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($item = $items->fetch_assoc()): ?>
            <tr>
              <td>
                <div class="fw-600"><?= htmlspecialchars($item['name']) ?></div>
                <?php if (!empty($item['note'])): ?>
                <div class="text-sm text-muted">↳ <?= htmlspecialchars($item['note']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-right"><?= $item['qty'] ?></td>
              <td class="text-right money"><?= number_format($item['unit_price']) ?>đ</td>
              <td class="text-right money fw-600"><?= number_format($item['qty'] * $item['unit_price']) ?>đ</td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Summary -->
    <div>
      <div class="card" style="margin-bottom:14px;">
        <div class="section-title">💰 Tổng kết</div>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:10px;font-size:14px;">
          <div class="flex-b">
            <span class="text-muted">Tổng trước giảm</span>
            <span class="fw-600 money"><?= number_format($subtotal) ?>đ</span>
          </div>
          <?php if ($discount > 0): ?>
          <div class="flex-b">
            <span class="text-muted">
              Giảm giá<?= $order['discount_type']==='percent' ? ' ('.$order['discount_value'].'%)' : '' ?>
            </span>
            <span class="fw-600 c-red money">-<?= number_format($discount) ?>đ</span>
          </div>
          <?php endif; ?>
          <div class="divider" style="margin:4px 0;"></div>
          <div class="flex-b">
            <span class="fw-700" style="font-size:15px;">Thanh toán</span>
            <span class="fw-700 c-blue money" style="font-size:22px;"><?= number_format($order['total_amount']) ?>đ</span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="section-title">🏦 Phương thức thanh toán</div>
        <div style="margin-top:10px;display:flex;flex-direction:column;gap:8px;font-size:13px;">
          <div><?= $pmLabel ?></div>
          <?php if ((float)$order['cash_amount'] > 0): ?>
          <div class="flex-b">
            <span class="text-muted">💵 Tiền mặt</span>
            <span class="fw-600 money"><?= number_format($order['cash_amount']) ?>đ</span>
          </div>
          <?php endif; ?>
          <?php if ((float)$order['bank_amount'] > 0): ?>
          <div class="flex-b">
            <span class="text-muted">🏦 Chuyển khoản</span>
            <span class="fw-600 money"><?= number_format($order['bank_amount']) ?>đ</span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

  </div>
</div>
</body>
</html>
