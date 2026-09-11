<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);
$error = '';
$messages = ['added'=>'Đã cập nhật tồn kho.','updated'=>'Đã sửa giao dịch kho.','deleted'=>'Đã xóa giao dịch kho.'];
$success = $messages[$_GET['success'] ?? ''] ?? '';
$types = ['usage'=>'Sử dụng / xuất kho','adjustment_in'=>'Nhập điều chỉnh','adjustment_out'=>'Xuất điều chỉnh'];
$form = ['id'=>0,'material_id'=>'','movement_date'=>date('Y-m-d'),'movement_type'=>'usage','quantity'=>'','note'=>''];
$range = bakeryMonthRange($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT material_id,movement_type FROM bakery_stock_movements WHERE id=? AND movement_type<>'purchase'");
        $stmt->bind_param('i', $id); $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        if (!$old) $error = 'Không tìm thấy giao dịch kho.';
        elseif ($old['movement_type'] === 'adjustment_in' && bakeryCurrentStock($db, (int)$old['material_id'], $id) < -0.0001) $error = 'Không thể xóa vì sẽ làm tồn kho âm.';
        else {
            $stmt = $db->prepare("DELETE FROM bakery_stock_movements WHERE id=? AND movement_type<>'purchase'");
            $stmt->bind_param('i', $id); $stmt->execute();
            header('Location: bakery_inventory.php?' . bakeryMonthRangeQuery($range, ['success'=>'deleted'])); exit;
        }
    }
    if ($action === 'save') {
        $form = [
            'id'=>(int)($_POST['id'] ?? 0), 'material_id'=>(int)($_POST['material_id'] ?? 0),
            'movement_date'=>trim((string)($_POST['movement_date'] ?? '')),
            'movement_type'=>isset($types[$_POST['movement_type'] ?? '']) ? $_POST['movement_type'] : 'usage',
            'quantity'=>trim((string)($_POST['quantity'] ?? '')), 'note'=>trim((string)($_POST['note'] ?? '')),
        ];
        $quantity = bakeryDecimalInput($form['quantity']);
        $id = (int)$form['id']; $old = null;
        if ($id) {
            $stmt = $db->prepare("SELECT material_id FROM bakery_stock_movements WHERE id=? AND movement_type<>'purchase'");
            $stmt->bind_param('i', $id); $stmt->execute(); $old = $stmt->get_result()->fetch_assoc();
            if ($old) $form['material_id'] = (int)$old['material_id'];
        }
        if (!bakeryValidDate($form['movement_date'])) $error = 'Ngày giao dịch không hợp lệ.';
        elseif (!$form['material_id']) $error = 'Vui lòng chọn nguyên liệu.';
        elseif ($quantity === null) $error = 'Số lượng không hợp lệ.';
        elseif ($id && !$old) $error = 'Không tìm thấy giao dịch kho.';
        elseif (in_array($form['movement_type'], ['usage','adjustment_out'], true) && bakeryCurrentStock($db, (int)$form['material_id'], $id) + 0.0001 < (float)$quantity) $error = 'Số lượng xuất vượt quá tồn kho hiện tại.';
        else {
            $cost = '0.00'; $supplier = '';
            if ($id) {
                $stmt = $db->prepare("UPDATE bakery_stock_movements SET movement_date=?,movement_type=?,quantity=?,unit_cost=?,supplier=?,note=? WHERE id=? AND movement_type<>'purchase'");
                $stmt->bind_param('ssssssi', $form['movement_date'], $form['movement_type'], $quantity, $cost, $supplier, $form['note'], $id);
                $result = 'updated';
            } else {
                $userId = (int)$bakeryAccount['id'];
                $stmt = $db->prepare('INSERT INTO bakery_stock_movements(material_id,movement_date,movement_type,quantity,unit_cost,supplier,note,created_by) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->bind_param('issssssi', $form['material_id'], $form['movement_date'], $form['movement_type'], $quantity, $cost, $supplier, $form['note'], $userId);
                $result = 'added';
            }
            $stmt->execute();
            $savedMonth = substr($form['movement_date'], 0, 7);
            if ($savedMonth < $range['from_month'] || $savedMonth > $range['to_month']) {
                $range = bakeryMonthRange(['from_month'=>$savedMonth, 'to_month'=>$savedMonth]);
            }
            header('Location: bakery_inventory.php?' . bakeryMonthRangeQuery($range, ['success'=>$result])); exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM bakery_stock_movements WHERE id=? AND movement_type<>'purchase'");
    $stmt->bind_param('i', $id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) $form = $row;
}

$materials = $db->query("SELECT m.id,m.name,m.unit,m.minimum_stock,m.is_active,COALESCE(s.stock,0) AS stock FROM bakery_materials m LEFT JOIN (SELECT material_id,SUM(CASE WHEN movement_type IN ('purchase','adjustment_in') THEN quantity ELSE -quantity END) AS stock FROM bakery_stock_movements GROUP BY material_id) s ON s.material_id=m.id ORDER BY m.is_active DESC,m.name")->fetch_all(MYSQLI_ASSOC);
$stmt = $db->prepare("SELECT s.*,m.name,m.unit FROM bakery_stock_movements s JOIN bakery_materials m ON m.id=s.material_id WHERE s.movement_date BETWEEN ? AND ? ORDER BY s.movement_date DESC,s.id DESC LIMIT 300");
$stmt->bind_param('ss', $range['from_date'], $range['to_date']); $stmt->execute(); $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$allTypes = $types + ['purchase'=>'Mua vào'];
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Tồn kho bếp bánh</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell"><header class="bk-header"><div><h1>Quản lý tồn kho</h1><p>Tồn hiện tại được tự động tính từ toàn bộ lịch sử nhập và xuất.</p></div></header>
<?php if ($error): ?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif;?><?php if ($success): ?><div class="bk-alert bk-alert-success"><?=bakeryH($success)?></div><?php endif;?>
<section class="bk-card" style="margin-bottom:16px"><h2 class="bk-card-title">Tồn kho hiện tại</h2><div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Nguyên liệu</th><th>Đơn vị</th><th>Tồn hiện tại</th><th>Mức tối thiểu</th><th>Tình trạng</th></tr></thead><tbody>
<?php if (!$materials): ?><tr><td colspan="5"><div class="bk-empty">Chưa có nguyên liệu.</div></td></tr><?php endif;?>
<?php foreach ($materials as $material): $low=(float)$material['stock']<=(float)$material['minimum_stock']; ?><tr><td><strong><?=bakeryH($material['name'])?></strong></td><td><?=bakeryH($material['unit'])?></td><td class="<?=$low?'bk-low':'bk-ok'?>"><?=bakeryNumber($material['stock'])?></td><td><?=bakeryNumber($material['minimum_stock'])?></td><td><span class="bk-badge <?=$low?'red':'green'?>"><?=$low?'Cần nhập thêm':'Đủ hàng'?></span></td></tr><?php endforeach;?>
</tbody></table></div></section>
<div class="bk-grid bk-grid-2"><section class="bk-card"><h2 class="bk-card-title"><?=$form['id']?'Sửa giao dịch kho':'Ghi nhận nhập / xuất kho'?></h2><form method="post"><?=csrf_field()?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$form['id']?>"><input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>"><?php if($form['id']):?><input type="hidden" name="material_id" value="<?=(int)$form['material_id']?>"><?php endif;?><label class="bk-label">Nguyên liệu</label><select class="bk-select" name="material_id" required <?=$form['id']?'disabled':''?>><option value="">Chọn nguyên liệu</option><?php foreach($materials as $material):?><option value="<?=(int)$material['id']?>" <?=(int)$form['material_id']===(int)$material['id']?'selected':''?> <?=!(int)$material['is_active']?'disabled':''?>><?=bakeryH($material['name'].' — tồn '.bakeryNumber($material['stock']).' '.$material['unit'])?></option><?php endforeach;?></select><div class="bk-form-grid"><div><label class="bk-label">Ngày</label><input class="bk-input" type="date" name="movement_date" required value="<?=bakeryH($form['movement_date'])?>"></div><div><label class="bk-label">Loại giao dịch</label><select class="bk-select" name="movement_type"><?php foreach($types as $key=>$label):?><option value="<?=$key?>" <?=$form['movement_type']===$key?'selected':''?>><?=$label?></option><?php endforeach;?></select></div><div><label class="bk-label">Số lượng</label><input class="bk-input" type="number" min="0.001" step="0.001" name="quantity" required value="<?=bakeryH($form['quantity'])?>"></div><div class="bk-span-2"><label class="bk-label">Ghi chú</label><textarea class="bk-textarea" name="note" maxlength="1000"><?=bakeryH($form['note'])?></textarea></div></div><button class="bk-btn bk-btn-primary bk-btn-block"><?=$form['id']?'Lưu thay đổi':'Cập nhật tồn kho'?></button></form></section>
<section><form class="bk-filter"><div><label class="bk-label">Từ tháng</label><input class="bk-input" type="month" name="from_month" value="<?=bakeryH($range['from_month'])?>"></div><div><label class="bk-label">Đến tháng</label><input class="bk-input" type="month" name="to_month" value="<?=bakeryH($range['to_month'])?>"></div><button class="bk-btn bk-btn-secondary">Xem</button></form><div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Nguyên liệu</th><th>Loại</th><th>Thay đổi</th><th>Ghi chú</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="6"><div class="bk-empty">Chưa có giao dịch kho.</div></td></tr><?php endif;?>
<?php foreach ($rows as $row): $incoming=in_array($row['movement_type'],['purchase','adjustment_in'],true); ?><tr><td><?=date('d/m/Y',strtotime($row['movement_date']))?></td><td><strong><?=bakeryH($row['name'])?></strong></td><td><span class="bk-badge <?=$incoming?'green':'orange'?>"><?=bakeryH($allTypes[$row['movement_type']]??$row['movement_type'])?></span></td><td class="<?=$incoming?'bk-ok':'bk-low'?>"><?=$incoming?'+':'−'?><?=bakeryNumber($row['quantity']).' '.bakeryH($row['unit'])?></td><td class="bk-note"><?=bakeryH($row['note']?:'—')?></td><td><?php if($row['movement_type']==='purchase'):?><a class="bk-btn bk-btn-secondary bk-btn-small" href="bakery_material_purchases.php?<?=bakeryH(bakeryMonthRangeQuery($range,['edit'=>(int)$row['id']]))?>">Mở</a><?php else:?><div class="bk-actions"><a class="bk-btn bk-btn-secondary bk-btn-small" href="?<?=bakeryH(bakeryMonthRangeQuery($range,['edit'=>(int)$row['id']]))?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa giao dịch kho này?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>"><button class="bk-btn bk-btn-danger bk-btn-small">Xóa</button></form></div><?php endif;?></td></tr><?php endforeach;?>
</tbody></table></div></section></div></main></body></html>
