<?php
include 'auth.php';
include 'config.php';
requireRole('admin');
requireCsrfForFormPost();
$error='';
$count=(int)$conn->query("SELECT COUNT(*) total FROM users WHERE role='bakery_admin'")->fetch_assoc()['total'];
if($count>0){header('Location: bakery_login.php');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
    $username=trim((string)($_POST['username']??''));$password=(string)($_POST['password']??'');$confirm=(string)($_POST['confirm_password']??'');
    if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/D',$username))$error='Tên đăng nhập phải có 3–50 ký tự hợp lệ.';
    elseif(strlen($password)<8)$error='Mật khẩu phải có ít nhất 8 ký tự.';
    elseif($password!==$confirm)$error='Xác nhận mật khẩu không khớp.';
    else try{$hash=password_hash($password,PASSWORD_DEFAULT);$role='bakery_admin';$stmt=$conn->prepare("INSERT INTO users(username,password,role,employee_id,is_active,must_change_password,session_version) VALUES(?,?,?,NULL,1,0,1)");$stmt->bind_param('sss',$username,$hash,$role);$stmt->execute();header('Location: bakery_login.php?created=1');exit;}catch(Throwable $e){$error=(int)$e->getCode()===1062?'Tên đăng nhập đã tồn tại.':'Không thể tạo tài khoản. Vui lòng thử lại.';error_log('Bakery install: '.$e->getMessage());}
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/bakery.css"><title>Khởi tạo quản lý bếp bánh</title></head><body class="bk-login-body"><main class="bk-login-wrap"><div class="bk-login-brand"><div class="icon">🥐</div><h1>Khởi tạo bếp bánh</h1><p>Tạo tài khoản quản trị duy nhất cho module độc lập</p></div><section class="bk-card"><?php if($error):?><div class="bk-alert bk-alert-error"><?=htmlspecialchars($error,ENT_QUOTES,'UTF-8')?></div><?php endif;?><form method="post" autocomplete="off"><?=csrf_field()?><label class="bk-label">Tên đăng nhập</label><input class="bk-input" name="username" minlength="3" maxlength="50" required><label class="bk-label">Mật khẩu</label><input class="bk-input" type="password" name="password" minlength="8" required><label class="bk-label">Nhập lại mật khẩu</label><input class="bk-input" type="password" name="confirm_password" minlength="8" required><button class="bk-btn bk-btn-primary bk-btn-block">Tạo tài khoản quản trị</button></form></section></main></body></html>
