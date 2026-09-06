<?php
require_once __DIR__ . '/../auth.php';
requireRole('admin');
require_once __DIR__ . '/../account_security.php';
require_once __DIR__ . '/catalog.php';
try { $websiteDb = ghe_database(); enforceAccountSession($websiteDb); requireRole('admin'); }
catch (Throwable $e) { error_log('Website admin DB: ' . $e->getMessage()); http_response_code(503); exit('Không kết nối được POS để xác thực tài khoản quản trị. Vui lòng thử lại sau.'); }
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store');
