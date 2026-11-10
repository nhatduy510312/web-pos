<?php
date_default_timezone_set(getenv('POS_TIMEZONE') ?: 'Asia/Ho_Chi_Minh');
session_name('ghe_bread_preorder');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();
$postedLanguage = (string)($_POST['language'] ?? '');
if (in_array($postedLanguage, ['vi', 'en'], true)) {
    $_SESSION['bread_preorder_language'] = $postedLanguage;
}
$language = ($_SESSION['bread_preorder_language'] ?? 'vi') === 'en' ? 'en' : 'vi';
define('API_REQUEST', true);
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/site/content-store.php';
$state = ghe_state_read();
$businessSettings = $state['published']['settings'] ?? [];
$openTime = preg_match('/^\d{2}:\d{2}$/D', (string)($businessSettings['opens'] ?? '07:00')) ? $businessSettings['opens'] : '07:00';
$closeTime = preg_match('/^\d{2}:\d{2}$/D', (string)($businessSettings['closes'] ?? '18:00')) ? $businessSettings['closes'] : '18:00';

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

function breadFail(string $message): never
{
    $englishMessages = [
        'Không thể gửi đơn lúc này. Vui lòng thử lại.' => 'We could not submit your order. Please try again.',
        'Vui lòng nhập tên khách hàng hợp lệ.' => 'Please enter a valid name.',
        'Vui lòng nhập số điện thoại hợp lệ để 24boulangerie xác nhận đơn.' => 'Please enter a valid phone number so we can confirm your order.',
        'Vui lòng chọn cách nhận bánh.' => 'Please choose how you would like to receive your order.',
        'Vui lòng nhập địa chỉ giao hàng.' => 'Please enter a delivery address.',
        'Lời nhắn quá dài.' => 'Your note is too long.',
        'Giỏ bánh trống hoặc có quá nhiều món.' => 'Your cart is empty or contains too many items.',
        'Thông tin giỏ bánh không hợp lệ.' => 'The cart information is invalid.',
        'Món hoặc số lượng trong giỏ không hợp lệ.' => 'A product or quantity in your cart is invalid.',
        'Ngày nhận/giao bánh không hợp lệ.' => 'The requested pickup or delivery date is invalid.',
        'Đơn Pre-Order cần đặt trước ít nhất 3 ngày, tối đa 30 ngày.' => 'Pre-orders must be placed at least 3 days and no more than 30 days in advance.',
        'Sản phẩm hằng ngày cần đặt trước ít nhất 1 ngày, tối đa 30 ngày.' => 'Daily products must be ordered at least 1 day and no more than 30 days in advance.',
        'Vui lòng chọn khung giờ hợp lệ.' => 'Please choose a valid time.',
        'Giờ nhận/giao nằm ngoài khung giờ hoạt động.' => 'The selected time is outside our opening hours.',
        'Một món trong giỏ vừa bị ẩn hoặc ngừng bán. Vui lòng tải lại menu.' => 'A product in your cart was hidden or is no longer available. Please reload the menu.',
        'Tổng đơn hàng vượt giới hạn. Vui lòng chia nhỏ đơn.' => 'The order total exceeds the limit. Please split your order.',
        'Chưa gửi được đơn do hệ thống đang bận. Bạn vui lòng thử lại hoặc gọi 24boulangerie.' => 'We could not submit your order because the system is busy. Please try again or call the bakery.',
    ];
    $language = ($_SESSION['bread_preorder_language'] ?? 'vi') === 'en' ? 'en' : 'vi';
    $_SESSION['bread_preorder_error'] = $language === 'en' ? ($englishMessages[$message] ?? $message) : $message;
    header('Location: banh-mi.php?lang=' . $language . '#checkout', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Phương thức không hợp lệ.');
}
csrf_require($_POST['csrf_token'] ?? '');
if (trim((string)($_POST['website'] ?? '')) !== '') breadFail('Không thể gửi đơn lúc này. Vui lòng thử lại.');

$customerName = trim((string)($_POST['customer_name'] ?? ''));
$customerPhone = trim((string)($_POST['customer_phone'] ?? ''));
$fulfillment = (string)($_POST['fulfillment_method'] ?? '');
$deliveryAddress = trim((string)($_POST['delivery_address'] ?? ''));
$pickupDate = trim((string)($_POST['pickup_date'] ?? ''));
$pickupTime = trim((string)($_POST['pickup_time'] ?? ''));
$customerNote = trim((string)($_POST['customer_note'] ?? ''));
$items = json_decode((string)($_POST['items_json'] ?? ''), true);

if ($customerName === '' || mb_strlen($customerName, 'UTF-8') > 100) breadFail('Vui lòng nhập tên khách hàng hợp lệ.');
if (!preg_match('/^[0-9+().\-\s]{8,30}$/D', $customerPhone)
    || strlen(preg_replace('/\D/', '', $customerPhone)) < 8) {
    breadFail('Vui lòng nhập số điện thoại hợp lệ để 24boulangerie xác nhận đơn.');
}
if (!in_array($fulfillment, ['pickup', 'delivery'], true)) breadFail('Vui lòng chọn cách nhận bánh.');
if ($fulfillment === 'delivery' && ($deliveryAddress === '' || mb_strlen($deliveryAddress, 'UTF-8') > 500)) {
    breadFail('Vui lòng nhập địa chỉ giao hàng.');
}
if ($fulfillment === 'pickup') $deliveryAddress = '';
if (mb_strlen($customerNote, 'UTF-8') > 500) breadFail('Lời nhắn quá dài.');
if (!is_array($items) || count($items) < 1 || count($items) > 30) breadFail('Giỏ bánh trống hoặc có quá nhiều món.');

$ids = [];
foreach ($items as $item) {
    if (!is_array($item)) breadFail('Thông tin giỏ bánh không hợp lệ.');
    $id = filter_var($item['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $quantity = filter_var($item['qty'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if ($id === false || $quantity === false) breadFail('Món hoặc số lượng trong giỏ không hợp lệ.');
    $ids[(int)$id] = (int)$quantity;
}

$dateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $pickupDate);
if (!$dateObject || $dateObject->format('Y-m-d') !== $pickupDate) breadFail('Ngày nhận/giao bánh không hợp lệ.');
$now = new DateTimeImmutable('now');
$closing = DateTimeImmutable::createFromFormat('!H:i', $closeTime);
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D', $pickupTime)) breadFail('Vui lòng chọn khung giờ hợp lệ.');

$openMinutes = ((int)substr($openTime, 0, 2) * 60) + (int)substr($openTime, 3, 2);
$closeMinutes = ((int)substr($closeTime, 0, 2) * 60) + (int)substr($closeTime, 3, 2);
$selectedMinutes = ((int)substr($pickupTime, 0, 2) * 60) + (int)substr($pickupTime, 3, 2);
if ($selectedMinutes < $openMinutes || $selectedMinutes >= $closeMinutes || (($selectedMinutes - $openMinutes) % 30) !== 0) {
    breadFail('Giờ nhận/giao nằm ngoài khung giờ hoạt động.');
}

$db = null;
try {
    $settings = posDatabaseSettings();
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 4);
    $db->real_connect($settings['host'], $settings['user'], $settings['password'], $settings['name'], $settings['port']);
    $db->set_charset('utf8mb4');

    $orderItems = [];
    $total = 0;
    $minimumLeadDays = 1;
    $productStatement = $db->prepare("SELECT p.id,p.name,p.price,COALESCE(pp.is_enabled,1) AS preorder_enabled,COALESCE(dp.is_enabled,0) AS daily_enabled FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN bread_preorder_products pp ON pp.product_id=p.id LEFT JOIN bread_daily_products dp ON dp.product_id=p.id WHERE p.id=? AND p.status=1 AND LOWER(TRIM(c.name))='bakery' AND (COALESCE(dp.is_enabled,0)=1 OR COALESCE(pp.is_enabled,1)=1) LIMIT 1");
    foreach ($ids as $id => $quantity) {
        $productStatement->bind_param('i', $id);
        $productStatement->execute();
        $product = $productStatement->get_result()->fetch_assoc();
        if (!$product) breadFail('Một món trong giỏ vừa bị ẩn hoặc ngừng bán. Vui lòng tải lại menu.');
        $minimumLeadDays = max($minimumLeadDays, (int)$product['daily_enabled'] === 1 ? 1 : 3);
        $price = (int)$product['price'];
        $lineTotal = $price * $quantity;
        $total += $lineTotal;
        if ($total > 100000000) breadFail('Tổng đơn hàng vượt giới hạn. Vui lòng chia nhỏ đơn.');
        $orderItems[] = ['id' => $id, 'name' => $product['name'], 'qty' => $quantity, 'price' => $price, 'total' => $lineTotal];
    }

    $minimumDate = $now->modify('+' . $minimumLeadDays . ' days')->setTime(0, 0);
    if ($closing && $now->format('H:i') >= $closing->format('H:i')) {
        $minimumDate = $now->modify('+' . ($minimumLeadDays + 1) . ' days')->setTime(0, 0);
    }
    if ($dateObject < $minimumDate || $dateObject > $now->modify('+30 days')->setTime(23, 59)) {
        breadFail($minimumLeadDays >= 3
            ? 'Đơn Pre-Order cần đặt trước ít nhất 3 ngày, tối đa 30 ngày.'
            : 'Sản phẩm hằng ngày cần đặt trước ít nhất 1 ngày, tối đa 30 ngày.');
    }

    $db->begin_transaction();
    $insertOrder = $db->prepare("INSERT INTO bread_preorders (customer_name,customer_phone,fulfillment_method,delivery_address,pickup_date,pickup_time,customer_note,total_amount) VALUES (?,?,?,?,?,?,?,?)");
    $insertOrder->bind_param('sssssssd', $customerName, $customerPhone, $fulfillment, $deliveryAddress, $pickupDate, $pickupTime, $customerNote, $total);
    $insertOrder->execute();
    $orderId = (int)$db->insert_id;

    $insertItem = $db->prepare("INSERT INTO bread_preorder_items (preorder_id,product_id,product_name,quantity,unit_price,line_total) VALUES (?,?,?,?,?,?)");
    foreach ($orderItems as $orderItem) {
        $productId = $orderItem['id'];
        $productName = $orderItem['name'];
        $quantity = $orderItem['qty'];
        $price = $orderItem['price'];
        $lineTotal = $orderItem['total'];
        $insertItem->bind_param('iisidd', $orderId, $productId, $productName, $quantity, $price, $lineTotal);
        $insertItem->execute();
    }
    $db->commit();
    $db->close();
    header('Location: banh-mi.php?lang=' . $language . '&dat-hang-thanh-cong=' . $orderId, true, 303);
    exit;
} catch (Throwable $exception) {
    if ($db instanceof mysqli) {
        try { $db->rollback(); } catch (Throwable $ignored) {}
        try { $db->close(); } catch (Throwable $ignored) {}
    }
    error_log('Bread preorder submission failed: ' . $exception->getMessage());
    breadFail('Chưa gửi được đơn do hệ thống đang bận. Bạn vui lòng thử lại hoặc gọi 24boulangerie.');
}
