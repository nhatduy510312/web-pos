<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);
$error = '';
$success = [
    'added'=>'Đã nhập mua nguyên liệu và cộng tồn kho.',
    'updated'=>'Đã cập nhật lần mua nguyên liệu.',
    'deleted'=>'Đã xóa lần mua nguyên liệu.',
][$_GET['success'] ?? ''] ?? '';
$form = ['id'=>0,'material_id'=>'','movement_date'=>date('Y-m-d'),'quantity'=>'','unit_cost'=>'','supplier'=>'','note'=>''];
$range = bakeryMonthRange($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("SELECT material_id FROM bakery_stock_movements WHERE id=? AND movement_type='purchase'");
        $stmt->bind_param('i', $id); $stmt->execute();
        $old = $stmt->get_result()->fetch_assoc();
        if (!$old) $error = 'Không tìm thấy lần mua.';
        elseif (bakeryCurrentStock($db, (int)$old['material_id'], $id) < -0.0001) $error = 'Không thể xóa vì sẽ làm tồn kho âm. Hãy điều chỉnh các lần xuất kho trước.';
        else {
            $stmt = $db->prepare("DELETE FROM bakery_stock_movements WHERE id=? AND movement_type='purchase'");
            $stmt->bind_param('i', $id); $stmt->execute();
            header('Location: bakery_material_purchases.php?' . bakeryMonthRangeQuery($range, ['success'=>'deleted']));
            exit;
        }
    }
    if ($action === 'save') {
        $form = [
            'id'=>(int)($_POST['id'] ?? 0), 'material_id'=>(int)($_POST['material_id'] ?? 0),
            'movement_date'=>trim((string)($_POST['movement_date'] ?? '')),
            'quantity'=>trim((string)($_POST['quantity'] ?? '')),
            'unit_cost'=>trim((string)($_POST['unit_cost'] ?? '')),
            'supplier'=>trim((string)($_POST['supplier'] ?? '')),
            'note'=>trim((string)($_POST['note'] ?? '')),
        ];
        $quantity = bakeryDecimalInput($form['quantity']);
        $cost = bakeryMoneyInput($form['unit_cost']);
        if (!bakeryValidDate($form['movement_date'])) $error = 'Ngày mua không hợp lệ.';
        elseif (!$form['material_id']) $error = 'Vui lòng chọn nguyên liệu.';
        elseif ($quantity === null) $error = 'Số lượng không hợp lệ.';
        elseif ($cost === null) $error = 'Đơn giá phải lớn hơn 0.';
        else {
            $id = (int)$form['id'];
            if ($id) {
                $stmt = $db->prepare("SELECT material_id FROM bakery_stock_movements WHERE id=? AND movement_type='purchase'");
                $stmt->bind_param('i', $id); $stmt->execute();
                $old = $stmt->get_result()->fetch_assoc();
                if (!$old) $error = 'Không tìm thấy lần mua.';
                else {
                    $oldMaterialId = (int)$old['material_id'];
                    $stockWithoutOld = bakeryCurrentStock($db, $oldMaterialId, $id);
                    if ($oldMaterialId !== (int)$form['material_id'] && $stockWithoutOld < -0.0001) {
                        $error = 'Không thể đổi nguyên liệu vì tồn cũ sẽ bị âm.';
                    } elseif ($oldMaterialId === (int)$form['material_id'] && $stockWithoutOld + (float)$quantity < -0.0001) {
                        $error = 'Số lượng mới sẽ làm tồn kho âm.';
                    } else {
                        $stmt = $db->prepare("UPDATE bakery_stock_movements SET material_id=?,movement_date=?,quantity=?,unit_cost=?,supplier=?,note=? WHERE id=? AND movement_type='purchase'");
                        $stmt->bind_param('isssssi', $form['material_id'], $form['movement_date'], $quantity, $cost, $form['supplier'], $form['note'], $id);
                        $stmt->execute();
                        $result = 'updated';
                    }
                }
            } else {
                $userId = (int)$bakeryAccount['id'];
                $type = 'purchase';
                $stmt = $db->prepare('INSERT INTO bakery_stock_movements(material_id,movement_date,movement_type,quantity,unit_cost,supplier,note,created_by) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->bind_param('issssssi', $form['material_id'], $form['movement_date'], $type, $quantity, $cost, $form['supplier'], $form['note'], $userId);
                $stmt->execute();
                $result = 'added';
            }
            if (!$error) {
                $savedMonth = substr($form['movement_date'], 0, 7);
                if ($savedMonth < $range['from_month'] || $savedMonth > $range['to_month']) {
                    $range = bakeryMonthRange(['from_month'=>$savedMonth, 'to_month'=>$savedMonth]);
                }
                header('Location: bakery_material_purchases.php?' . bakeryMonthRangeQuery($range, ['success'=>$result]));
                exit;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM bakery_stock_movements WHERE id=? AND movement_type='purchase'");
    $stmt->bind_param('i', $id); $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $form = $row;
        $form['unit_cost'] = number_format((float)$row['unit_cost'], 0, ',', '.');
    }
}

$materials = $db->query('SELECT id,name,unit,is_active FROM bakery_materials ORDER BY is_active DESC,name')->fetch_all(MYSQLI_ASSOC);
$stmt = $db->prepare("SELECT s.*,m.name,m.unit FROM bakery_stock_movements s JOIN bakery_materials m ON m.id=s.material_id WHERE s.movement_type='purchase' AND s.movement_date BETWEEN ? AND ? ORDER BY s.movement_date DESC,s.id DESC");
$stmt->bind_param('ss', $range['from_date'], $range['to_date']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = 0;
foreach ($rows as $row) $total += (float)$row['quantity'] * (float)$row['unit_cost'];
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Mua nguyên liệu</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell"><header class="bk-header"><div><h1>Mua nguyên vật liệu</h1><p>Mỗi lần mua được tự động cộng vào số lượng tồn kho.</p></div></header>
<?php if ($error): ?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="bk-alert bk-alert-success"><?=bakeryH($success)?></div><?php endif; ?>
<?php if (!$materials): ?><div class="bk-alert bk-alert-info">Hãy tạo nguyên liệu trong <a href="bakery_materials.php"><strong>Danh mục</strong></a> trước.</div><?php endif; ?>
<div class="bk-grid bk-grid-2">
<section class="bk-card"><h2 class="bk-card-title"><?=$form['id'] ? 'Sửa lần mua' : 'Nhập lần mua nguyên liệu'?></h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$form['id']?>">
<input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>">
<label class="bk-label">Nguyên liệu</label><select class="bk-select" name="material_id" required><option value="">Chọn nguyên liệu</option><?php foreach ($materials as $material): ?><option value="<?=(int)$material['id']?>" <?=(int)$form['material_id']===(int)$material['id']?'selected':''?> <?=!(int)$material['is_active']&&(int)$form['material_id']!==(int)$material['id']?'disabled':''?>><?=bakeryH($material['name'].' ('.$material['unit'].')')?></option><?php endforeach; ?></select>
<div class="bk-form-grid">
<div><label class="bk-label">Ngày mua</label><input class="bk-input" type="date" name="movement_date" required value="<?=bakeryH($form['movement_date'])?>"></div>
<div><label class="bk-label">Nhà cung cấp</label><input class="bk-input" name="supplier" maxlength="150" value="<?=bakeryH($form['supplier'])?>"></div>
<div><label class="bk-label">Số lượng</label><input class="bk-input" type="number" min="0.001" step="0.001" name="quantity" required value="<?=bakeryH($form['quantity'])?>"></div>
<div><label class="bk-label">Đơn giá</label><input class="bk-input" data-bk-money inputmode="numeric" name="unit_cost" maxlength="13" required value="<?=bakeryH($form['unit_cost'])?>"></div>
<div class="bk-span-2"><label class="bk-label">Ghi chú</label><textarea class="bk-textarea" name="note" maxlength="1000"><?=bakeryH($form['note'])?></textarea></div>
</div><button class="bk-btn bk-btn-primary bk-btn-block" <?=$materials?'':'disabled'?>><?=$form['id'] ? 'Lưu thay đổi' : 'Nhập hàng & cộng tồn'?></button></form></section>
<section>
<form class="bk-filter"><div><label class="bk-label">Từ tháng</label><input class="bk-input" type="month" name="from_month" value="<?=bakeryH($range['from_month'])?>"></div><div><label class="bk-label">Đến tháng</label><input class="bk-input" type="month" name="to_month" value="<?=bakeryH($range['to_month'])?>"></div><button class="bk-btn bk-btn-secondary">Xem</button></form>
<div class="bk-total"><span>Tổng mua nguyên liệu trong khoảng</span><span><?=bakeryMoney($total)?></span></div>
<div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Nguyên liệu</th><th>Nhà cung cấp</th><th>SL</th><th class="money">Đơn giá</th><th class="money">Thành tiền</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7"><div class="bk-empty">Chưa có lần mua nguyên liệu.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?=date('d/m/Y', strtotime($row['movement_date']))?></td><td><strong><?=bakeryH($row['name'])?></strong><div class="bk-muted"><?=bakeryH($row['note'])?></div></td><td><?=bakeryH($row['supplier'] ?: '—')?></td><td><?=bakeryNumber($row['quantity']).' '.bakeryH($row['unit'])?></td><td class="money"><?=bakeryMoney($row['unit_cost'])?></td><td class="money"><?=bakeryMoney((float)$row['quantity']*(float)$row['unit_cost'])?></td><td><div class="bk-actions"><a class="bk-btn bk-btn-secondary bk-btn-small" href="?<?=bakeryH(bakeryMonthRangeQuery($range, ['edit'=>(int)$row['id']]))?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa lần mua này? Tồn kho sẽ tự tính lại.')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>"><button class="bk-btn bk-btn-danger bk-btn-small">Xóa</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></section></div></main>
<script>document.querySelectorAll('[data-bk-money]').forEach(function(e){function f(){let d=e.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'');e.value=d.replace(/\B(?=(\d{3})+(?!\d))/g,'.')}e.addEventListener('input',f);f()})</script>
</body></html>
