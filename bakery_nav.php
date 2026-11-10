<?php $bakeryPage = basename((string)($_SERVER['PHP_SELF'] ?? '')); ?>
<link rel="icon" type="image/svg+xml" href="bakery_favicon.svg">
<link rel="stylesheet" href="assets/bakery.css">
<link rel="stylesheet" href="assets/24boulangerie-brand.css">
<?php
$bakeryCostPages = ['bakery_investments.php', 'bakery_equipment.php', 'bakery_operating_expenses.php', 'bakery_material_purchases.php'];
$bakeryStockPages = ['bakery_inventory.php', 'bakery_materials.php'];
$bakeryBusinessPages = ['bakery_revenue.php', 'bakery_reports.php'];
$bakeryPreOrderPages = ['bakery_preorders.php', 'bakery_preorder_products.php'];
?>
<nav class="bk-nav" aria-label="Bakery" data-bakery-navigation>
  <a class="bk-brand" href="bakery.php" aria-label="Bếp bánh 24boulangerie"><span class="bk-brand-logo-frame"><img src="assets/24boulangerie-logo.png" alt="24 Boulangerie"></span></a>
  <button class="bk-nav-toggle" type="button" aria-expanded="false" aria-controls="bakeryNavigationMenu" aria-label="Mở menu" data-bakery-nav-toggle>☰</button>
  <div class="bk-nav-menu" id="bakeryNavigationMenu">
   <div class="bk-links">
    <a class="<?= $bakeryPage==='bakery.php'?'active':'' ?>" href="bakery.php">Tổng quan</a>
    <details class="bk-group <?= in_array($bakeryPage, $bakeryCostPages, true)?'active':'' ?>">
      <summary>Chi phí</summary>
      <div class="bk-dropdown">
        <a class="<?= $bakeryPage==='bakery_investments.php'?'active':'' ?>" href="bakery_investments.php">Đầu tư phần thô</a>
        <a class="<?= $bakeryPage==='bakery_equipment.php'?'active':'' ?>" href="bakery_equipment.php">Thiết bị</a>
        <a class="<?= $bakeryPage==='bakery_operating_expenses.php'?'active':'' ?>" href="bakery_operating_expenses.php">Chi phí vận hành</a>
        <a class="<?= $bakeryPage==='bakery_material_purchases.php'?'active':'' ?>" href="bakery_material_purchases.php">Mua nguyên liệu</a>
      </div>
    </details>
    <details class="bk-group <?= in_array($bakeryPage, $bakeryStockPages, true)?'active':'' ?>">
      <summary>Kho</summary>
      <div class="bk-dropdown">
        <a class="<?= $bakeryPage==='bakery_inventory.php'?'active':'' ?>" href="bakery_inventory.php">Tồn kho</a>
        <a class="<?= $bakeryPage==='bakery_materials.php'?'active':'' ?>" href="bakery_materials.php">Danh mục</a>
      </div>
    </details>
    <details class="bk-group <?= in_array($bakeryPage, $bakeryBusinessPages, true)?'active':'' ?>">
      <summary>Kinh doanh</summary>
      <div class="bk-dropdown">
        <a class="<?= $bakeryPage==='bakery_revenue.php'?'active':'' ?>" href="bakery_revenue.php">Doanh thu</a>
        <a class="<?= $bakeryPage==='bakery_reports.php'?'active':'' ?>" href="bakery_reports.php">Báo cáo</a>
      </div>
    </details>
    <details class="bk-group <?= in_array($bakeryPage, $bakeryPreOrderPages, true)?'active':'' ?>">
      <summary>Pre-Order</summary>
      <div class="bk-dropdown">
        <a class="<?= $bakeryPage==='bakery_preorders.php'?'active':'' ?>" href="bakery_preorders.php">Đơn đặt bánh online</a>
        <a class="<?= $bakeryPage==='bakery_preorder_products.php'?'active':'' ?>" href="bakery_preorder_products.php">Sản phẩm Pre-order</a>
      </div>
    </details>
   </div>
   <div class="bk-user"><?= bakeryLanguageSwitcher() ?><span><?= bakeryH($bakeryAccount['username']) ?></span><a href="bakery_account.php">Tài khoản</a><a class="logout" href="bakery_logout.php">Đăng xuất</a></div>
  </div>
</nav>
<?= bakeryTranslationScript() ?>
<script src="assets/bakery-nav.js?v=1" defer></script>
