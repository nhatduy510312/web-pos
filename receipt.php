<?php
include 'auth.php';
include 'config.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('Hóa đơn không hợp lệ');

/* BUG FIX: use prepared statements instead of string interpolation */
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=?");
$stmt->bind_param("i", $id); $stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
if (!$order) die('Không tìm thấy hóa đơn');

$stmt = $conn->prepare("
    SELECT oi.*, p.name FROM order_items oi
    LEFT JOIN products p ON oi.product_id=p.id
    WHERE oi.order_id=?
");
$stmt->bind_param("i", $id); $stmt->execute();
$items = $stmt->get_result();

$discount = (float)($order['discount_amount'] ?? 0);
$subtotal = $order['total_amount'] + $discount;
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<title>Hóa đơn #<?= $id ?></title>
<style>
@page { size: 80mm auto; margin: 0; }
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
  width: 80mm;
  margin: auto;
  font-family: 'Courier New', Courier, monospace;
  font-size: 12px;
  padding: 12px 10px 20px;
  color: #000;
  background: #fff;
}
.center { text-align: center; }
.right  { text-align: right; }
.bold   { font-weight: bold; }
.lg     { font-size: 14px; }
.xl     { font-size: 16px; }
hr.dashed { border: none; border-top: 1px dashed #000; margin: 8px 0; }
hr.solid  { border: none; border-top: 1px solid #000; margin: 8px 0; }
table { width: 100%; border-collapse: collapse; }
td    { padding: 3px 0; vertical-align: top; }
.shop-name { font-size: 17px; font-weight: bold; letter-spacing: .05em; }
.inv-label { font-size: 11px; letter-spacing: .08em; margin-top: 3px; }
.item-row td:first-child { width: 60%; }
.item-row td:last-child  { text-align: right; }
.item-note { font-size: 10px; color: #555; padding-left: 8px; }
.footer { text-align: center; font-size: 11px; letter-spacing: .04em; margin-top: 10px; line-height: 1.8; }

/* Screen preview */
@media screen {
  body {
    box-shadow: 0 0 20px rgba(0,0,0,.15);
    margin: 20px auto;
    padding: 16px;
    border-radius: 4px;
  }
  .print-btn {
    display: block;
    width: 80mm;
    margin: 0 auto 12px;
    padding: 10px;
    background: #2563eb;
    color: #fff;
    font-size: 14px;
    font-weight: 700;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-family: sans-serif;
  }
}
@media print {
  .print-btn { display: none; }
}
</style>
</head>
<body>

<button class="print-btn no-print" onclick="window.print()">🖨️ In hóa đơn</button>

<!-- Header -->
<div class="center">
  <div class="shop-name">GHÉ COFFEE AND TEA</div>
  <div class="inv-label">HÓA ĐƠN BÁN HÀNG</div>
  <div style="margin-top:4px;font-size:11px;">Mã HĐ: #<?= $id ?></div>
  <div style="font-size:11px;"><?= date('d/m/Y H:i', strtotime($order['paid_at'] ?? $order['created_at'])) ?></div>
  <?php if (!empty($order['customer_name'])): ?>
  <div style="font-size:11px;margin-top:2px;">👤 <?= htmlspecialchars($order['customer_name']) ?></div>
  <?php endif; ?>
</div>

<hr class="dashed">

<!-- Items -->
<table>
  <?php while ($item = $items->fetch_assoc()):
    $amt = $item['qty'] * $item['unit_price'];
  ?>
  <tr class="item-row">
    <td>
      <span class="bold"><?= htmlspecialchars($item['name']) ?></span><br>
      <span style="font-size:11px;"><?= $item['qty'] ?> x <?= number_format($item['unit_price']) ?>đ</span>
      <?php if (!empty($item['note'])): ?>
      <div class="item-note">↳ <?= htmlspecialchars($item['note']) ?></div>
      <?php endif; ?>
    </td>
    <td class="right bold"><?= number_format($amt) ?>đ</td>
  </tr>
  <?php endwhile; ?>
</table>

<hr class="dashed">

<!-- Totals -->
<table>
  <tr><td>Tổng tiền hàng</td><td class="right"><?= number_format($subtotal) ?>đ</td></tr>
  <?php if ($discount > 0): ?>
  <tr><td>Giảm giá<?= $order['discount_type']==='percent' ? ' ('.$order['discount_value'].'%)' : '' ?></td>
      <td class="right">- <?= number_format($discount) ?>đ</td></tr>
  <?php endif; ?>
</table>

<hr class="solid">

<table>
  <tr>
    <td class="bold lg">THANH TOÁN</td>
    <td class="right bold xl"><?= number_format($order['total_amount']) ?>đ</td>
  </tr>
</table>

<div style="margin-top:8px;font-size:11px;line-height:1.8;">
  <?php if ($order['payment_method'] === 'cash'): ?>
  💵 Tiền mặt: <b><?= number_format($order['cash_amount']) ?>đ</b>
  <?php elseif ($order['payment_method'] === 'bank'): ?>
  🏦 Chuyển khoản: <b><?= number_format($order['bank_amount']) ?>đ</b>
  <?php else: ?>
  💵 Tiền mặt: <b><?= number_format($order['cash_amount']) ?>đ</b><br>
  🏦 Chuyển khoản: <b><?= number_format($order['bank_amount']) ?>đ</b>
  <?php endif; ?>
</div>

<hr class="dashed">

<div class="footer">
  Cảm ơn quý khách!<br>
  Hẹn gặp lại 💚
</div>

<script>
window.addEventListener('load', function() {
  // Auto-print after 500ms so page renders first
  setTimeout(function() {
    window.print();
  }, 500);
});
window.addEventListener('afterprint', function() {
  window.location.href = 'index.php';
});
</script>
</body>
</html>
