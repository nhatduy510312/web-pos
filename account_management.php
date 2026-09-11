<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();

$error = '';
$successMessages = [
    'created'=>'Đã tạo tài khoản.',
    'updated'=>'Đã cập nhật tài khoản.',
    'enabled'=>'Đã kích hoạt tài khoản.',
    'disabled'=>'Đã khóa tài khoản và thu hồi phiên đăng nhập.',
];
$success = $successMessages[$_GET['success'] ?? ''] ?? '';
$areaLabels = ['staff'=>'Nhân viên POS','purchaser'=>'Mua hàng','bakery_admin'=>'Bakery'];
$requestedArea = (string)($_GET['area'] ?? 'all');
$areaFilter = in_array($requestedArea, ['all','staff','purchaser','bakery_admin'], true) ? $requestedArea : 'all';
$createForm = [
    'account_type'=>in_array($_POST['account_type'] ?? '', array_keys($areaLabels), true) ? $_POST['account_type'] : 'staff',
    'name'=>trim((string)($_POST['name'] ?? '')),
    'username'=>trim((string)($_POST['username'] ?? '')),
    'employee_type'=>in_array($_POST['employee_type'] ?? '', ['probation','official'], true) ? $_POST['employee_type'] : 'probation',
];

function unifiedAccountUsernameValid(string $username): bool
{
    return (bool)preg_match('/^[A-Za-z0-9._-]{3,50}$/D', $username);
}

function unifiedAccountError(Throwable $exception): string
{
    if ($exception instanceof mysqli_sql_exception && (int)$exception->getCode() === 1062) {
        return 'Tên đăng nhập đã tồn tại. Vui lòng chọn tên khác.';
    }
    error_log('Unified account management failed: ' . $exception->getMessage());
    return 'Không thể lưu tài khoản. Vui lòng thử lại.';
}

function unifiedAccountRedirect(string $success, string $area): void
{
    header('Location: account_management.php?' . http_build_query(['area'=>$area, 'success'=>$success]));
    exit;
}

if (isset($_POST['create_account'])) {
    $role = $createForm['account_type'];
    $name = $createForm['name'];
    $username = $createForm['username'];
    $password = (string)($_POST['password'] ?? '');
    $employeeType = $createForm['employee_type'];
    if (!isset($areaLabels[$role])) $error = 'Vui lòng chọn đúng khu vực tài khoản.';
    elseif ($role === 'staff' && ($name === '' || mb_strlen($name, 'UTF-8') > 100)) $error = 'Vui lòng nhập tên nhân viên, tối đa 100 ký tự.';
    elseif (!unifiedAccountUsernameValid($username)) $error = 'Tên đăng nhập phải có 3–50 ký tự, chỉ gồm chữ, số, dấu chấm, gạch dưới hoặc gạch ngang.';
    elseif (strlen($password) < 8) $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    else {
        try {
            $conn->begin_transaction();
            $employeeId = null;
            if ($role === 'staff') {
                $stmt = $conn->prepare('INSERT INTO employees(name,employee_type,is_active) VALUES(?,?,1)');
                $stmt->bind_param('ss', $name, $employeeType); $stmt->execute();
                $employeeId = (int)$conn->insert_id;
            }
            $passwordHash = password_hash($password, PASSWORD_DEFAULT);
            $mustChange = $role === 'staff' ? 1 : 0;
            if ($employeeId !== null) {
                $stmt = $conn->prepare('INSERT INTO users(username,password,role,employee_id,is_active,must_change_password,session_version) VALUES(?,?,?,?,1,?,1)');
                $stmt->bind_param('sssii', $username, $passwordHash, $role, $employeeId, $mustChange);
            } else {
                $stmt = $conn->prepare('INSERT INTO users(username,password,role,employee_id,is_active,must_change_password,session_version) VALUES(?,?,?,NULL,1,?,1)');
                $stmt->bind_param('sssi', $username, $passwordHash, $role, $mustChange);
            }
            $stmt->execute();
            $conn->commit();
            unifiedAccountRedirect('created', $role);
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = unifiedAccountError($exception);
        }
    }
}

if (isset($_POST['update_account'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $name = trim((string)($_POST['name'] ?? ''));
    $employeeType = in_array($_POST['employee_type'] ?? '', ['probation','official'], true) ? $_POST['employee_type'] : 'probation';
    $current = null;
    if ($userId > 0) {
        $stmt = $conn->prepare("SELECT id,role,employee_id FROM users WHERE id=? AND role IN ('staff','user','purchaser','bakery_admin') LIMIT 1");
        $stmt->bind_param('i', $userId); $stmt->execute();
        $current = $stmt->get_result()->fetch_assoc();
    }
    if ($current) {
        $role = in_array($current['role'], ['staff','user'], true) ? 'staff' : $current['role'];
        $linkedEmployeeId = (int)($current['employee_id'] ?? 0);
        if (!unifiedAccountUsernameValid($username)) $error = 'Tên đăng nhập không hợp lệ.';
        elseif ($password !== '' && strlen($password) < 8) $error = 'Mật khẩu mới phải có ít nhất 8 ký tự.';
        elseif ($linkedEmployeeId > 0 && ($name === '' || mb_strlen($name, 'UTF-8') > 100)) $error = 'Tên nhân viên không hợp lệ.';
        else {
            try {
                $conn->begin_transaction();
                if ($linkedEmployeeId > 0) {
                    $stmt = $conn->prepare('UPDATE employees SET name=?,employee_type=? WHERE id=?');
                    $stmt->bind_param('ssi', $name, $employeeType, $linkedEmployeeId); $stmt->execute();
                }
                if ($password !== '') {
                    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                    $mustChange = $role === 'staff' ? 1 : 0;
                    $stmt = $conn->prepare('UPDATE users SET username=?,password=?,must_change_password=?,session_version=session_version+1 WHERE id=?');
                    $stmt->bind_param('ssii', $username, $passwordHash, $mustChange, $userId);
                } else {
                    $stmt = $conn->prepare('UPDATE users SET username=? WHERE id=?');
                    $stmt->bind_param('si', $username, $userId);
                }
                $stmt->execute();
                $conn->commit();
                unifiedAccountRedirect('updated', $role);
            } catch (Throwable $exception) {
                $conn->rollback();
                $error = unifiedAccountError($exception);
            }
        }
    } elseif ($userId === 0 && $employeeId > 0) {
        $stmt = $conn->prepare('SELECT id,name,is_active FROM employees WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $employeeId); $stmt->execute();
        $employee = $stmt->get_result()->fetch_assoc();
        if (!$employee) $error = 'Không tìm thấy nhân viên.';
        elseif ($name === '' || mb_strlen($name, 'UTF-8') > 100) $error = 'Tên nhân viên không hợp lệ.';
        elseif (!unifiedAccountUsernameValid($username)) $error = 'Tên đăng nhập không hợp lệ.';
        elseif (strlen($password) < 8) $error = 'Nhân viên chưa có tài khoản; vui lòng nhập mật khẩu ít nhất 8 ký tự.';
        else {
            try {
                $conn->begin_transaction();
                $stmt = $conn->prepare('UPDATE employees SET name=?,employee_type=? WHERE id=?');
                $stmt->bind_param('ssi', $name, $employeeType, $employeeId); $stmt->execute();
                $passwordHash = password_hash($password, PASSWORD_DEFAULT);
                $role = 'staff'; $active = (int)$employee['is_active'];
                $stmt = $conn->prepare('INSERT INTO users(username,password,role,employee_id,is_active,must_change_password,session_version) VALUES(?,?,?,?,?,1,1)');
                $stmt->bind_param('sssii', $username, $passwordHash, $role, $employeeId, $active); $stmt->execute();
                $conn->commit();
                unifiedAccountRedirect('updated', 'staff');
            } catch (Throwable $exception) {
                $conn->rollback();
                $error = unifiedAccountError($exception);
            }
        }
    } else {
        $error = 'Không tìm thấy tài khoản.';
    }
}

if (isset($_POST['set_active'])) {
    $userId = (int)($_POST['user_id'] ?? 0);
    $employeeId = (int)($_POST['employee_id'] ?? 0);
    $active = (int)($_POST['set_active'] ?? 0) === 1 ? 1 : 0;
    $role = 'staff';
    try {
        $conn->begin_transaction();
        if ($userId > 0) {
            $stmt = $conn->prepare("SELECT role,employee_id FROM users WHERE id=? AND role IN ('staff','user','purchaser','bakery_admin') LIMIT 1");
            $stmt->bind_param('i', $userId); $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            if (!$account) throw new RuntimeException('Account not found');
            $role = in_array($account['role'], ['staff','user'], true) ? 'staff' : $account['role'];
            $employeeId = (int)($account['employee_id'] ?? 0);
            $stmt = $conn->prepare('UPDATE users SET is_active=?,session_version=session_version+1 WHERE id=?');
            $stmt->bind_param('ii', $active, $userId); $stmt->execute();
        }
        if ($employeeId > 0) {
            $stmt = $conn->prepare('UPDATE employees SET is_active=? WHERE id=?');
            $stmt->bind_param('ii', $active, $employeeId); $stmt->execute();
        }
        if ($userId < 1 && $employeeId < 1) throw new RuntimeException('Target not found');
        $conn->commit();
        unifiedAccountRedirect($active ? 'enabled' : 'disabled', $role);
    } catch (Throwable $exception) {
        $conn->rollback();
        $error = unifiedAccountError($exception);
    }
}

$accounts = [];
$result = $conn->query(
    "SELECT u.id AS user_id,u.username,u.role,u.employee_id,u.is_active AS account_active,
            u.must_change_password,u.last_login_at,u.created_at,
            e.name,e.employee_type,e.is_active AS employee_active
     FROM users u LEFT JOIN employees e ON e.id=u.employee_id
     WHERE u.role IN ('staff','user','purchaser','bakery_admin')"
);
while ($row = $result->fetch_assoc()) {
    $row['area'] = in_array($row['role'], ['staff','user'], true) ? 'staff' : $row['role'];
    $row['display_name'] = $row['name'] ?: ($row['role'] === 'user' ? 'Tài khoản POS dùng chung' : '—');
    $row['active'] = (int)$row['account_active'] === 1 && ($row['employee_id'] === null || (int)$row['employee_active'] === 1);
    $row['orphan'] = false;
    $accounts[] = $row;
}
$result = $conn->query(
    'SELECT e.id AS employee_id,e.name,e.employee_type,e.is_active AS employee_active
     FROM employees e LEFT JOIN users u ON u.employee_id=e.id WHERE u.id IS NULL'
);
while ($row = $result->fetch_assoc()) {
    $accounts[] = [
        'user_id'=>0,'username'=>'','role'=>'staff','employee_id'=>$row['employee_id'],
        'account_active'=>0,'must_change_password'=>0,'last_login_at'=>null,'created_at'=>null,
        'name'=>$row['name'],'employee_type'=>$row['employee_type'],'employee_active'=>$row['employee_active'],
        'area'=>'staff','display_name'=>$row['name'],'active'=>(int)$row['employee_active']===1,'orphan'=>true,
    ];
}
$counts = ['staff'=>0,'purchaser'=>0,'bakery_admin'=>0];
$activeCounts = ['staff'=>0,'purchaser'=>0,'bakery_admin'=>0];
foreach ($accounts as $row) {
    $counts[$row['area']]++;
    if ($row['active'] && !$row['orphan']) $activeCounts[$row['area']]++;
}
usort($accounts, static function (array $left, array $right): int {
    $areaOrder = ['staff'=>0,'purchaser'=>1,'bakery_admin'=>2];
    return [$areaOrder[$left['area']], !$left['active'], strtolower($left['display_name']), strtolower($left['username'])]
        <=> [$areaOrder[$right['area']], !$right['active'], strtolower($right['display_name']), strtolower($right['username'])];
});
$visibleAccounts = array_values(array_filter($accounts, static fn(array $row): bool => $areaFilter === 'all' || $row['area'] === $areaFilter));
$loginHistory = $conn->query(
    "SELECT h.id,h.username,h.result,h.ip_address,h.user_agent,h.created_at,
            u.role,COALESCE(e.name,h.username) AS display_name
     FROM login_history h
     LEFT JOIN users u ON u.id=h.user_id
     LEFT JOIN employees e ON e.id=u.employee_id
     ORDER BY h.id DESC LIMIT 100"
)->fetch_all(MYSQLI_ASSOC);
$loginResultLabels = [
    'success'=>'Đăng nhập POS thành công',
    'purchase_success'=>'Đăng nhập Mua hàng thành công',
    'bakery_success'=>'Đăng nhập Bakery thành công',
    'password_changed'=>'Đã đổi mật khẩu',
    'failed'=>'Sai tài khoản hoặc mật khẩu',
    'purchase_failed'=>'Đăng nhập Mua hàng thất bại',
    'bakery_failed'=>'Đăng nhập Bakery thất bại',
    'inactive'=>'Tài khoản POS đã khóa',
    'purchase_inactive'=>'Tài khoản Mua hàng đã khóa',
    'bakery_inactive'=>'Tài khoản Bakery đã khóa',
    'rate_limited'=>'POS bị giới hạn đăng nhập',
    'purchase_rate_limited'=>'Mua hàng bị giới hạn đăng nhập',
    'bakery_rate_limited'=>'Bakery bị giới hạn đăng nhập',
    'wrong_portal'=>'Tài khoản Mua hàng đăng nhập nhầm POS',
    'wrong_bakery_portal'=>'Tài khoản Bakery đăng nhập nhầm POS',
];
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="favicon.svg"><title>Quản lý tài khoản — GHÉ</title>
<style>
.account-layout{display:grid;grid-template-columns:minmax(300px,380px) minmax(0,1fr);gap:16px;align-items:start}.account-table{min-width:1180px}.account-actions{display:flex;gap:6px;align-items:center;white-space:nowrap}.account-tabs{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}.account-portal-links{display:flex;gap:8px;flex-wrap:wrap}.account-stats{margin-bottom:16px}@media(max-width:850px){.account-layout{grid-template-columns:1fr}}
</style></head><body>
<?php include 'menu.php'; ?>
<main class="page-content">
<header class="page-hdr"><div class="page-hdr-left"><h1>👥 Quản lý tài khoản</h1><p>Một nơi quản lý nhân viên POS, tài khoản Mua hàng và tài khoản Bakery.</p></div><div class="account-portal-links"><a class="btn btn-secondary" href="purchase_login.php" target="_blank" rel="noopener">Mở Mua hàng</a><a class="btn btn-secondary" href="bakery_login.php" target="_blank" rel="noopener">Mở Bakery</a></div></header>
<?php if ($error): ?><div class="alert alert-err" style="margin-bottom:14px">⚠️ <?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-ok" style="margin-bottom:14px">✅ <?=htmlspecialchars($success)?></div><?php endif; ?>
<section class="stats-grid account-stats">
<?php foreach ($areaLabels as $key=>$label): ?><div class="stat-card"><div class="stat-label"><?=htmlspecialchars($label)?></div><div class="stat-value <?=$key==='staff'?'c-blue':($key==='purchaser'?'c-green':'c-orange')?>"><?=$activeCounts[$key]?></div><div class="stat-sub">Đang hoạt động / tổng <?=$counts[$key]?></div></div><?php endforeach; ?>
</section>
<div class="account-layout">
<section class="card"><div class="section-title">➕ Tạo tài khoản</div>
<form method="post" autocomplete="off"><?=csrf_field()?>
<div class="form-group"><label class="form-label">Khu vực sử dụng</label><select class="form-select" name="account_type" id="account-type"><?php foreach ($areaLabels as $key=>$label): ?><option value="<?=$key?>" <?=$createForm['account_type']===$key?'selected':''?>><?=htmlspecialchars($label)?></option><?php endforeach; ?></select></div>
<div id="employee-fields">
<div class="form-group"><label class="form-label">Tên nhân viên</label><input class="form-input" id="employee-name" name="name" maxlength="100" value="<?=htmlspecialchars($createForm['name'])?>" placeholder="Nguyễn Văn A"></div>
<div class="form-group"><label class="form-label">Loại hợp đồng</label><select class="form-select" name="employee_type"><option value="probation" <?=$createForm['employee_type']==='probation'?'selected':''?>>Thử việc (20.000đ/h)</option><option value="official" <?=$createForm['employee_type']==='official'?'selected':''?>>Chính thức (25.000đ/h)</option></select></div>
</div>
<div class="form-group"><label class="form-label">Tên đăng nhập</label><input class="form-input" name="username" minlength="3" maxlength="50" required autocomplete="off" value="<?=htmlspecialchars($createForm['username'])?>"></div>
<div class="form-group"><label class="form-label">Mật khẩu</label><input class="form-input" type="password" name="password" minlength="8" required autocomplete="new-password" placeholder="Ít nhất 8 ký tự"></div>
<button class="btn btn-primary btn-block" name="create_account">Tạo tài khoản</button>
</form>
<div class="alert alert-info" style="margin-top:14px">Nhân viên POS sẽ phải đổi mật khẩu ở lần đăng nhập đầu tiên. Tài khoản Mua hàng và Bakery dùng trang đăng nhập riêng.</div>
</section>
<section>
<nav class="account-tabs"><?php foreach (['all'=>'Tất cả']+$areaLabels as $key=>$label): ?><a class="btn btn-sm <?=$areaFilter===$key?'btn-primary':'btn-secondary'?>" href="?area=<?=$key?>"><?=htmlspecialchars($label)?></a><?php endforeach; ?></nav>
<div class="tbl-wrap"><table class="tbl account-table"><thead><tr><th>Khu vực</th><th>Tên nhân viên</th><th>Loại</th><th>Tên đăng nhập</th><th>Trạng thái</th><th>Đăng nhập cuối</th><th>Mật khẩu mới</th><th>Thao tác</th></tr></thead><tbody>
<?php if (!$visibleAccounts): ?><tr><td colspan="8"><div class="empty-state">Chưa có tài khoản trong khu vực này.</div></td></tr><?php endif; ?>
<?php foreach ($visibleAccounts as $row): $formId='account-'.(int)$row['user_id'].'-'.(int)$row['employee_id']; ?>
<tr>
<td><span class="badge <?=$row['area']==='staff'?'badge-blue':($row['area']==='purchaser'?'badge-green':'badge-yellow')?>"><?=htmlspecialchars($areaLabels[$row['area']])?></span></td>
<td><?php if ($row['area']==='staff' && (int)$row['employee_id']>0): ?><input form="<?=$formId?>" class="form-input" name="name" maxlength="100" required value="<?=htmlspecialchars($row['display_name'])?>"><?php else: ?><span class="text-muted"><?=htmlspecialchars($row['display_name'])?></span><?php endif; ?></td>
<td><?php if ($row['area']==='staff' && (int)$row['employee_id']>0): ?><select form="<?=$formId?>" class="form-select" name="employee_type" style="width:125px"><option value="probation" <?=$row['employee_type']==='probation'?'selected':''?>>Thử việc</option><option value="official" <?=$row['employee_type']==='official'?'selected':''?>>Chính thức</option></select><?php else: ?>—<?php endif; ?></td>
<td><input form="<?=$formId?>" class="form-input" name="username" minlength="3" maxlength="50" required value="<?=htmlspecialchars($row['username'])?>" placeholder="Tên đăng nhập"></td>
<td><?php if ($row['orphan']): ?><span class="badge badge-yellow">Chưa có tài khoản</span><?php elseif (!$row['active']): ?><span class="badge badge-gray">Đã khóa</span><?php elseif ((int)$row['must_change_password']===1 && $row['area']==='staff'): ?><span class="badge badge-yellow">Chờ đổi mật khẩu</span><?php else: ?><span class="badge badge-green">Hoạt động</span><?php endif; ?></td>
<td class="text-sm text-muted"><?=$row['last_login_at']?date('d/m/Y H:i',strtotime($row['last_login_at'])):'—'?></td>
<td><input form="<?=$formId?>" class="form-input" type="password" name="password" minlength="8" <?=$row['orphan']?'required':''?> autocomplete="new-password" placeholder="<?=$row['orphan']?'Bắt buộc để cấp TK':'Để trống nếu giữ nguyên'?>"></td>
<td><div class="account-actions"><form method="post" id="<?=$formId?>"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=(int)$row['user_id']?>"><input type="hidden" name="employee_id" value="<?=(int)$row['employee_id']?>"><button class="btn btn-sm btn-primary" name="update_account"><?=$row['orphan']?'Cấp tài khoản':'Lưu'?></button></form><form method="post" onsubmit="return confirm('<?=$row['active']?'Khóa':'Kích hoạt'?> tài khoản này?')"><?=csrf_field()?><input type="hidden" name="user_id" value="<?=(int)$row['user_id']?>"><input type="hidden" name="employee_id" value="<?=(int)$row['employee_id']?>"><button class="btn btn-sm <?=$row['active']?'btn-danger':'btn-success'?>" name="set_active" value="<?=$row['active']?0:1?>"><?=$row['active']?'Khóa':'Kích hoạt'?></button></form></div></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section></div>
<section style="margin-top:20px">
<div class="section-title">🔐 100 lần đăng nhập gần nhất</div>
<div class="tbl-wrap"><table class="tbl"><thead><tr><th>Thời gian</th><th>Khu vực</th><th>Tài khoản / Nhân viên</th><th>Kết quả</th><th>IP</th><th>Thiết bị</th></tr></thead><tbody>
<?php if (!$loginHistory): ?><tr><td colspan="6"><div class="empty-state">Chưa có lịch sử đăng nhập.</div></td></tr><?php endif; ?>
<?php foreach ($loginHistory as $history):
    $historyRole = (string)($history['role'] ?? '');
    $historyResult = (string)$history['result'];
    if ($historyRole === 'purchaser' || str_starts_with($historyResult, 'purchase_') || $historyResult === 'wrong_portal') {
        $historyArea = 'Mua hàng'; $historyAreaClass = 'badge-green';
    } elseif ($historyRole === 'bakery_admin' || str_starts_with($historyResult, 'bakery_') || $historyResult === 'wrong_bakery_portal') {
        $historyArea = 'Bakery'; $historyAreaClass = 'badge-yellow';
    } else {
        $historyArea = 'POS'; $historyAreaClass = 'badge-blue';
    }
    $historySuccess = in_array($historyResult, ['success','purchase_success','bakery_success'], true);
    $historyChanged = $historyResult === 'password_changed';
    $resultClass = $historySuccess ? 'badge-green' : ($historyChanged ? 'badge-blue' : 'badge-red');
?>
<tr>
<td class="text-sm"><?=date('d/m/Y H:i:s', strtotime($history['created_at']))?></td>
<td><span class="badge <?=$historyAreaClass?>"><?=htmlspecialchars($historyArea)?></span></td>
<td class="fw-600"><?=htmlspecialchars($history['display_name'])?> <span class="text-muted">(<?=htmlspecialchars($history['username'])?>)</span></td>
<td><span class="badge <?=$resultClass?>"><?=htmlspecialchars($loginResultLabels[$historyResult] ?? $historyResult)?></span></td>
<td><?=htmlspecialchars($history['ip_address'] ?: '—')?></td>
<td class="text-sm text-muted" style="max-width:360px;white-space:normal"><?=htmlspecialchars($history['user_agent'] ?: '—')?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
</section>
</main>
<script>(function(){const type=document.getElementById('account-type'),fields=document.getElementById('employee-fields'),name=document.getElementById('employee-name');function update(){const staff=type.value==='staff';fields.style.display=staff?'block':'none';name.required=staff}type.addEventListener('change',update);update()})()</script>
</body></html>
