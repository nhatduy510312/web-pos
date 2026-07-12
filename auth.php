<?php

if(session_status() === PHP_SESSION_NONE){
    session_start();
}

require_once __DIR__ . '/csrf.php';

/**
 * Chặn request nếu user hiện tại không có 1 trong các role được phép.
 * Gọi sau khi auth.php đã include (tức là user chắc chắn đã đăng nhập).
 */
function requireRole($allowedRoles)
{
    $allowedRoles = (array)$allowedRoles;
    $role = $_SESSION['role'] ?? '';

    if(in_array($role, $allowedRoles, true)){
        return;
    }

    if(defined('API_REQUEST') && API_REQUEST){
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Ban khong co quyen thuc hien hanh dong nay'
        ]);
    }else{
        http_response_code(403);
        echo 'Ban khong co quyen thuc hien hanh dong nay';
    }

    exit;
}

if(
!isset(
$_SESSION['logged_in']
)
){
    if(defined('API_REQUEST') && API_REQUEST){

        http_response_code(401);
        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'message' => 'Chua dang nhap'
        ]);

        exit;
    }

    header(
    'Location: login.php'
    );

    exit;
}

