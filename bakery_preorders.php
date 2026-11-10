<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);

$statusLabels = [
    'pending' => 'Chờ xác nhận',
    'confirmed' => 'Đã xác nhận',
    'preparing' => 'Đang chuẩn bị',
    'ready' => 'Sẵn sàng',
    'completed' => 'Đã giao/nhận',
    'cancelled' => 'Đã hủy',
];
$allowedStatuses = array_keys($statusLabels);
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $orderId = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $newStatus = (string)($_POST['status'] ?? '');
    if ($orderId === false || !in_array($newStatus, $allowedStatuses, true)) {
        $error = 'Thông tin cập nhật không hợp lệ.';
    } else {
        $stmt = $db->prepare('UPDATE bread_preorders SET status=? WHERE id=?');
        $stmt->bind_param('si', $newStatus, $orderId);
        $stmt->execute();
        $destination = in_array($newStatus, ['completed', 'cancelled'], true) ? $newStatus : 'active';
        header('Location: bakery_preorders.php?filter=' . $destination . '&updated=' . $newStatus, true, 303);
        exit;
    }
}
$updatedStatus = (string)($_GET['updated'] ?? '');
if ($updatedStatus === 'completed') $notice = 'Đơn đã giao/nhận được lưu trong tab “Đã giao/nhận”.';
elseif ($updatedStatus === 'cancelled') $notice = 'Đơn đã hủy được lưu trong tab “Đã hủy”.';
elseif (in_array($updatedStatus, ['pending', 'confirmed', 'preparing', 'ready'], true)) $notice = 'Đã cập nhật trạng thái đơn.';

$filterOptions = ['active', 'completed', 'cancelled', 'all'];
$filter = in_array($_GET['filter'] ?? 'active', $filterOptions, true) ? (string)($_GET['filter'] ?? 'active') : 'active';
$whereByFilter = [
    'active' => "WHERE status IN ('pending','confirmed','preparing','ready')",
    'completed' => "WHERE status='completed'",
    'cancelled' => "WHERE status='cancelled'",
    'all' => 'WHERE created_at>=DATE_SUB(NOW(),INTERVAL 60 DAY)',
];
$where = $whereByFilter[$filter];
$result = $db->query("SELECT o.id,o.customer_name,o.customer_phone,o.fulfillment_method,o.delivery_address,o.pickup_date,o.pickup_time,o.customer_note,o.total_amount,o.status,o.created_at,i.product_name,i.quantity,i.unit_price,i.line_total FROM (SELECT * FROM bread_preorders $where ORDER BY pickup_date ASC,pickup_time ASC,id ASC LIMIT 100) o LEFT JOIN bread_preorder_items i ON i.preorder_id=o.id ORDER BY o.pickup_date ASC,o.pickup_time ASC,o.id ASC");
$orders = [];
while ($row = $result->fetch_assoc()) {
    $id = (int)$row['id'];
    if (!isset($orders[$id])) {
        $orders[$id] = [
            'id' => $id,
            'customer_name' => $row['customer_name'],
            'customer_phone' => $row['customer_phone'],
            'fulfillment_method' => $row['fulfillment_method'],
            'delivery_address' => $row['delivery_address'],
            'pickup_date' => $row['pickup_date'],
            'pickup_time' => $row['pickup_time'],
            'customer_note' => $row['customer_note'],
            'total_amount' => $row['total_amount'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'items' => [],
        ];
    }
    if ($row['product_name'] !== null) $orders[$id]['items'][] = $row;
}
$methodLabels = ['pickup' => 'Lấy tại cửa hàng', 'delivery' => 'Giao hàng'];
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/bakery.css"><link rel="stylesheet" href="assets/bakery-preorders.css"><link rel="stylesheet" href="assets/24boulangerie-brand.css"><title>Đơn đặt bánh online · 24boulangerie</title></head>
<body><?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
  <header class="bk-header"><div><h1>Đơn đặt bánh online</h1><p>Đơn Pre-Order gửi đến 24boulangerie; chưa ghi nhận vào doanh thu POS.</p></div><a class="bk-btn bk-btn-secondary" href="banh-mi.php" target="_blank" rel="noopener">Mở trang Pre-Order ↗</a></header>
  <?php if ($notice): ?><div class="bk-alert bk-alert-success"><?= bakeryH($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bk-alert bk-alert-error"><?= bakeryH($error) ?></div><?php endif; ?>
  <nav class="bk-filter" aria-label="Lọc đơn đặt bánh"><a class="bk-btn <?= $filter==='active'?'bk-btn-primary':'bk-btn-secondary' ?>" href="?filter=active">Đơn cần xử lý</a><a class="bk-btn <?= $filter==='completed'?'bk-btn-primary':'bk-btn-secondary' ?>" href="?filter=completed">Đã giao/nhận</a><a class="bk-btn <?= $filter==='cancelled'?'bk-btn-primary':'bk-btn-secondary' ?>" href="?filter=cancelled">Đã hủy</a><a class="bk-btn <?= $filter==='all'?'bk-btn-primary':'bk-btn-secondary' ?>" href="?filter=all">Tất cả 60 ngày</a><span class="bk-muted"><?= count($orders) ?> đơn</span></nav>
  <?php if (!$orders): ?><section class="bk-card"><div class="bk-empty">Chưa có đơn đặt bánh nào trong danh sách.</div></section><?php endif; ?>
  <div class="preorder-admin-list">
  <?php foreach ($orders as $order): ?>
    <article class="bk-card preorder-admin-card">
      <header class="preorder-admin-heading"><div><h2 class="bk-card-title">Đơn #<?= $order['id'] ?> · <?= bakeryH($order['customer_name']) ?></h2><a href="tel:<?= bakeryH(preg_replace('/[^0-9+]/', '', $order['customer_phone'])) ?>"><?= bakeryH($order['customer_phone']) ?></a></div><span class="bk-badge <?= $order['status']==='completed'?'green':(in_array($order['status'],['cancelled'],true)?'':'orange') ?>"><?= bakeryH($statusLabels[$order['status']] ?? $order['status']) ?></span></header>
      <div class="preorder-admin-meta"><span><strong><?= bakeryH($methodLabels[$order['fulfillment_method']] ?? $order['fulfillment_method']) ?></strong></span><span><?= date('d/m/Y', strtotime($order['pickup_date'])) ?> · <?= date('H:i', strtotime($order['pickup_time'])) ?></span><span>Đặt lúc <?= date('d/m/Y H:i', strtotime($order['created_at'])) ?></span></div>
      <?php if ($order['fulfillment_method']==='delivery'): ?><p class="preorder-admin-address"><strong>Địa chỉ giao:</strong> <?= nl2br(bakeryH($order['delivery_address'])) ?></p><?php endif; ?>
      <ul class="preorder-admin-items"><?php foreach ($order['items'] as $item): ?><li><span><?= bakeryH($item['product_name']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= bakeryMoney($item['line_total']) ?></strong></li><?php endforeach; ?></ul>
      <div class="preorder-admin-total"><span>Tổng đơn</span><strong><?= bakeryMoney($order['total_amount']) ?></strong></div>
      <?php if ($order['customer_note']!==''): ?><p class="preorder-admin-note"><strong>Lời nhắn:</strong> <?= nl2br(bakeryH($order['customer_note'])) ?></p><?php endif; ?>
      <form method="post" class="preorder-status-form"><?= csrf_field() ?><input type="hidden" name="id" value="<?= $order['id'] ?>"><label class="bk-label" for="status-<?= $order['id'] ?>">Cập nhật trạng thái</label><select class="bk-select" id="status-<?= $order['id'] ?>" name="status"><?php foreach ($statusLabels as $value=>$label): ?><option value="<?= bakeryH($value) ?>" <?= $order['status']===$value?'selected':'' ?>><?= bakeryH($label) ?></option><?php endforeach; ?></select><button class="bk-btn bk-btn-primary">Lưu trạng thái</button></form>
    </article>
  <?php endforeach; ?>
  </div>
</main>
</body></html>
