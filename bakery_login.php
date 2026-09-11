<?php
require_once __DIR__ . '/bakery_auth.php';
require_once __DIR__ . '/login_rate_limit.php';
$bakeryDb = bakeryDbOrFail();
$error = isset($_GET['reason']) ? 'Phiên đăng nhập đã hết hiệu lực. Vui lòng đăng nhập lại.' : '';
$created = isset($_GET['created']);

if (!empty($_SESSION['bakery_logged_in'])) {
    requireBakerySession($bakeryDb);
    header('Location: bakery.php'); exit;
}

$accountCount = (int)$bakeryDb->query("SELECT COUNT(*) total FROM users WHERE role='bakery_admin'")->fetch_assoc()['total'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $username = substr(trim((string)($_POST['username'] ?? '')), 0, 50);
    $password = (string)($_POST['password'] ?? '');
    $rateName = 'bakery:' . $username;
    if (loginIsRateLimited($rateName)) {
        recordLoginHistory($bakeryDb, null, $username, 'bakery_rate_limited');
        $error = 'Đăng nhập sai quá nhiều lần. Vui lòng thử lại sau 15 phút.';
    } else {
        $stmt = $bakeryDb->prepare("SELECT id,username,password,is_active,session_version FROM users WHERE LOWER(username)=LOWER(?) AND role='bakery_admin' LIMIT 1");
        $stmt->bind_param('s', $username); $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        if ($account && (int)$account['is_active'] === 1 && password_verify($password, $account['password'])) {
            clearLoginFailures($rateName); session_regenerate_id(true);
            $_SESSION['bakery_logged_in']=true; $_SESSION['bakery_user_id']=(int)$account['id'];
            $_SESSION['bakery_username']=$account['username']; $_SESSION['bakery_session_version']=(int)$account['session_version'];
            $userId=(int)$account['id']; $stmt=$bakeryDb->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
            $stmt->bind_param('i',$userId); $stmt->execute(); recordLoginHistory($bakeryDb,$userId,$account['username'],'bakery_success');
            header('Location: bakery.php'); exit;
        }
        recordLoginFailure($rateName); recordLoginHistory($bakeryDb,$account?(int)$account['id']:null,$username,$account&&!(int)$account['is_active']?'bakery_inactive':'bakery_failed');
        $error='Sai tài khoản hoặc mật khẩu.';
    }
}
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="icon" type="image/svg+xml" href="bakery_favicon.svg"><link rel="stylesheet" href="assets/bakery.css"><title>Đăng nhập quản lý bếp bánh — GHÉ</title></head>
<body class="bk-login-body"><main class="bk-login-wrap"><?=bakeryLanguageSwitcher('bk-language-login')?><div class="bk-login-brand"><div class="icon">🥐</div><h1>Quản lý bếp bánh</h1><p>Khu vực quản trị đầu tư, thiết bị, nguyên liệu và tồn kho</p></div>
<section class="bk-card"><?php if($error!==''):?><div class="bk-alert bk-alert-error"><?=bakeryH($error)?></div><?php endif;?><?php if($created):?><div class="bk-alert bk-alert-success">Đã tạo tài khoản. Bạn có thể đăng nhập ngay.</div><?php endif;?>
<form method="post"><?=csrf_field()?><label class="bk-label" for="username">Tài khoản</label><input class="bk-input" id="username" name="username" required maxlength="50" autocomplete="username" autofocus value="<?=bakeryH($_POST['username']??'')?>"><label class="bk-label" for="password">Mật khẩu</label><input class="bk-input" type="password" id="password" name="password" required autocomplete="current-password"><button class="bk-btn bk-btn-primary bk-btn-block">Đăng nhập</button></form>
<?php if($accountCount===0):?><div class="bk-alert bk-alert-info" style="margin-top:14px;margin-bottom:0">Chưa có tài khoản. Admin POS vào menu <strong>Tài khoản</strong>, chọn khu vực <strong>Bakery</strong> để tạo.</div><?php endif;?></section><p class="bk-login-note">Tài khoản bếp bánh không truy cập được hệ thống POS.</p></main><?=bakeryTranslationScript()?></body></html>
