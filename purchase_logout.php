<?php
require_once __DIR__ . '/purchase_auth.php';
clearPurchaseSession();
session_regenerate_id(true);
header('Location: purchase_login.php');
exit;
