<?php $bakeryPage = basename((string)($_SERVER['PHP_SELF'] ?? '')); ?>
<link rel="icon" type="image/svg+xml" href="bakery_favicon.svg">
<link rel="stylesheet" href="assets/bakery.css">
<nav class="bk-nav">
  <a class="bk-brand" href="bakery.php">🥐 Bếp bánh</a>
  <div class="bk-links">
    <a class="<?= $bakeryPage==='bakery.php'?'active':'' ?>" href="bakery.php">Tổng quan</a>
    <a class="<?= $bakeryPage==='bakery_investments.php'?'active':'' ?>" href="bakery_investments.php">Đầu tư phần thô</a>
    <a class="<?= $bakeryPage==='bakery_equipment.php'?'active':'' ?>" href="bakery_equipment.php">Thiết bị</a>
    <a class="<?= $bakeryPage==='bakery_operating_expenses.php'?'active':'' ?>" href="bakery_operating_expenses.php">Chi phí vận hành</a>
    <a class="<?= $bakeryPage==='bakery_material_purchases.php'?'active':'' ?>" href="bakery_material_purchases.php">Mua nguyên liệu</a>
    <a class="<?= $bakeryPage==='bakery_inventory.php'?'active':'' ?>" href="bakery_inventory.php">Tồn kho</a>
    <a class="<?= $bakeryPage==='bakery_materials.php'?'active':'' ?>" href="bakery_materials.php">Danh mục</a>
    <a class="<?= $bakeryPage==='bakery_revenue.php'?'active':'' ?>" href="bakery_revenue.php">Doanh thu</a>
    <a class="<?= $bakeryPage==='bakery_reports.php'?'active':'' ?>" href="bakery_reports.php">Báo cáo</a>
  </div>
  <div class="bk-user"><?= bakeryLanguageSwitcher() ?><span><?= bakeryH($bakeryAccount['username']) ?></span><a href="bakery_account.php">Tài khoản</a><a class="logout" href="bakery_logout.php">Đăng xuất</a></div>
</nav>
<?= bakeryTranslationScript() ?>
