<?php
require_once __DIR__ . '/bakery_auth.php';
$bakeryDb=bakeryDbOrFail();$bakeryAccount=requireBakerySession($bakeryDb);$error='';$success='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_require($_POST['csrf_token']??'');$username=trim((string)($_POST['username']??''));$current=(string)($_POST['current_password']??'');$new=(string)($_POST['new_password']??'');$confirm=(string)($_POST['confirm_password']??'');$id=(int)$bakeryAccount['id'];
    $stmt=$bakeryDb->prepare("SELECT password,session_version FROM users WHERE id=? AND role='bakery_admin'");$stmt->bind_param('i',$id);$stmt->execute();$stored=$stmt->get_result()->fetch_assoc();
    if(!preg_match('/^[A-Za-z0-9._-]{3,50}$/D',$username))$error='Tên đăng nhập phải có 3–50 ký tự hợp lệ.';
    elseif(!$stored||!password_verify($current,$stored['password']))$error='Mật khẩu hiện tại không đúng.';
    elseif($new!==''&&strlen($new)<8)$error='Mật khẩu mới phải có ít nhất 8 ký tự.';
    elseif($new!==$confirm)$error='Xác nhận mật khẩu mới không khớp.';
    else try{
        if($new!==''){$hash=password_hash($new,PASSWORD_DEFAULT);$stmt=$bakeryDb->prepare("UPDATE users SET username=?,password=?,session_version=session_version+1 WHERE id=? AND role='bakery_admin'");$stmt->bind_param('ssi',$username,$hash,$id);$_SESSION['bakery_session_version']=(int)$stored['session_version']+1;}
        else{$stmt=$bakeryDb->prepare("UPDATE users SET username=? WHERE id=? AND role='bakery_admin'");$stmt->bind_param('si',$username,$id);}
        $stmt->execute();$_SESSION['bakery_username']=$username;session_regenerate_id(true);$bakeryAccount['username']=$username;$success='Đã cập nhật tài khoản.';
    }catch(Throwable $e){$error=(int)$e->getCode()===1062?'Tên đăng nhập đã tồn tại.':'Không thể cập nhật tài khoản.';error_log('Bakery account: '.$e->getMessage());}
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" href="favicon.svg"><title>Tài khoản bếp bánh</title></head><body><?php require __DIR__.'/bakery_nav.php';?><main class="bk-shell" style="max-width:620px"><header class="bk-header"><div><h1>Tài khoản quản trị</h1><p>Đổi tên đăng nhập hoặc mật khẩu của module bếp bánh.</p></div></header><?php if($error):?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif;?><?php if($success):?><div class="bk-alert bk-alert-success"><?=bakeryH($success)?></div><?php endif;?><section class="bk-card"><form method="post" autocomplete="off"><?=csrf_field()?><label class="bk-label">Tên đăng nhập</label><input class="bk-input" name="username" minlength="3" maxlength="50" required value="<?=bakeryH($bakeryAccount['username'])?>"><label class="bk-label">Mật khẩu hiện tại</label><input class="bk-input" type="password" name="current_password" required autocomplete="current-password"><label class="bk-label">Mật khẩu mới</label><input class="bk-input" type="password" name="new_password" minlength="8" autocomplete="new-password" placeholder="Để trống nếu giữ nguyên"><label class="bk-label">Nhập lại mật khẩu mới</label><input class="bk-input" type="password" name="confirm_password" autocomplete="new-password"><button class="bk-btn bk-btn-primary bk-btn-block">Lưu tài khoản</button></form></section></main></body></html>
