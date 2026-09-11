<?php
require_once __DIR__ . '/bakery_auth.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);

$startDate = trim((string)($_GET['start_date'] ?? date('Y-m-01')));
$endDate = trim((string)($_GET['end_date'] ?? date('Y-m-t')));
if (!bakeryValidDate($startDate)) $startDate = date('Y-m-01');
if (!bakeryValidDate($endDate)) $endDate = date('Y-m-t');
if ($startDate > $endDate) [$startDate, $endDate] = [$endDate, $startDate];

$reportTypes = [
    'all'=>'Tất cả hạng mục',
    'investment'=>'Đầu tư phần thô',
    'equipment'=>'Thiết bị',
    'operating'=>'Chi phí vận hành',
    'materials'=>'Mua nguyên liệu',
];
$selectedType = isset($reportTypes[$_GET['type'] ?? '']) ? $_GET['type'] : 'all';
$rows = [];
$monthRange = ['from_month'=>substr($startDate, 0, 7), 'to_month'=>substr($endDate, 0, 7)];

if ($selectedType === 'all' || $selectedType === 'investment') {
    $stmt = $db->prepare('SELECT id,investment_date,category,description,contractor,amount,status,note FROM bakery_investments WHERE investment_date BETWEEN ? AND ?');
    $stmt->bind_param('ss', $startDate, $endDate); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rows[] = [
            'id'=>(int)$row['id'], 'date'=>$row['investment_date'], 'group_key'=>'investment',
            'group'=>'Đầu tư phần thô', 'category'=>$row['category'], 'description'=>$row['description'],
            'party'=>$row['contractor'], 'status'=>$row['status']==='paid'?'Đã thanh toán':'Dự kiến',
            'confirmed'=>$row['status']==='paid', 'amount'=>(float)$row['amount'], 'note'=>$row['note'],
            'url'=>'bakery_investments.php?' . bakeryMonthRangeQuery($monthRange, ['edit'=>(int)$row['id']]),
        ];
    }
}

if ($selectedType === 'all' || $selectedType === 'equipment') {
    $equipmentStatuses = ['planned'=>'Dự kiến','ordered'=>'Đã đặt','received'=>'Đã nhận','installed'=>'Đã lắp đặt'];
    $stmt = $db->prepare('SELECT id,purchase_date,equipment_name,category,supplier,quantity,total_amount,status,note FROM bakery_equipment WHERE purchase_date BETWEEN ? AND ?');
    $stmt->bind_param('ss', $startDate, $endDate); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rows[] = [
            'id'=>(int)$row['id'], 'date'=>$row['purchase_date'], 'group_key'=>'equipment',
            'group'=>'Thiết bị', 'category'=>$row['category'],
            'description'=>$row['equipment_name'] . ' · SL ' . bakeryNumber($row['quantity']),
            'party'=>$row['supplier'], 'status'=>$equipmentStatuses[$row['status']] ?? $row['status'],
            'confirmed'=>$row['status']!=='planned', 'amount'=>(float)$row['total_amount'], 'note'=>$row['note'],
            'url'=>'bakery_equipment.php?' . bakeryMonthRangeQuery($monthRange, ['edit'=>(int)$row['id']]),
        ];
    }
}

if ($selectedType === 'all' || $selectedType === 'operating') {
    $stmt = $db->prepare('SELECT id,expense_date,category,description,payee,amount,status,note FROM bakery_operating_expenses WHERE expense_date BETWEEN ? AND ?');
    $stmt->bind_param('ss', $startDate, $endDate); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rows[] = [
            'id'=>(int)$row['id'], 'date'=>$row['expense_date'], 'group_key'=>'operating',
            'group'=>'Chi phí vận hành', 'category'=>$row['category'], 'description'=>$row['description'],
            'party'=>$row['payee'], 'status'=>$row['status']==='paid'?'Đã thanh toán':'Dự kiến',
            'confirmed'=>$row['status']==='paid', 'amount'=>(float)$row['amount'], 'note'=>$row['note'],
            'url'=>'bakery_operating_expenses.php?' . bakeryMonthRangeQuery($monthRange, ['edit'=>(int)$row['id']]),
        ];
    }
}

if ($selectedType === 'all' || $selectedType === 'materials') {
    $stmt = $db->prepare("SELECT s.id,s.movement_date,s.quantity,s.unit_cost,s.supplier,s.note,m.name,m.unit FROM bakery_stock_movements s JOIN bakery_materials m ON m.id=s.material_id WHERE s.movement_type='purchase' AND s.movement_date BETWEEN ? AND ?");
    $stmt->bind_param('ss', $startDate, $endDate); $stmt->execute();
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
        $rows[] = [
            'id'=>(int)$row['id'], 'date'=>$row['movement_date'], 'group_key'=>'materials',
            'group'=>'Mua nguyên liệu', 'category'=>'Nguyên vật liệu',
            'description'=>$row['name'] . ' · ' . bakeryNumber($row['quantity']) . ' ' . $row['unit'] . ' × ' . bakeryMoney($row['unit_cost']),
            'party'=>$row['supplier'], 'status'=>'Đã nhập kho', 'confirmed'=>true,
            'amount'=>(float)$row['quantity'] * (float)$row['unit_cost'], 'note'=>$row['note'],
            'url'=>'bakery_material_purchases.php?' . bakeryMonthRangeQuery($monthRange, ['edit'=>(int)$row['id']]),
        ];
    }
}

usort($rows, static function (array $left, array $right): int {
    $dateOrder = strcmp($right['date'], $left['date']);
    return $dateOrder !== 0 ? $dateOrder : ($right['id'] <=> $left['id']);
});

$totals = ['investment'=>0.0,'equipment'=>0.0,'operating'=>0.0,'materials'=>0.0];
$recordedTotal = 0.0;
$confirmedTotal = 0.0;
foreach ($rows as $row) {
    $recordedTotal += $row['amount'];
    $totals[$row['group_key']] += $row['amount'];
    if ($row['confirmed']) $confirmedTotal += $row['amount'];
}
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><title>Báo cáo bếp bánh</title></head><body>
<?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
<header class="bk-header"><div><h1>Báo cáo tổng hợp</h1><p>Xem chi tiết các khoản đầu tư, thiết bị, vận hành và mua nguyên liệu theo khoảng thời gian.</p></div></header>
<form class="bk-filter bk-filter-card" method="get">
<div><label class="bk-label">Từ ngày</label><input class="bk-input" type="date" name="start_date" required value="<?=bakeryH($startDate)?>"></div>
<div><label class="bk-label">Đến ngày</label><input class="bk-input" type="date" name="end_date" required value="<?=bakeryH($endDate)?>"></div>
<div><label class="bk-label">Hạng mục</label><select class="bk-select" name="type"><?php foreach ($reportTypes as $key=>$label): ?><option value="<?=$key?>" <?=$selectedType===$key?'selected':''?>><?=bakeryH($label)?></option><?php endforeach; ?></select></div>
<button class="bk-btn bk-btn-secondary">Xem báo cáo</button>
</form>
<section class="bk-stats">
<div class="bk-stat orange"><span>Đã thanh toán / xác nhận</span><strong><?=bakeryMoney($confirmedTotal)?></strong><small>Tổng ghi nhận: <?=bakeryMoney($recordedTotal)?></small></div>
<div class="bk-stat orange"><span>Đầu tư phần thô</span><strong><?=bakeryMoney($totals['investment'])?></strong></div>
<div class="bk-stat blue"><span>Thiết bị</span><strong><?=bakeryMoney($totals['equipment'])?></strong></div>
<div class="bk-stat orange"><span>Chi phí vận hành</span><strong><?=bakeryMoney($totals['operating'])?></strong></div>
<div class="bk-stat green"><span>Mua nguyên liệu</span><strong><?=bakeryMoney($totals['materials'])?></strong></div>
</section>
<div class="bk-table-wrap"><table class="bk-table"><thead><tr><th>Ngày</th><th>Hạng mục</th><th>Phân loại</th><th>Nội dung chi tiết</th><th>Đơn vị nhận / cung cấp</th><th>Trạng thái</th><th class="money">Số tiền</th><th></th></tr></thead><tbody>
<?php if (!$rows): ?><tr><td colspan="8"><div class="bk-empty">Không có dữ liệu trong thời gian đã chọn.</div></td></tr><?php endif; ?>
<?php foreach ($rows as $row): ?><tr>
<td><?=date('d/m/Y', strtotime($row['date']))?></td>
<td><strong><?=bakeryH($row['group'])?></strong></td>
<td><?=bakeryH($row['category'])?></td>
<td><strong><?=bakeryH($row['description'])?></strong><?php if ($row['note'] !== ''): ?><div class="bk-muted"><?=bakeryH($row['note'])?></div><?php endif; ?></td>
<td><?=bakeryH($row['party'] ?: '—')?></td>
<td><span class="bk-badge <?=$row['confirmed']?'green':'orange'?>"><?=bakeryH($row['status'])?></span></td>
<td class="money"><?=bakeryMoney($row['amount'])?></td>
<td><a class="bk-btn bk-btn-secondary bk-btn-small" href="<?=bakeryH($row['url'])?>">Mở</a></td>
</tr><?php endforeach; ?>
</tbody></table></div>
</main></body></html>
