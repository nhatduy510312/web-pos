<?php
include 'auth.php';
include 'config.php';
require_once __DIR__ . '/recipe_helpers.php';

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

$prepItems = loadOrderPreparationItems($conn, $id);
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
    width: 100%;
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
  .screen-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    width: 80mm;
    margin: 0 auto 12px;
  }
  .recipe-btn { background: #0f766e; }
  .prep-overlay {
    position: fixed;
    inset: 0;
    z-index: 1000;
    width: 100vw;
    min-height: 100vh;
    overflow-y: auto;
    background: #f1f5f9;
    color: #0f172a;
    font-family: Inter, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
  }
  .prep-overlay[hidden] { display: none; }
  .prep-shell {
    width: min(920px, 100%);
    min-height: 100vh;
    margin: 0 auto;
    padding: 20px 16px 108px;
  }
  .prep-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    margin-bottom: 16px;
  }
  .prep-title {
    font-size: 24px;
    line-height: 1.2;
    font-weight: 800;
    letter-spacing: -.02em;
  }
  .prep-meta {
    margin-top: 5px;
    color: #64748b;
    font-size: 13px;
    line-height: 1.6;
  }
  .prep-count {
    flex: 0 0 auto;
    padding: 7px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 999px;
    background: #fff;
    color: #475569;
    font-size: 12px;
    font-weight: 700;
  }
  .prep-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 14px;
  }
  .prep-card {
    overflow: hidden;
    border: 1px solid #cbd5e1;
    border-top: 5px solid #0f766e;
    border-radius: 16px;
    background: #fff;
    box-shadow: 0 5px 18px rgba(15, 23, 42, .07);
  }
  .prep-card.type-food { border-top-color: #ea580c; }
  .prep-card-head {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
    padding: 14px 15px 12px;
    border-bottom: 1px solid #e2e8f0;
  }
  .prep-card-name {
    font-size: 17px;
    line-height: 1.3;
    font-weight: 800;
  }
  .prep-card-recipe {
    margin-top: 4px;
    color: #64748b;
    font-size: 11px;
    font-weight: 600;
  }
  .prep-qty {
    flex: 0 0 auto;
    min-width: 46px;
    padding: 7px 9px;
    border-radius: 10px;
    background: #0f172a;
    color: #fff;
    text-align: center;
    font-size: 14px;
    font-weight: 800;
  }
  .prep-body { padding: 14px 15px 16px; }
  .prep-note {
    margin-bottom: 12px;
    padding: 10px 11px;
    border: 1px solid #fde68a;
    border-radius: 10px;
    background: #fffbeb;
    color: #92400e;
    font-size: 13px;
    font-weight: 700;
    line-height: 1.45;
  }
  .prep-section-title {
    margin: 13px 0 7px;
    color: #475569;
    font-size: 11px;
    font-weight: 800;
    letter-spacing: .07em;
    text-transform: uppercase;
  }
  .ingredient-list {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
  }
  .ingredient-list td {
    padding: 7px 0;
    border-bottom: 1px solid #f1f5f9;
    vertical-align: top;
  }
  .ingredient-list th {
    padding: 4px 0 7px;
    border-bottom: 1px solid #cbd5e1;
    color: #64748b;
    font-size: 11px;
    font-weight: 800;
    text-align: left;
    text-transform: uppercase;
  }
  .ingredient-list td:last-child {
    padding-left: 12px;
    color: #0f172a;
    text-align: right;
    font-weight: 700;
  }
  .ingredient-list.dual-variant th:not(:first-child),
  .ingredient-list.dual-variant td:not(:first-child) {
    width: 23%;
    padding-left: 8px;
    color: #0f172a;
    text-align: right;
    font-weight: 700;
  }
  .ingredient-list.dual-variant th:not(:first-child) {
    color: #475569;
  }
  .prep-steps {
    color: #334155;
    font-size: 13px;
    line-height: 1.65;
    white-space: normal;
  }
  .prep-unmapped {
    padding: 14px;
    border: 1px dashed #cbd5e1;
    border-radius: 10px;
    background: #f8fafc;
    color: #64748b;
    font-size: 13px;
    line-height: 1.5;
    text-align: center;
  }
  .prep-empty {
    padding: 28px 18px;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    background: #fff;
    color: #64748b;
    text-align: center;
  }
  .prep-footer {
    position: fixed;
    z-index: 1001;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    justify-content: center;
    gap: 10px;
    padding: 12px 16px max(12px, env(safe-area-inset-bottom));
    border-top: 1px solid #cbd5e1;
    background: rgba(255, 255, 255, .96);
    backdrop-filter: blur(10px);
  }
  .prep-action {
    min-height: 46px;
    padding: 11px 18px;
    border: 0;
    border-radius: 11px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 800;
    cursor: pointer;
  }
  .prep-action-secondary {
    border: 1px solid #cbd5e1;
    background: #fff;
    color: #334155;
  }
  .prep-action-primary {
    min-width: 180px;
    background: #2563eb;
    color: #fff;
  }
  @media (max-width: 720px) {
    .prep-shell { padding: 16px 12px 112px; }
    .prep-grid { grid-template-columns: 1fr; }
    .prep-title { font-size: 21px; }
    .prep-footer { display: grid; grid-template-columns: 1fr 1.35fr; }
    .prep-action { width: 100%; padding-right: 10px; padding-left: 10px; }
  }
}
@media print {
  .screen-actions, .prep-overlay { display: none !important; }
}
</style>
</head>
<body>

<div class="screen-actions no-print">
  <button class="print-btn" type="button" onclick="printReceiptAgain()">🖨️ In hóa đơn</button>
  <button class="print-btn recipe-btn" type="button" onclick="showPreparationSheet()">📋 Phiếu chế biến</button>
</div>

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

<section class="prep-overlay no-print" id="preparationSheet" hidden aria-hidden="true">
  <div class="prep-shell">
    <header class="prep-header">
      <div>
        <h1 class="prep-title">📋 Phiếu chế biến #<?= $id ?></h1>
        <div class="prep-meta">
          <?php if (!empty($order['table_id'])): ?>
          Bàn <?= (int)$order['table_id'] ?> ·
          <?php endif; ?>
          <?= date('H:i · d/m/Y', strtotime($order['paid_at'] ?? $order['created_at'])) ?>
          <?php if (!empty($order['customer_name'])): ?>
          <br>Khách: <b><?= htmlspecialchars($order['customer_name']) ?></b>
          <?php endif; ?>
        </div>
      </div>
      <div class="prep-count"><?= count($prepItems) ?> món</div>
    </header>

    <?php if (!$prepItems): ?>
    <div class="prep-empty">
      Chưa tải được dữ liệu chế biến. Hóa đơn vẫn có thể in bình thường.
    </div>
    <?php else: ?>
    <div class="prep-grid">
      <?php foreach ($prepItems as $prepItem):
        $recipe = $prepItem['recipe'];
        $typeClass = $recipe && $recipe['type'] === 'food' ? 'type-food' : 'type-drink';
        $qty = (float)$prepItem['qty'];
        $qtyText = floor($qty) == $qty
            ? number_format($qty, 0)
            : rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
      ?>
      <article class="prep-card <?= $typeClass ?>">
        <div class="prep-card-head">
          <div>
            <div class="prep-card-name"><?= htmlspecialchars($prepItem['product_name']) ?></div>
            <?php if ($recipe): ?>
            <div class="prep-card-recipe">
              <?= $recipe['type'] === 'food' ? '🍳 Bếp' : '🥤 Quầy nước' ?>
              <?php if (!empty($recipe['variant_label'])): ?>
              · <?= htmlspecialchars($recipe['variant_label']) ?>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
          <div class="prep-qty">×<?= $qtyText ?></div>
        </div>

        <div class="prep-body">
          <?php if (!empty($prepItem['note'])): ?>
          <div class="prep-note">⚠️ <?= nl2br(htmlspecialchars($prepItem['note'])) ?></div>
          <?php endif; ?>

          <?php if (!$recipe): ?>
          <div class="prep-unmapped">
            Chưa có công thức cho món này.<br>
            Vui lòng thực hiện theo quy trình hiện tại của quán.
          </div>
          <?php else: ?>
            <?php if ($recipe['ingredients']): ?>
            <div class="prep-section-title">Nguyên liệu · định lượng cho 1 phần</div>
              <?php if (!empty($recipe['has_hot_iced_amounts'])): ?>
              <table class="ingredient-list dual-variant">
                <thead>
                  <tr>
                    <th>Nguyên liệu</th>
                    <th>Nóng</th>
                    <th>Đá</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($recipe['ingredients'] as $ingredient):
                    $hotMeasure = $ingredient['hot_amount'] !== null
                        ? trim($ingredient['hot_amount'] . ' ' . $ingredient['unit'])
                        : '';
                    $icedMeasure = $ingredient['iced_amount'] !== null
                        ? trim($ingredient['iced_amount'] . ' ' . $ingredient['unit'])
                        : '';
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($ingredient['name']) ?></td>
                    <td><?= $hotMeasure !== '' ? htmlspecialchars($hotMeasure) : '—' ?></td>
                    <td><?= $icedMeasure !== '' ? htmlspecialchars($icedMeasure) : '—' ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
              <?php else: ?>
              <table class="ingredient-list">
                <?php foreach ($recipe['ingredients'] as $ingredient):
                  $measure = trim($ingredient['amount'] . ' ' . $ingredient['unit']);
                ?>
                <tr>
                  <td><?= htmlspecialchars($ingredient['name']) ?></td>
                  <td><?= $measure !== '' ? htmlspecialchars($measure) : '—' ?></td>
                </tr>
                <?php endforeach; ?>
              </table>
              <?php endif; ?>
            <?php endif; ?>

            <?php if (!empty($recipe['instructions'])): ?>
            <div class="prep-section-title">Cách thực hiện</div>
            <div class="prep-steps"><?= nl2br(htmlspecialchars($recipe['instructions'])) ?></div>
            <?php elseif (!$recipe['ingredients']): ?>
            <div class="prep-unmapped">Công thức này chưa có nội dung chi tiết.</div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <footer class="prep-footer">
    <button type="button" class="prep-action prep-action-secondary" onclick="printReceiptAgain()">
      🖨️ In lại hóa đơn
    </button>
    <button type="button" class="prep-action prep-action-primary" onclick="closePreparationSheet()">
      Tắt · về POS
    </button>
  </footer>
</section>

<script>
function showPreparationSheet() {
  const sheet = document.getElementById('preparationSheet');
  if (!sheet) return;
  sheet.hidden = false;
  sheet.setAttribute('aria-hidden', 'false');
  document.body.style.overflow = 'hidden';
  sheet.scrollTop = 0;
}

function hidePreparationSheet() {
  const sheet = document.getElementById('preparationSheet');
  if (!sheet) return;
  sheet.hidden = true;
  sheet.setAttribute('aria-hidden', 'true');
  document.body.style.overflow = '';
}

function closePreparationSheet() {
  window.location.href = 'index.php';
}

function printReceiptAgain() {
  hidePreparationSheet();
  setTimeout(function() {
    window.print();
  }, 80);
}

window.addEventListener('load', function() {
  // Auto-print after 500ms so page renders first
  setTimeout(function() {
    window.print();
  }, 500);
});

window.addEventListener('afterprint', function() {
  setTimeout(showPreparationSheet, 120);
});
</script>
</body>
</html>
