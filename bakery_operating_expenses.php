<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);
$error = '';
$success = [
    'added'=>'Đã thêm chi phí vận hành.',
    'updated'=>'Đã cập nhật chi phí vận hành.',
    'deleted'=>'Đã xóa chi phí vận hành.',
][$_GET['success'] ?? ''] ?? '';
$categories = ['Thuê mặt bằng','Điện','Nước','Internet / điện thoại','Nhân sự','Vệ sinh','Bảo trì / sửa chữa','Vận chuyển','Phí dịch vụ','Khác'];
$statuses = ['planned'=>'Dự kiến','paid'=>'Đã thanh toán'];
$form = [
    'id'=>0, 'expense_date'=>date('Y-m-d'), 'category'=>'Thuê mặt bằng',
    'description'=>'', 'payee'=>'', 'amount'=>'', 'status'=>'paid', 'note'=>'',
];
$range = bakeryMonthRange($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare('DELETE FROM bakery_operating_expenses WHERE id=?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        header('Location: bakery_operating_expenses.php?' . bakeryMonthRangeQuery($range, ['success'=>'deleted']));
        exit;
    }
    if ($action === 'save') {
        $form = [
            'id'=>(int)($_POST['id'] ?? 0),
            'expense_date'=>trim((string)($_POST['expense_date'] ?? '')),
            'category'=>trim((string)($_POST['category'] ?? '')),
            'description'=>trim((string)($_POST['description'] ?? '')),
            'payee'=>trim((string)($_POST['payee'] ?? '')),
            'amount'=>trim((string)($_POST['amount'] ?? '')),
            'status'=>isset($statuses[$_POST['status'] ?? '']) ? $_POST['status'] : 'planned',
            'note'=>trim((string)($_POST['note'] ?? '')),
        ];
        $amount = bakeryMoneyInput($form['amount']);
        if (!bakeryValidDate($form['expense_date'])) $error = 'Ngày chi phí không hợp lệ.';
        elseif (!in_array($form['category'], $categories, true)) $error = 'Nhóm chi phí không hợp lệ.';
        elseif ($form['description'] === '' || mb_strlen($form['description'], 'UTF-8') > 200) $error = 'Vui lòng nhập nội dung, tối đa 200 ký tự.';
        elseif (mb_strlen($form['payee'], 'UTF-8') > 150) $error = 'Đơn vị nhận tiền tối đa 150 ký tự.';
        elseif ($amount === null) $error = 'Số tiền phải lớn hơn 0.';
        elseif (mb_strlen($form['note'], 'UTF-8') > 1000) $error = 'Ghi chú tối đa 1.000 ký tự.';
        else {
            $id = (int)$form['id'];
            if ($id) {
                $stmt = $db->prepare('UPDATE bakery_operating_expenses SET expense_date=?,category=?,description=?,payee=?,amount=?,status=?,note=? WHERE id=?');
                $stmt->bind_param('sssssssi', $form['expense_date'], $form['category'], $form['description'], $form['payee'], $amount, $form['status'], $form['note'], $id);
                $result = 'updated';
            } else {
                $userId = (int)$bakeryAccount['id'];
                $stmt = $db->prepare('INSERT INTO bakery_operating_expenses(expense_date,category,description,payee,amount,status,note,created_by) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->bind_param('sssssssi', $form['expense_date'], $form['category'], $form['description'], $form['payee'], $amount, $form['status'], $form['note'], $userId);
                $result = 'added';
            }
            $stmt->execute();
            $savedMonth = substr($form['expense_date'], 0, 7);
            if ($savedMonth < $range['from_month'] || $savedMonth > $range['to_month']) {
                $range = bakeryMonthRange(['from_month'=>$savedMonth, 'to_month'=>$savedMonth]);
            }
            header('Location: bakery_operating_expenses.php?' . bakeryMonthRangeQuery($range, ['success'=>$result]));
            exit;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $db->prepare('SELECT * FROM bakery_operating_expenses WHERE id=?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $form = $row;
        $form['amount'] = number_format((float)$row['amount'], 0, ',', '.');
    }
}

$stmt = $db->prepare('SELECT * FROM bakery_operating_expenses WHERE expense_date BETWEEN ? AND ? ORDER BY expense_date DESC,id DESC');
$stmt->bind_param('ss', $range['from_date'], $range['to_date']);
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$total = 0;
$paidTotal = 0;
foreach ($rows as $row) {
    $total += (float)$row['amount'];
    if ($row['status'] === 'paid') $paidTotal += (float)$row['amount'];
}
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Chi phí vận hành bếp bánh</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
<header class="bk-header"><div><h1>Chi phí vận hành</h1><p>Quản lý tiền thuê mặt bằng, điện, nước và các chi phí hoạt động thường xuyên.</p></div></header>
<?php if ($error): ?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="bk-alert bk-alert-success"><?=bakeryH($success)?></div><?php endif; ?>
<div class="bk-grid bk-grid-2">
<section class="bk-card"><h2 class="bk-card-title"><?=$form['id'] ? 'Sửa chi phí vận hành' : 'Thêm chi phí vận hành'?></h2>
<form method="post"><?=csrf_field()?>
<input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=(int)$form['id']?>">
<input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>">
<div class="bk-form-grid">
<div><label class="bk-label">Ngày</label><input class="bk-input" type="date" name="expense_date" required value="<?=bakeryH($form['expense_date'])?>"></div>
<div><label class="bk-label">Nhóm chi phí</label><select class="bk-select" name="category"><?php foreach ($categories as $category): ?><option <?=$form['category']===$category?'selected':''?>><?=bakeryH($category)?></option><?php endforeach; ?></select></div>
<div class="bk-span-2"><label class="bk-label">Nội dung</label><input class="bk-input" name="description" maxlength="200" required placeholder="Ví dụ: Tiền thuê mặt bằng tháng 9" value="<?=bakeryH($form['description'])?>"></div>
<div><label class="bk-label">Đơn vị nhận tiền</label><input class="bk-input" name="payee" maxlength="150" value="<?=bakeryH($form['payee'])?>"></div>
<div><label class="bk-label">Số tiền</label><input class="bk-input" data-bk-money inputmode="numeric" name="amount" maxlength="13" required value="<?=bakeryH($form['amount'])?>"></div>
<div class="bk-span-2"><label class="bk-label">Trạng thái</label><select class="bk-select" name="status"><?php foreach ($statuses as $key=>$label): ?><option value="<?=$key?>" <?=$form['status']===$key?'selected':''?>><?=$label?></option><?php endforeach; ?></select></div>
<div class="bk-span-2"><label class="bk-label">Ghi chú</label><textarea class="bk-textarea" name="note" maxlength="1000"><?=bakeryH($form['note'])?></textarea></div>
</div><button class="bk-btn bk-btn-primary bk-btn-block"><?=$form['id'] ? 'Lưu thay đổi' : 'Thêm chi phí'?></button></form></section>
<section>
<form class="bk-filter"><div><label class="bk-label">Từ tháng</label><input class="bk-input" type="month" name="from_month" value="<?=bakeryH($range['from_month'])?>"></div><div><label class="bk-label">Đến tháng</label><input class="bk-input" type="month" name="to_month" value="<?=bakeryH($range['to_month'])?>"></div><button class="bk-btn bk-btn-secondary">Xem</button></form>
<div class="bk-total"><span>Đã thanh toán / Tổng dự kiến</span><span><?=bakeryMoney($paidTotal)?> / <?=bakeryMoney($total)?></span></div>
<div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Nhóm</th><th>Nội dung</th><th>Đơn vị nhận</th><th>Trạng thái</th><th class="money">Số tiền</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="7"><div class="bk-empty">Chưa có chi phí vận hành.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr><td><?=date('d/m/Y', strtotime($row['expense_date']))?></td><td><?=bakeryH($row['category'])?></td><td><strong><?=bakeryH($row['description'])?></strong><div class="bk-muted"><?=bakeryH($row['note'])?></div></td><td><?=bakeryH($row['payee'] ?: '—')?></td><td><span class="bk-badge <?=$row['status']==='paid'?'green':'orange'?>"><?=bakeryH($statuses[$row['status']] ?? $row['status'])?></span></td><td class="money"><?=bakeryMoney($row['amount'])?></td><td><div class="bk-actions"><a class="bk-btn bk-btn-secondary bk-btn-small" href="?<?=bakeryH(bakeryMonthRangeQuery($range, ['edit'=>(int)$row['id']]))?>">Sửa</a><form method="post" onsubmit="return confirm('Xóa chi phí vận hành này?')"><?=csrf_field()?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?=(int)$row['id']?>"><input type="hidden" name="from_month" value="<?=bakeryH($range['from_month'])?>"><input type="hidden" name="to_month" value="<?=bakeryH($range['to_month'])?>"><button class="bk-btn bk-btn-danger bk-btn-small">Xóa</button></form></div></td></tr><?php endforeach; ?>
</tbody></table></div></section></div></main>
<script>document.querySelectorAll('[data-bk-money]').forEach(function(e){function f(){let d=e.value.replace(/\D/g,'').replace(/^0+(?=\d)/,'');e.value=d.replace(/\B(?=(\d{3})+(?!\d))/g,'.')}e.addEventListener('input',f);f()})</script>
</body></html>
