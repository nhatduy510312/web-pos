<?php
require_once __DIR__ . '/bakery_auth.php';
require_once __DIR__ . '/site/content-store.php';
require_once __DIR__ . '/site/media.php';
$db = bakeryDbOrFail();
$bakeryAccount = requireBakerySession($db);
$notice = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require($_POST['csrf_token'] ?? '');
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $isEnabled = isset($_POST['preorder_visible']) ? 1 : 0;
    $isDailyEnabled = isset($_POST['daily_visible']) ? 1 : 0;
    if ($isDailyEnabled) $isEnabled = 0;
    $imageId = trim((string)($_POST['image_id'] ?? ''));
    $removeImage = isset($_POST['remove_preorder_image']);
    $upload = $_FILES['product_image'] ?? ['error'=>UPLOAD_ERR_NO_FILE];
    $uploaded = null;
    if ($productId === false || ($imageId !== '' && !preg_match('/^[a-f0-9]{32}$/D', $imageId))) {
        $error = 'Sản phẩm hoặc hình ảnh không hợp lệ.';
    } else {
        $check = $db->prepare("SELECT p.id FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND LOWER(TRIM(c.name))='bakery' LIMIT 1");
        $check->bind_param('i', $productId);
        $check->execute();
        if (!$check->get_result()->fetch_assoc()) {
            $error = 'Chỉ có thể cập nhật sản phẩm thuộc danh mục Bakery.';
        } else {
            $current = $db->prepare('SELECT image_id FROM bread_preorder_product_images WHERE product_id=? LIMIT 1');
            $current->bind_param('i', $productId);
            $current->execute();
            $currentImage = (string)($current->get_result()->fetch_assoc()['image_id'] ?? '');
            if ($imageId !== $currentImage) {
                $error = 'Ảnh hiện tại không khớp. Hãy tải lại trang rồi thử lại.';
            } else try {
                if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $uploaded = ghe_media_upload($upload);
                    $imageId = $uploaded['id'];
                } elseif ($removeImage) {
                    $imageId = '';
                }
                $db->begin_transaction();
                $save = $db->prepare('INSERT INTO bread_preorder_products (product_id,is_enabled) VALUES (?,?) ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled)');
                $save->bind_param('ii', $productId, $isEnabled);
                $save->execute();
                if ($isDailyEnabled) {
                    $saveDaily = $db->prepare('INSERT INTO bread_daily_products (product_id,is_enabled) VALUES (?,1) ON DUPLICATE KEY UPDATE is_enabled=1');
                    $saveDaily->bind_param('i', $productId);
                    $saveDaily->execute();
                } else {
                    $removeDaily = $db->prepare('DELETE FROM bread_daily_products WHERE product_id=?');
                    $removeDaily->bind_param('i', $productId);
                    $removeDaily->execute();
                }
                if ($imageId === '') {
                    $removeImage = $db->prepare('DELETE FROM bread_preorder_product_images WHERE product_id=?');
                    $removeImage->bind_param('i', $productId);
                    $removeImage->execute();
                } else {
                    $saveImage = $db->prepare('INSERT INTO bread_preorder_product_images (product_id,image_id) VALUES (?,?) ON DUPLICATE KEY UPDATE image_id=VALUES(image_id)');
                    $saveImage->bind_param('is', $productId, $imageId);
                    $saveImage->execute();
                }
                $db->commit();
                header('Location: bakery_preorder_products.php?saved=1', true, 303);
                exit;
            } catch (Throwable $exception) {
                try { $db->rollback(); } catch (Throwable $ignored) {}
                if ($uploaded) foreach (['full','thumb'] as $size) {
                    $file = ghe_media_file($uploaded['id'], $size);
                    if (is_file($file)) unlink($file);
                }
                error_log('Pre-order product settings update failed: ' . $exception->getMessage());
                $error = $exception instanceof RuntimeException ? $exception->getMessage() : 'Chưa lưu được cài đặt sản phẩm. Hãy thử lại.';
            }
        }
    }
}
if (($_GET['saved'] ?? '') === '1') $notice = 'Đã cập nhật danh sách sản phẩm hiển thị trên trang khách.';

$result = $db->query("SELECT p.id,p.name,p.price,p.status,COALESCE(pp.is_enabled,1) AS preorder_enabled,COALESCE(dp.is_enabled,0) AS daily_enabled,COALESCE(pi.image_id,'') AS preorder_image_id FROM products p JOIN categories c ON c.id=p.category_id LEFT JOIN bread_preorder_products pp ON pp.product_id=p.id LEFT JOIN bread_daily_products dp ON dp.product_id=p.id LEFT JOIN bread_preorder_product_images pi ON pi.product_id=p.id WHERE LOWER(TRIM(c.name))='bakery' ORDER BY p.status DESC,p.sort_order ASC,p.name ASC");
$products = $result->fetch_all(MYSQLI_ASSOC);
$shownCount = count(array_filter($products, static fn(array $product): bool => (int)$product['status'] === 1 && ((int)$product['preorder_enabled'] === 1 || (int)$product['daily_enabled'] === 1)));
?>
<!doctype html>
<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="assets/bakery.css"><link rel="stylesheet" href="assets/bakery-preorders.css"><link rel="stylesheet" href="assets/24boulangerie-brand.css?v=5"><title>Sản phẩm Pre-Order · 24boulangerie</title></head>
<body><?php require __DIR__ . '/bakery_nav.php'; ?>
<main class="bk-shell">
  <header class="bk-header"><div><h1>Sản phẩm Pre-Order</h1><p>Danh sách đồng bộ với sản phẩm Bakery trên POS. Daily đặt trước 1 ngày; Pre-Order đặt trước 3 ngày.</p></div><a class="bk-btn bk-btn-secondary" href="banh-mi.php" target="_blank" rel="noopener">Mở trang khách ↗</a></header>
  <?php if ($notice): ?><div class="bk-alert bk-alert-success"><?= bakeryH($notice) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="bk-alert bk-alert-error"><?= bakeryH($error) ?></div><?php endif; ?>
  <div class="preorder-product-summary"><strong><?= $shownCount ?> sản phẩm đang hiển thị</strong><span>Sản phẩm đang tắt trong POS sẽ không xuất hiện. Bật Daily sẽ chuyển sản phẩm khỏi nhóm Pre-Order.</span></div>
  <?php if (!$products): ?><section class="bk-card"><div class="bk-empty">Chưa có sản phẩm trong danh mục Bakery của POS.</div></section><?php else: ?>
  <div class="bk-table-wrap"><table class="bk-table preorder-products-table"><thead><tr><th>Sản phẩm POS</th><th class="money">Giá hiện tại</th><th>POS</th><th>Ảnh sản phẩm</th><th>Hiển thị Pre-Order</th><th>Hiển thị Daily</th></tr></thead><tbody>
  <?php foreach ($products as $product): ?>
    <?php $imageId = (string)$product['preorder_image_id']; ?>
    <tr><td><strong><?= bakeryH($product['name']) ?></strong><div class="bk-muted">Mã #<?= (int)$product['id'] ?></div></td><td class="money"><?= bakeryMoney($product['price']) ?></td><td><span class="bk-badge <?= (int)$product['status']===1?'green':'' ?>"><?= (int)$product['status']===1?'Đang bán':'Đang tắt' ?></span></td><td><?php if ($imageId !== ''): ?><img class="preorder-image-preview" src="ghe-image.php?id=<?= bakeryH($imageId) ?>&amp;size=thumb" alt="Ảnh sản phẩm hiện tại" loading="lazy"><?php endif; ?><label class="bk-muted" for="image-<?= (int)$product['id'] ?>">Ảnh từ máy tính (JPEG, PNG, WebP; tối đa 8 MB)</label><input class="preorder-image-upload" id="image-<?= (int)$product['id'] ?>" type="file" name="product_image" form="preorder-config-<?= (int)$product['id'] ?>" accept="image/jpeg,image/png,image/webp"><input type="hidden" name="image_id" value="<?= bakeryH($imageId) ?>" form="preorder-config-<?= (int)$product['id'] ?>"><?php if ($imageId !== ''): ?><label class="preorder-image-remove"><input type="checkbox" name="remove_preorder_image" value="1" form="preorder-config-<?= (int)$product['id'] ?>"> Gỡ ảnh</label><?php endif; ?></td><td><form id="preorder-config-<?= (int)$product['id'] ?>" method="post" enctype="multipart/form-data" class="preorder-product-toggle"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>"><label class="preorder-switch"><input type="checkbox" name="preorder_visible" value="1" <?= (int)$product['preorder_enabled']===1 && !(int)$product['daily_enabled']?'checked':'' ?> aria-label="Hiển thị <?= bakeryH($product['name']) ?> trong nhóm Pre-Order"><span aria-hidden="true"></span></label></form><span class="preorder-visibility-label"><?= (int)$product['preorder_enabled']===1 && !(int)$product['daily_enabled']?'Hiển thị':'Ẩn' ?></span></td><td><label class="preorder-switch"><input type="checkbox" name="daily_visible" value="1" form="preorder-config-<?= (int)$product['id'] ?>" <?= (int)$product['daily_enabled']===1?'checked':'' ?> aria-label="Hiển thị <?= bakeryH($product['name']) ?> trong nhóm Daily"><span aria-hidden="true"></span></label><span class="preorder-visibility-label"><?= (int)$product['daily_enabled']===1?'Hiển thị':'Ẩn' ?></span><button class="bk-btn bk-btn-primary bk-btn-small" type="submit" form="preorder-config-<?= (int)$product['id'] ?>">Lưu</button></td></tr>
  <?php endforeach; ?>
  </tbody></table></div>
  <?php endif; ?>
</main></body></html>
