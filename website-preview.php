<?php
require __DIR__ . '/site/admin-auth.php';
define('GHE_PREVIEW',true);
$previewPage = is_string($_GET['page'] ?? null) ? $_GET['page'] : 'home.php';
if (!in_array($previewPage,['home.php','gioi-thieu.php','thuc-don.php','ghe-uong-gi.php','den-ghe.php','chuyen-o-ghe.php','bai-viet.php','en.php'],true)) { http_response_code(404); exit('Không tìm thấy trang xem trước.'); }
require __DIR__ . '/' . $previewPage;
