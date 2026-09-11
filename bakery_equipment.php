<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);
$error = '';
$success = [
    'added' => 'Đã thêm thiết bị.',
    'updated' => 'Đã cập nhật thiết bị.',
    'deleted' => 'Đã xóa thiết bị.',
][$_GET['success'] ?? ''] ?? '';
$labels = ['planned'=>'Dự kiến','ordered'=>'Đã đặt','received'=>'Đã nhận','installed'=>'Đã lắp đặt'];
$form = [
    'id'=>0, 'purchase_date'=>date('Y-m-d'), 'equipment_name'=>'',
    'category'=>'Thiết bị sản xuất', 'supplier'=>'', 'quantity'=>'1',
    'unit_price'=>'', 'status'=>'planned', 'note'=>'',
];
$range = bakeryMonthRange($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM bakery_equipment WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header('Location: bakery_equipment.php?' . bakeryMonthRangeQuery($range, ['success'=>'deleted']));
        exit;
    }
    if ($action === 'save') {
        $form = [
            'id'=>(int)($_POST['id'] ?? 0),
            'purchase_date'=>trim((string)($_POST['purchase_date'] ?? '')),
            'equipment_name'=>trim((string)($_POST['equipment_name'] ?? '')),
            'category'=>trim((string)($_POST['category'] ?? '')),
            'supplier'=>trim((string)($_POST['supplier'] ?? '')),
            'quantity'=>trim((string)($_POST['quantity'] ?? '')),
            'unit_price'=>trim((string)($_POST['unit_price'] ?? '')),
            'status'=>isset($labels[$_POST['status'] ?? '']) ? $_POST['status'] : 'planned',
            'note'=>trim((string)($_POST['note'] ?? '')),
        ];
        $quantity = bakeryDecimalInput($form['quantity']);
        $price = bakeryMoneyInput($form['unit_price']);
        if (!bakeryValidDate($form['purchase_date'])) $error = 'Ngày mua không hợp lệ.';
        elseif ($form['equipment_name'] === '' || mb_strlen($form['equipment_name'], 'UTF-8') > 180) $error = 'Vui lòng nhập tên thiết bị, tối đa 180 ký tự.';
        elseif ($quantity === null) $error = 'Số lượng không hợp lệ.';
        elseif ($price === null) $error = 'Đơn giá phải lớn hơn 0.';
        elseif (mb_strlen($form['note'], 'UTF-8') > 1000) $error = 'Ghi chú tối đa 1.000 ký tự.';
        else {
            $totalAmount = number_format((float)$quantity * (float)$price, 2, '.', '');
            $id = (int)$form['id'];
            if ($id) {
                $stmt = $db->prepare('UPDATE bakery_equipment SET purchase_date=?,equipment_name=?,category=?,supplier=?,quantity=?,unit_price=?,total_amount=?,status=?,note=? WHERE id=?');
                $stmt->bind_param('sssssssssi', $form['purchase_date'], $form['equipment_name'], $form['category'], $form['supplier'], $quantity, $price, $totalAmount, $form['status'], $form['note'], $id);
                $result = 'updated';
            } else {
                $userId = (int)$bakeryAccount['id'];
                $stmt = $db->prepare('INSERT INTO bakery_equipment(purchase_date,equipment_name,category,supplier,quantity,unit_price,total_amount,status,note,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssssssssi', $form['purchase_date'], $form['equipment_name'], $form['category'], $form['supplier'], $quantity, $price, $totalAmount, $form['status'], $form['note'], $userId);
                $result = 'added';
            }
            $stmt->execute();
            $savedMonth = substr($form['purchase_date'], 0, 7);
            if ($savedMonth < $range['from_month'] || $savedMonth > $range['to_month']) {
                $range = bakeryMonthRange(['from_month'=>$savedMonth, 'to_month'=>$savedMonth]);
            }
            header('Location: bakery_equipment.php?' . bakeryMonthRangeQuery($range, ['success'=>$result]));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM bakery_equipment WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $form = $row;
        $form['unit_price'] = number_format((float)$row['unit_price'], 0, ',', '.');
    }
}

$stmt = $db->prepare('SELECT * FROM bakery_equipment WHERE purchase_date BETWEEN ? AND ? ORDER BY purchase_date DESC,id DESC');
$stmt->bind_param('ss', $range['from_date'], $range['to_date']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = array_sum(array_map(fn($row)=>(float)$row['total_amount'], $rows));
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Thiết bị bếp bánh</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
<header class="bk-header"><div><h1>Mua sắm thiết bị</h1><p>Quản lý máy móc, dụng cụ, nhà cung cấp và tiến độ lắp đặt.</p></div></header>
<?php if ($error): ?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="bk-alert bk-alert-success"><?=bakeryH($success)?></div><?php endif; ?>
<div class="bk-grid bk-grid-2">
<section class="bk-card"><h2 class="bk-card-title"><?=$form['id'] ? 'Sửa thiết bị' : 'Thêm thiết bị'?></h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$form['id']?>">
<input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>">
<div class="bk-form-grid">
<div><label class="bk-label">Ngày</label><input class="bk-input" type="date" name="purchase_date" required value="<?=bakeryH($form['purchase_date'])?>"></div>
<div><label class="bk-label">Trạng thái</label><select class="bk-select" name="status"><?php foreach ($labels as $key=>$label): ?><option value="<?=$key?>" <?=$form['status']===$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select></div>
<div class="bk-span-2"><label class="bk-label">Tên thiết bị</label><input class="bk-input" name="equipment_name" maxlength="180" required value="<?=bakeryH($form['equipment_name'])?>"></div>
<div><label class="bk-label">Nhóm thiết bị</label><select class="bk-select" name="category"><?php foreach (['Thiết bị sản xuất','Thiết bị lạnh','Dụng cụ','Thiết bị điện','Nội thất','Khác'] as $value): ?><option <?=$form['category']===$value?'selected':''?>><?=bakeryH($value)?></option><?php endforeach; ?></select></div>
<div><label class="bk-label">Nhà cung cấp</label><input class="bk-input" name="supplier" maxlength="150" value="<?=bakeryH($form['supplier'])?>"></div>
<div><label class="bk-label">Số lượng</label><input class="bk-input" type="number" step="0.001" min="0.001" name="quantity" required value="<?=bakeryH($form['quantity'])?>"></div>
<div><label class="bk-label">Đơn giá</label><input class="bk-input" data-bk-money inputmode="numeric" name="unit_price" maxlength="13" required value="<?=bakeryH($form['unit_price'])?>"></div>
<div class="bk-span-2"><label class="bk-label">Ghi chú</label><textarea class="bk-textarea" name="note" maxlength="1000"><?=bakeryH($form['note'])?></textarea></div>
</div><button class="bk-btn bk-btn-primary bk-btn-block"><?=$form['id'] ? 'Lưu thay đổi' : 'Thêm thiết bị'?></button></form></section>
<section>
<form class="bk-filter"><div><label class="bk-label">Từ tháng</label><input class="bk-input" type="month" name="from_month" value="<?=bakeryH($range['from_month'])?>"></div><div><label class="bk-label">Đến tháng</label><input class="bk-input" type="month" name="to_month" value="<?=bakeryH($range['to_month'])?>"></div><button class="bk-btn bk-btn-secondary">Xem</button></form>
<div class="bk-total"><span>Tổng thiết bị trong khoảng đã chọn</span><span><?=bakeryMoney($total)?></span></div>
<div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Thiết bị</th><th>Nhà cung cấp</th><th>SL</th><th>Trạng thái</th><th class="money">Thành tiền</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7"><div class="bk-empty">Chưa có thiết bị.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?=date('d/m/Y', strtotime($row['purchase_date']))?></td><td><strong><?=bakeryH($row['equipment_name'])?></strong><div class="bk-muted"><?=bakeryH($row['category'])?> · <?=bakeryH($row['note'])?></div></td><td><?=bakeryH($row['supplier'] ?: '—')?></td><td><?=bakeryNumber($row['quantity'])?></td><td><span class="bk-badge <?=$row['status']==='installed'?'green':($row['status']==='planned'?'orange':'blue')?>"><?=bakeryH($labels[$row['status']] ?? $row['status'])?></span></td><td class="money"><?=bakeryMoney($row['total_amount'])?></td><td><div class="bk-actions"><a class="bk-btn bk-btn-secondary bk-btn-small" href="?<?=bakeryH(bakeryMonthRangeQuery($range, ['edit'=>(int)$row['id']]))?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa thiết bị này?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>"><button class="bk-btn bk-btn-danger bk-btn-small">Xóa</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></section></div></main>
<script>document.querySelectorAll('[data-bk-money]').forEach(function(e){function f(){let d=e.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'');e.value=d.replace(/\B(?=(\d{3})+(?!\d))/g,'.')}e.addEventListener('input',f);f()})</script>
</body></html>
