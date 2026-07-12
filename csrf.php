<?php

/* ====================================================
   CSRF helper
   - csrf_token(): lấy/tạo token cho session hiện tại
   - csrf_field(): in ra <input hidden> để nhúng vào <form>
   - csrf_require($token): chặn request nếu token sai
   ==================================================== */

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES)
        . '">';
}

function csrf_require($token)
{
    $valid = !empty($token)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);

    if ($valid) {
        return;
    }

    if (defined('API_REQUEST') && API_REQUEST) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Phien lam viec da het han, vui long tai lai trang.'
        ]);
    } else {
        http_response_code(403);
        echo 'Phien lam viec da het han, vui long tai lai trang.';
    }

    exit;
}
