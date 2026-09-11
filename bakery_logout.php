<?php
require_once __DIR__ . '/bakery_auth.php';
clearBakerySession();
session_regenerate_id(true);
header('Location: bakery_login.php');
exit;
