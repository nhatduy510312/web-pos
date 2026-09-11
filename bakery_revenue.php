<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);

$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate = trim((string)($_GET['end_date'] ?? date('Y-m-t')));
if (!bakeryValidDate($startDate)) $startDate = date('Y-m-01');
if (!bakeryValidDate($endDate)) $endDate = date('Y-m-t');
if ($startDate > $endDate) [$startDate, $endDate] = [$endDate, $startDate];
$fromDateTime = $startDate . ' 00:00:00';
$toDateTime = (new DateTimeImmutable($endDate))->modify('+1 day')->format('Y-m-d') . ' 00:00:00';

$category = $db->query("SELECT id,name FROM categories WHERE LOWER(TRIM(name))='bakery' ORDER BY id LIMIT 1")->fetch_assoc();
$productCount = 0;
$sales = [];
$products = [];
$days = [];
$orderIds = [];
$grossTotal = 0.0;
$discountTotal = 0.0;
$revenueTotal = 0.0;
$quantityTotal = 0.0;

if ($category) {
    $categoryId = (int)$category['id'];
    $stmt = $db->prepare('SELECT COUNT(*) total FROM products WHERE category_id=?');
    $stmt->bind_param('i', $categoryId); $stmt->execute();
    $productCount = (int)$stmt->get_result()->fetch_assoc()['total'];

    $stmt = $db->prepare(
        "SELECT oi.id AS item_id,oi.order_id,oi.qty,oi.unit_price,
                p.id AS product_id,p.name AS product_name,
                COALESCE(o.paid_at,o.created_at) AS sold_at,o.payment_method,
                COALESCE(o.discount_amount,0) AS order_discount,
                COALESCE(order_totals.order_gross,0) AS order_gross
         FROM order_items oi
         JOIN products p ON p.id=oi.product_id
         JOIN orders o ON o.id=oi.order_id
         JOIN (
             SELECT order_id,SUM(qty*unit_price) AS order_gross
             FROM order_items GROUP BY order_id
         ) order_totals ON order_totals.order_id=o.id
         WHERE o.status='paid' AND p.category_id=?
           AND COALESCE(o.paid_at,o.created_at)>=?
           AND COALESCE(o.paid_at,o.created_at)<?
         ORDER BY sold_at DESC,oi.order_id DESC,oi.id DESC"
    );
    $stmt->bind_param('iss', $categoryId, $fromDateTime, $toDateTime);
    $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $quantity = (float)$row['qty'];
        $gross = $quantity * (float)$row['unit_price'];
        $orderGross = (float)$row['order_gross'];
        $orderDiscount = max(0.0, (float)$row['order_discount']);
        $discount = $orderGross > 0 ? min($gross, $orderDiscount * $gross / $orderGross) : 0.0;
        $revenue = max(0.0, $gross - $discount);
        $sale = $row + ['gross'=>$gross, 'discount'=>$discount, 'revenue'=>$revenue];
        $sales[] = $sale;

        $productId = (int)$row['product_id'];
        if (!isset($products[$productId])) {
            $products[$productId] = ['name'=>$row['product_name'],'quantity'=>0.0,'gross'=>0.0,'discount'=>0.0,'revenue'=>0.0];
        }
        $products[$productId]['quantity'] += $quantity;
        $products[$productId]['gross'] += $gross;
        $products[$productId]['discount'] += $discount;
        $products[$productId]['revenue'] += $revenue;

        $day = substr($row['sold_at'], 0, 10);
        if (!isset($days[$day])) $days[$day] = ['quantity'=>0.0,'orders'=>[],'revenue'=>0.0];
        $days[$day]['quantity'] += $quantity;
        $days[$day]['orders'][(int)$row['order_id']] = true;
        $days[$day]['revenue'] += $revenue;

        $orderIds[(int)$row['order_id']] = true;
        $quantityTotal += $quantity;
        $grossTotal += $gross;
        $discountTotal += $discount;
        $revenueTotal += $revenue;
    }
}

uasort($products, static fn(array $left, array $right): int => $right['revenue'] <=> $left['revenue']);
krsort($days);
$paymentLabels = ['cash'=>'Tiền mặt','bank'=>'Chuyển khoản','mixed'=>'Kết hợp'];
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Doanh thu Bakery</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
<header class="bk-header"><div><h1>Doanh thu Bakery</h1><p>Dữ liệu được lấy trực tiếp từ các hóa đơn đã thanh toán có món thuộc danh mục Bakery trên POS.</p></div></header>
<form class="bk-filter bk-filter-card" method="get">
<div><label class="bk-label">Từ ngày</label><input class="bk-input" type="date" name="start_date" required value="<?=bakeryH($startDate)?>"></div>
<div><label class="bk-label">Đến ngày</label><input class="bk-input" type="date" name="end_date" required value="<?=bakeryH($endDate)?>"></div>
<button class="bk-btn bk-btn-secondary">Xem doanh thu</button>
</form>

<?php if (!$category): ?>
<div class="bk-alert bk-alert-error">Không tìm thấy danh mục có tên <strong>Bakery</strong> trên POS. Vui lòng kiểm tra lại đúng tên danh mục.</div>
<?php elseif ($productCount === 0): ?>
<div class="bk-alert bk-alert-info">Đã tìm thấy danh mục <strong><?=bakeryH($category['name'])?></strong>, nhưng hiện chưa có món. Khi bạn thêm món vào danh mục này và POS phát sinh hóa đơn đã thanh toán, doanh thu sẽ tự xuất hiện tại đây.</div>
<?php endif; ?>

<section class="bk-stats">
<div class="bk-stat green"><span>Doanh thu Bakery</span><strong><?=bakeryMoney($revenueTotal)?></strong><small>Sau khi phân bổ giảm giá hóa đơn</small></div>
<div class="bk-stat orange"><span>Doanh thu trước giảm giá</span><strong><?=bakeryMoney($grossTotal)?></strong></div>
<div class="bk-stat red"><span>Giảm giá phân bổ</span><strong><?=bakeryMoney($discountTotal)?></strong></div>
<div class="bk-stat blue"><span>Số lượng món đã bán</span><strong><?=bakeryNumber($quantityTotal)?></strong></div>
<div class="bk-stat orange"><span>Hóa đơn có món Bakery</span><strong><?=count($orderIds)?></strong></div>
</section>

<div class="bk-grid bk-grid-2 bk-report-grid">
<section><h2 class="bk-card-title">Doanh thu theo món</h2><div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Món</th><th>SL</th><th class="money">Trước giảm giá</th><th class="money">Giảm giá</th><th class="money">Doanh thu</th></tr></thead><tbody>
<?php if (!$products): ?><tr><td colspan="5"><div class="bk-empty">Chưa có doanh thu Bakery trong thời gian đã chọn.</div></td></tr><?php endif; ?>
<?php foreach ($products as $product): ?><tr><td><strong><?=bakeryH($product['name'])?></strong></td><td><?=bakeryNumber($product['quantity'])?></td><td class="money"><?=bakeryMoney($product['gross'])?></td><td class="money bk-low">−<?=bakeryMoney($product['discount'])?></td><td class="money bk-ok"><?=bakeryMoney($product['revenue'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section><h2 class="bk-card-title">Doanh thu theo ngày</h2><div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Hóa đơn</th><th>SL món</th><th class="money">Doanh thu</th></tr></thead><tbody>
<?php if (!$days): ?><tr><td colspan="4"><div class="bk-empty">Chưa có dữ liệu theo ngày.</div></td></tr><?php endif; ?>
<?php foreach ($days as $day=>$summary): ?><tr><td><strong><?=date('d/m/Y', strtotime($day))?></strong></td><td><?=count($summary['orders'])?></td><td><?=bakeryNumber($summary['quantity'])?></td><td class="money bk-ok"><?=bakeryMoney($summary['revenue'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</div>

<section style="margin-top:18px"><h2 class="bk-card-title">Chi tiết lượt bán</h2><div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Thời gian</th><th>Hóa đơn</th><th>Món</th><th>Thanh toán</th><th>SL</th><th class="money">Đơn giá</th><th class="money">Trước giảm giá</th><th class="money">Giảm giá</th><th class="money">Doanh thu</th></tr></thead><tbody>
<?php if (!$sales): ?><tr><td colspan="9"><div class="bk-empty">Chưa có lượt bán Bakery trong thời gian đã chọn.</div></td></tr><?php endif; ?>
<?php foreach ($sales as $sale): ?><tr><td><?=date('d/m/Y H:i', strtotime($sale['sold_at']))?></td><td><strong>#<?=(int)$sale['order_id']?></strong></td><td><strong><?=bakeryH($sale['product_name'])?></strong></td><td><?=bakeryH($paymentLabels[$sale['payment_method']] ?? $sale['payment_method'])?></td><td><?=bakeryNumber($sale['qty'])?></td><td class="money"><?=bakeryMoney($sale['unit_price'])?></td><td class="money"><?=bakeryMoney($sale['gross'])?></td><td class="money bk-low">−<?=bakeryMoney($sale['discount'])?></td><td class="money bk-ok"><?=bakeryMoney($sale['revenue'])?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
</main></body></html>
