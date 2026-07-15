<?php
session_start();
require 'config.php';

$error = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare(
        "SELECT id, username, password, role FROM users WHERE LOWER(username)=LOWER(?)"
    );
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();

    if ($row && password_verify($password, $row['password'])) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        $_SESSION['user_id']   = $row['id'];
        $_SESSION['username']  = $row['username'];
        $_SESSION['role']      = $row['role'] ?? 'staff';
        header('Location: index.php');
        exit;
    }
    $error = 'Sai tài khoản hoặc mật khẩu.';
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đăng nhập — GHÉ Coffee</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{
  font-family:'Inter',-apple-system,sans-serif;
  background:#f1f5f9;
  min-height:100vh;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:20px;
  -webkit-font-smoothing:antialiased;
}
.wrap{width:100%;max-width:380px;}
.logo{
  text-align:center;
  margin-bottom:28px;
}
.logo-icon{
  width:52px;height:52px;
  background:#2563eb;
  border-radius:16px;
  display:inline-flex;
  align-items:center;justify-content:center;
  font-size:26px;
  margin-bottom:12px;
  box-shadow:0 4px 14px rgba(37,99,235,.35);
}
.logo h1{font-size:20px;font-weight:700;color:#0f172a;letter-spacing:-.02em;}
.logo p{font-size:13px;color:#64748b;margin-top:3px;}
.card{
  background:#fff;
  border-radius:20px;
  padding:28px;
  box-shadow:0 4px 24px rgba(0,0,0,.07);
  border:1px solid #e2e8f0;
}
.field{margin-bottom:14px;}
label{
  display:block;
  font-size:11px;font-weight:700;
  text-transform:uppercase;letter-spacing:.05em;
  color:#64748b;margin-bottom:5px;
}
input{
  width:100%;padding:10px 13px;
  font-size:14px;color:#0f172a;
  background:#fff;
  border:1px solid #e2e8f0;
  border-radius:9px;
  outline:none;
  transition:.15s ease;
  font-family:inherit;
}
input:focus{border-color:#2563eb;box-shadow:0 0 0 3px #eff6ff;}
input::placeholder{color:#94a3b8;}
.error{
  display:flex;align-items:center;gap:8px;
  background:#fef2f2;color:#dc2626;
  border:1px solid #fecaca;
  border-radius:9px;
  padding:10px 13px;
  font-size:13px;font-weight:500;
  margin-bottom:14px;
}
.submit{
  width:100%;padding:12px;
  background:#2563eb;color:#fff;
  font-size:14px;font-weight:700;
  border:none;border-radius:9px;
  cursor:pointer;
  transition:.15s ease;
  margin-top:4px;
  font-family:inherit;
}
.submit:hover{background:#1d4ed8;}
.submit:active{transform:scale(.99);}
.footer-note{
  text-align:center;
  margin-top:20px;
  font-size:12px;
  color:#94a3b8;
}
</style>
</head>
<body>
<div class="wrap">
  <div class="logo">
    <div class="logo-icon">☕</div>
    <h1>GHÉ Coffee & Tea</h1>
    <p>Hệ thống quản lý bán hàng</p>
  </div>

  <div class="card">
    <?php if ($error): ?>
    <div class="error">⚠️ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="on">
      <div class="field">
        <label for="username">Tài khoản</label>
        <input type="text" id="username" name="username"
               placeholder="Nhập tài khoản" required autofocus
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Mật khẩu</label>
        <input type="password" id="password" name="password"
               placeholder="Nhập mật khẩu" required>
      </div>
      <button class="submit" type="submit" name="login">Đăng nhập</button>
    </form>
  </div>

  <p class="footer-note">GHÉ Coffee and Tea · POS v2</p>
</div>
</body>
</html>
