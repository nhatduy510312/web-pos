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
if (isset($_GET['lang']) && in_array($_GET['lang'], ['vi', 'en'], true)) {
    $_SESSION['bread_preorder_language'] = $_GET['lang'];
}
$language = ($_SESSION['bread_preorder_language'] ?? 'vi') === 'en' ? 'en' : 'vi';
$isEnglish = $language === 'en';
$t = fn(string $vi, string $en): string => $isEnglish ? $en : $vi;
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/site/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');

$breadItems = [];
$preorderItems = [];
$dailyItems = [];
if ($catalogAvailable) {
    $preorderDb = null;
    try {
        $preorderDb = ghe_database();
        $result = $preorderDb->query("SELECT p.id,p.name,p.price,p.status,p.category_id,COALESCE(pp.is_enabled,1) AS preorder_enabled,COALESCE(dp.is_enabled,0) AS daily_enabled,COALESCE(pi.image_id,'') AS preorder_image_id FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN bread_preorder_products pp ON pp.product_id=p.id LEFT JOIN bread_daily_products dp ON dp.product_id=p.id LEFT JOIN bread_preorder_product_images pi ON pi.product_id=p.id WHERE p.status=1 AND LOWER(TRIM(c.name))='bakery' AND (COALESCE(pp.is_enabled,1)=1 OR COALESCE(dp.is_enabled,0)=1) ORDER BY p.sort_order ASC,p.name ASC");
        while ($row = $result->fetch_assoc()) {
            $productId = (int)$row['id'];
            $meta = $content['products'][(string)$productId] ?? [];
            $image = (string)$row['preorder_image_id'];
            if ($image === '') $image = ($meta['visible'] ?? true) ? (string)($meta['image'] ?? '') : '';
            if ($image === '' && ($meta['visible'] ?? true) && !($meta['image_manual'] ?? false)) {
                $image = ghe_collection_product($row['name'], $content)['image'];
            }
            $item = [
                'id' => $productId,
                'name' => (string)$row['name'],
                'price' => (int)$row['price'],
                'detail' => (string)($meta['description'] ?? ''),
                'image' => $image,
                'lead_days' => (int)$row['daily_enabled'] === 1 ? 1 : 3,
            ];
            $breadItems[] = $item;
            if ((int)$row['daily_enabled'] === 1) $dailyItems[] = $item;
            else $preorderItems[] = $item;
        }
    } catch (Throwable $exception) {
        error_log('24boulangerie preorder catalog unavailable: ' . $exception->getMessage());
        $catalogAvailable = false;
        $breadItems = [];
        $preorderItems = [];
        $dailyItems = [];
    } finally {
        if ($preorderDb instanceof mysqli) $preorderDb->close();
    }
}
$now = new DateTimeImmutable('now');
$closing = DateTimeImmutable::createFromFormat('!H:i', (string)($site['closes'] ?? '18:00'));
$minimumDailyPickup = $now->modify('+1 day');
$minimumPreorderPickup = $now->modify('+3 days');
if ($closing && $now->format('H:i') >= $closing->format('H:i')) {
    $minimumDailyPickup = $now->modify('+2 days');
    $minimumPreorderPickup = $now->modify('+4 days');
}
$maxPickup = $now->modify('+30 days');
$openingTime = preg_match('/^\d{2}:\d{2}$/D', (string)($site['opens'] ?? '07:00')) ? $site['opens'] : '07:00';
$closingTime = preg_match('/^\d{2}:\d{2}$/D', (string)($site['closes'] ?? '18:00')) ? $site['closes'] : '18:00';
$openMinutes = ((int)substr($openingTime, 0, 2) * 60) + (int)substr($openingTime, 3, 2);
$closeMinutes = ((int)substr($closingTime, 0, 2) * 60) + (int)substr($closingTime, 3, 2);
$pickupSlots = [];
for ($minute = $openMinutes; $minute < $closeMinutes; $minute += 30) {
    $pickupSlots[] = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
}
$orderSuccess = preg_match('/^\d+$/D', (string)($_GET['dat-hang-thanh-cong'] ?? '')) ? (string)$_GET['dat-hang-thanh-cong'] : '';
$orderError = is_string($_SESSION['bread_preorder_error'] ?? null) ? $_SESSION['bread_preorder_error'] : '';
unset($_SESSION['bread_preorder_error']);
$safeItems = array_map(static function (array $item): array {
    return [
        'id' => (int)$item['id'],
        'name' => (string)$item['name'],
        'price' => (int)$item['price'],
        'detail' => (string)$item['detail'],
        'image' => (string)$item['image'],
        'lead_days' => (int)$item['lead_days'],
    ];
}, $breadItems);
$productSections = [
    ['key'=>'preorder','items'=>$preorderItems],
    ['key'=>'daily','items'=>$dailyItems],
];
?>
<!doctype html>
<html lang="<?= $language ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f4eddf">
<meta name="description" content="<?= ghe_h($t('Đặt trước bánh tại Đà Lạt. Chọn bánh, ngày giờ và hình thức nhận.','Pre-order bakery favorites in Da Lat. Choose your products, pickup or delivery time, and how to receive your order.')) ?>">
<meta name="robots" content="index,follow">
<link rel="canonical" href="<?= ghe_h(ghe_url('banh-mi.php')) ?>">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<link rel="stylesheet" href="assets/bread-preorder.css?v=3">
<link rel="stylesheet" href="assets/24boulangerie-brand.css?v=5">
<title><?= ghe_h($t('Pre-Order — Đà Lạt','Pre-Order — Da Lat')) ?></title>
</head>
<body data-order-placed="<?= $orderSuccess !== '' ? '1' : '0' ?>">
<header class="bread-header">
  <a class="bread-brand" href="banh-mi.php?lang=<?= $language ?>" aria-label="24 Boulangerie"><span class="bread-logo-frame"><img src="assets/24boulangerie-logo.png" alt="24 Boulangerie"></span></a>
  <nav class="bread-language-switch" aria-label="<?= ghe_h($t('Ngôn ngữ','Language')) ?>"><a href="?lang=vi" lang="vi"<?= !$isEnglish ? ' aria-current="true"' : '' ?>>VI</a><a href="?lang=en" lang="en"<?= $isEnglish ? ' aria-current="true"' : '' ?>>EN</a></nav>
</header>

<main class="bread-page">
  <?php if ($orderSuccess !== ''): ?>
  <div class="notice success" role="status"><strong><?= ghe_h($t('Đã nhận đơn #','Your order #')) ?><?= ghe_h($orderSuccess) ?>.</strong> <?= ghe_h($t('Chúng tôi sẽ liên hệ xác nhận qua số điện thoại bạn cung cấp.','We will contact you to confirm using the phone number you provided.')) ?></div>
  <?php elseif ($orderError !== ''): ?>
  <div class="notice error" role="alert"><?= ghe_h($orderError) ?></div>
  <?php endif; ?>

  <?php if (!$catalogAvailable): ?>
  <div class="notice error" role="alert"><?= ghe_h($t('Chưa tải được danh sách bánh. Bạn thử tải lại sau ít phút hoặc gọi','The product list is unavailable. Please try again in a few minutes or call')) ?> <?= ghe_h($site['phone']) ?>.</div>
  <?php elseif (!$breadItems): ?>
  <div class="notice empty" role="status"><?= ghe_h($t('Chưa có sản phẩm đang hiển thị. Bạn có thể gọi','No products are available right now. Call')) ?> <a href="tel:<?= ghe_h($site['telephone']) ?>"><?= ghe_h($site['phone']) ?></a> <?= ghe_h($t('để hỏi thêm.','for assistance.')) ?></div>
  <?php else: ?>
  <div class="shop-layout">
    <section class="products-panel" aria-labelledby="<?= $preorderItems ? 'bread-menu-title' : 'bread-daily-title' ?>">
      <?php foreach ($productSections as $productSection): if (!$productSection['items']) continue; $isDailySection = $productSection['key'] === 'daily'; ?>
      <div class="section-heading<?= $isDailySection ? ' daily-section-heading' : '' ?>"><div><h2 id="<?= $isDailySection ? 'bread-daily-title' : 'bread-menu-title' ?>"><?= ghe_h($isDailySection ? $t('Sản phẩm hằng ngày','Daily Products') : $t('Sản phẩm Pre-Order','Pre-Order Products')) ?></h2><?php if (!$isDailySection): ?><p class="preorder-tagline"><?= ghe_h($t('Chào bạn! Các sản phẩm này 24boulangerie nhận đặt trước và giao hàng sau 3 ngày. Hãy đặt trước món yêu thích để 24boulangerie dành riêng cho bạn nhé!','Hello! 24boulangerie accepts pre-orders for these products, with delivery after 3 days. Pre-order your favorites and 24boulangerie will set them aside just for you!')) ?></p><?php else: ?><p class="preorder-tagline"><?= ghe_h($t('Sản phẩm hằng ngày cần đặt trước ít nhất 1 ngày.','Daily products must be ordered at least 1 day in advance.')) ?></p><?php endif; ?></div><span><?= count($productSection['items']) ?> <?= ghe_h($t('món','items')) ?></span></div>
      <div class="bread-grid">
        <?php foreach ($productSection['items'] as $item): ?>
        <article class="bread-card" data-product-id="<?= (int)$item['id'] ?>" data-lead-days="<?= (int)$item['lead_days'] ?>">
          <div class="bread-photo">
            <?php if ($item['image'] !== ''): ?>
              <img src="<?= ghe_h(ghe_link('ghe-image.php?id=' . rawurlencode($item['image']))) ?>" alt="<?= ghe_h($item['name']) ?>" loading="lazy">
            <?php else: ?>
              <div class="bread-placeholder" aria-hidden="true"><span>🥖</span></div>
            <?php endif; ?>
            <span class="fresh-tag"><?= ghe_h($t('Làm mới mỗi ngày','Freshly made')) ?></span>
          </div>
          <div class="bread-card-body">
            <div class="product-heading"><h3><?= ghe_h($item['name']) ?></h3><strong><?= number_format((int)$item['price'], 0, $isEnglish ? '.' : ',', $isEnglish ? ',' : '.') ?><?= $isEnglish ? ' ₫' : 'đ' ?></strong></div>
            <?php if (trim($item['detail']) !== ''): ?><p><?= ghe_h($item['detail']) ?></p><?php else: ?><p><?= ghe_h($t('Bánh được chuẩn bị mới theo đơn đặt trước.','Freshly prepared for your pre-order.')) ?></p><?php endif; ?>
            <div class="quantity-control" data-quantity-for="<?= (int)$item['id'] ?>">
              <button type="button" data-quantity-change="-1" aria-label="<?= ghe_h($t('Giảm một','Decrease quantity')) ?>">−</button><span data-quantity-value>0</span><button type="button" data-quantity-change="1" aria-label="<?= ghe_h($t('Tăng một','Increase quantity')) ?>">＋</button>
            </div>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
      <?php endforeach; ?>
    </section>

    <aside class="order-panel" id="checkout">
      <div class="order-panel-heading"><div><p class="eyebrow"><?= ghe_h($t('Đặt trước','Pre-Order')) ?></p><h2><?= ghe_h($t('Giỏ bánh','Your order')) ?></h2></div><span class="cart-count" id="cart-count">0 <?= ghe_h($t('món','items')) ?></span></div>
      <div id="cart-lines" class="cart-lines"><p class="cart-empty"><?= ghe_h($t('Giỏ hàng đang trống. Chọn bánh bạn thích để bắt đầu nhé.','Your cart is empty. Choose a product to get started.')) ?></p></div>
      <div class="cart-total"><span><?= ghe_h($t('Tạm tính','Subtotal')) ?></span><strong id="cart-total">0<?= $isEnglish ? ' ₫' : 'đ' ?></strong></div>
      <form id="preorder-form" action="banh-mi-dat-hang.php" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="items_json" id="items-json" value="[]">
        <input type="hidden" name="language" value="<?= $language ?>">
        <div class="form-section-title"><span>01</span><strong><?= ghe_h($t('Thông tin khách hàng','Your Details')) ?></strong></div>
        <label for="customer-name"><?= ghe_h($t('Tên của bạn','Your name')) ?></label>
        <input id="customer-name" name="customer_name" type="text" maxlength="100" autocomplete="name" required>
        <label for="customer-phone"><?= ghe_h($t('Số điện thoại','Phone number')) ?></label>
        <input id="customer-phone" name="customer_phone" type="tel" maxlength="30" autocomplete="tel" inputmode="tel" pattern="[0-9+(). -]{8,30}" required>
        <fieldset class="fulfillment-options"><legend><?= ghe_h($t('Hình thức nhận bánh','How would you like to receive your order?')) ?></legend>
          <label class="fulfillment-option"><input type="radio" name="fulfillment_method" value="pickup" checked><span><strong><?= ghe_h($t('Lấy tại cửa hàng','Pick up at the bakery')) ?></strong><small><?= ghe_h($site['address']) ?></small></span></label>
          <label class="fulfillment-option"><input type="radio" name="fulfillment_method" value="delivery"><span><strong><?= ghe_h($t('Giao hàng','Delivery')) ?></strong><small><?= ghe_h($t('Nhập địa chỉ để chúng tôi liên hệ xác nhận.','Enter your address and we will contact you to confirm.')) ?></small></span></label>
        </fieldset>
        <div id="delivery-address-wrap" hidden><label for="delivery-address"><?= ghe_h($t('Địa chỉ giao hàng','Delivery address')) ?></label><textarea id="delivery-address" name="delivery_address" maxlength="500" rows="2" autocomplete="street-address" placeholder="<?= ghe_h($t('Số nhà, đường, phường/xã, thành phố','Street, ward, district, city')) ?>"></textarea></div>
        <div class="form-grid">
          <div><label for="pickup-date"><?= ghe_h($t('Ngày mong muốn','Preferred date')) ?></label><input id="pickup-date" name="pickup_date" type="date" min="<?= $minimumDailyPickup->format('Y-m-d') ?>" data-min-daily="<?= $minimumDailyPickup->format('Y-m-d') ?>" data-min-preorder="<?= $minimumPreorderPickup->format('Y-m-d') ?>" max="<?= $maxPickup->format('Y-m-d') ?>" required></div>
          <div><label for="pickup-time"><?= ghe_h($t('Giờ mong muốn','Preferred time')) ?></label><select id="pickup-time" name="pickup_time" required><option value=""><?= ghe_h($t('Chọn giờ','Select a time')) ?></option><?php foreach ($pickupSlots as $slot): ?><option value="<?= ghe_h($slot) ?>"><?= ghe_h($slot) ?></option><?php endforeach; ?></select></div>
        </div>
        <label for="customer-note"><?= ghe_h($t('Lời nhắn cho bếp','Note for the bakery')) ?> <span>(<?= ghe_h($t('không bắt buộc','optional')) ?>)</span></label>
        <textarea id="customer-note" name="customer_note" maxlength="500" rows="3" placeholder="<?= ghe_h($t('Ghi chú thêm cho đơn hàng','Add a note to your order')) ?>"></textarea>
        <div class="hp-field" aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <button class="submit-order" id="submit-order" type="submit" disabled><?= ghe_h($t('Gửi đơn đặt bánh','Place pre-order')) ?> <span aria-hidden="true">→</span></button>
        <p class="terms-note"><?= ghe_h($t('Chúng tôi sẽ liên hệ xác nhận đơn và thông tin nhận/giao. Thông tin chỉ dùng để phục vụ đơn đặt bánh.','We will contact you to confirm your order and fulfillment details. Your information will only be used to process this order.')) ?></p>
      </form>
    </aside>
  </div>
  <?php endif; ?>

  <section class="visit-strip"><span class="visit-icon" aria-hidden="true">⌖</span><div><strong><?= ghe_h($t('Đà Lạt','Da Lat')) ?></strong><p><?= ghe_h($site['address']) ?></p><a href="<?= ghe_h($site['directions']) ?>" target="_blank" rel="noopener"><?= ghe_h($t('Mở bản đồ','Open map')) ?> ↗</a></div><a class="visit-phone" href="tel:<?= ghe_h($site['telephone']) ?>"><?= ghe_h($site['phone']) ?></a></section>
</main>

<footer class="bread-footer"><span><?= ghe_h($t('Đà Lạt','Da Lat')) ?></span><span>Pre-Order</span></footer>
<script type="application/json" id="bread-products"><?= json_encode($safeItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/bread-preorder.js?v=4" defer></script>
</body>
</html>
