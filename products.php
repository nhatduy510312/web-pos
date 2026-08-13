<?php
include 'auth.php';
include 'config.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once __DIR__ . '/recipe_helpers.php';
requireRole('admin');

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$addError = '';
$updateError = '';

function productRecipeCleanValue($value)
{
    return trim(preg_replace('/[\r\n\t]+/u', ' ', (string)$value));
}

function productRecipePostArray($key)
{
    return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
}

/* ── Import Excel ── */
if (isset($_POST['import_excel'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $spreadsheet = IOFactory::load($_FILES['excel_file']['tmp_name']);
    $rows = $spreadsheet->getActiveSheet()->toArray();
    $imported = 0;
    foreach ($rows as $i => $row) {
        if ($i === 0) continue;
        $category = trim($row[0]); $name = trim($row[1]);
        $price = (int)preg_replace('/[^0-9]/', '', $row[2]);
        if (empty($name)) continue;

        $stmt = $conn->prepare("SELECT id FROM categories WHERE name=?");
        $stmt->bind_param("s", $category); $stmt->execute();
        $cat_row = $stmt->get_result()->fetch_assoc();
        if ($cat_row) { $cid = $cat_row['id']; }
        else {
            $stmt = $conn->prepare("INSERT INTO categories(name) VALUES(?)");
            $stmt->bind_param("s", $category); $stmt->execute();
            $cid = $conn->insert_id;
        }
        $stmt = $conn->prepare("SELECT id FROM products WHERE name=?");
        $stmt->bind_param("s", $name); $stmt->execute();
        if ($stmt->get_result()->num_rows) continue;
        $stmt = $conn->prepare("INSERT INTO products(name,price,category_id) VALUES(?,?,?)");
        $stmt->bind_param("sii", $name, $price, $cid); $stmt->execute();
        $imported++;
    }
    header("Location: products.php?imported=$imported"); exit;
}

/* ── Add ── */
if (isset($_POST['add'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $name  = productRecipeCleanValue($_POST['name'] ?? '');
    $price = (int)preg_replace('/[^0-9]/', '', (string)($_POST['price'] ?? ''));
    $cid   = (int)($_POST['category_id'] ?? 0);
    $originalRecipeData = null;
    $recipeJsonWritten = false;

    try {
        if ($name === '') {
            throw new RuntimeException('Vui lòng nhập tên món.');
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO products(name,price,category_id) VALUES(?,?,?)");
        $stmt->bind_param("sii", $name, $price, $cid);
        $stmt->execute();
        $newProductId = (int)$conn->insert_id;

        if ($isAdmin) {
            $categoryStmt = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
            $categoryStmt->bind_param("i", $cid);
            $categoryStmt->execute();
            $categoryRow = $categoryStmt->get_result()->fetch_assoc();
            if (!$categoryRow) {
                throw new RuntimeException('Danh mục đã chọn không tồn tại.');
            }

            $ingredientNames = productRecipePostArray('new_ingredient_name');
            $commonAmounts = productRecipePostArray('new_amount_common');
            $hotAmounts = productRecipePostArray('new_amount_hot');
            $icedAmounts = productRecipePostArray('new_amount_iced');
            $ingredientUnits = productRecipePostArray('new_ingredient_unit');
            $ingredients = [];

            $ingredientRowCount = min(count($ingredientNames), 100);
            for ($index = 0; $index < $ingredientRowCount; $index++) {
                $ingredientName = productRecipeCleanValue($ingredientNames[$index] ?? '');
                if ($ingredientName === '') {
                    continue;
                }

                $amount = recipeBuildAmountValue(
                    productRecipeCleanValue($commonAmounts[$index] ?? ''),
                    productRecipeCleanValue($hotAmounts[$index] ?? ''),
                    productRecipeCleanValue($icedAmounts[$index] ?? '')
                );
                $unit = productRecipeCleanValue($ingredientUnits[$index] ?? '');

                if (
                    strlen($ingredientName) > 360
                    || strlen($amount) > 500
                    || strlen($unit) > 100
                ) {
                    throw new RuntimeException('Một dòng nguyên liệu có nội dung quá dài.');
                }

                $ingredients[] = [
                    'name' => $ingredientName,
                    'amount' => $amount,
                    'unit' => $unit,
                    'sort_order' => count($ingredients) + 1,
                ];
            }

            $instructions = trim((string)($_POST['new_recipe_instructions'] ?? ''));
            if (!$ingredients && $instructions === '') {
                throw new RuntimeException('Vui lòng nhập nguyên liệu hoặc cách thực hiện cho món mới.');
            }
            if (strlen($instructions) > 30000) {
                throw new RuntimeException('Phần cách thực hiện quá dài.');
            }

            $recipeName = productRecipeCleanValue($_POST['new_recipe_name'] ?? '');
            if ($recipeName === '') {
                $recipeName = $name;
            }
            $recipeType = (string)($_POST['new_recipe_type'] ?? 'drink');
            if (!in_array($recipeType, ['drink', 'food'], true)) {
                $recipeType = 'drink';
            }

            $originalRecipeData = recipeReadDataFile();
            $updatedRecipeData = $originalRecipeData;
            $recipeCode = 'product-' . $newProductId;
            $suffix = 2;
            while (isset($updatedRecipeData['recipes'][$recipeCode])) {
                $recipeCode = 'product-' . $newProductId . '-' . $suffix;
                $suffix++;
            }

            $updatedRecipeData['recipes'][$recipeCode] = [
                'name' => $recipeName,
                'type' => $recipeType,
                'category' => (string)$categoryRow['name'],
                'instructions' => $instructions,
                'source' => [
                    'sheet' => 'ADMIN',
                    'row' => null,
                ],
                'ingredients' => $ingredients,
                'updated_at' => date(DATE_ATOM),
            ];
            $updatedRecipeData['product_map'][] = [
                'product_names' => [$name],
                'recipe_code' => $recipeCode,
                'variant' => '',
            ];
            $updatedRecipeData['updated_at'] = date(DATE_ATOM);

            recipeWriteDataFile($updatedRecipeData);
            $recipeJsonWritten = true;
        }

        $conn->commit();
        header("Location: products.php?added=1");
        exit;
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Unable to rollback new product: ' . $rollbackError->getMessage());
        }

        if ($recipeJsonWritten && $originalRecipeData !== null) {
            try {
                recipeWriteDataFile($originalRecipeData);
            } catch (Throwable $restoreError) {
                error_log('Unable to restore recipe JSON after product rollback: ' . $restoreError->getMessage());
            }
        }

        $addError = $e->getMessage();
    }
}

/* ── Update ── */
if (isset($_POST['update'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $id    = (int)$_POST['id'];
    $name  = productRecipeCleanValue($_POST['name'] ?? '');
    $price = (int)preg_replace('/[^0-9]/', '', (string)($_POST['price'] ?? ''));
    $cid   = (int)$_POST['category_id'];
    $sort  = (int)$_POST['sort_order'];
    $originalRecipeData = null;
    $recipeJsonWritten = false;

    try {
        if ($name === '') {
            throw new RuntimeException('Tên món không được để trống.');
        }

        $conn->begin_transaction();
        $oldProductStmt = $conn->prepare("SELECT name FROM products WHERE id=? LIMIT 1");
        $oldProductStmt->bind_param("i", $id);
        $oldProductStmt->execute();
        $oldProduct = $oldProductStmt->get_result()->fetch_assoc();
        if (!$oldProduct) {
            throw new RuntimeException('Không tìm thấy món cần cập nhật.');
        }

        $stmt = $conn->prepare("UPDATE products SET name=?,price=?,category_id=?,sort_order=? WHERE id=?");
        $stmt->bind_param("siiii", $name, $price, $cid, $sort, $id);
        $stmt->execute();

        if (recipeNormalizeProductName($oldProduct['name']) !== recipeNormalizeProductName($name)) {
            $originalRecipeData = recipeReadDataFile();
            $updatedRecipeData = $originalRecipeData;
            $mapping = recipeFindProductMappingInData($updatedRecipeData, $oldProduct['name']);

            if ($mapping !== null) {
                $mappingIndex = $mapping['index'];
                foreach ($updatedRecipeData['product_map'][$mappingIndex]['product_names'] as &$mappedName) {
                    if (
                        recipeNormalizeProductName($mappedName)
                        === recipeNormalizeProductName($oldProduct['name'])
                    ) {
                        $mappedName = $name;
                    }
                }
                unset($mappedName);

                $updatedRecipeData['updated_at'] = date(DATE_ATOM);
                recipeWriteDataFile($updatedRecipeData);
                $recipeJsonWritten = true;
            }
        }

        $conn->commit();
        header("Location: products.php?updated=1");
        exit;
    } catch (Throwable $e) {
        try {
            $conn->rollback();
        } catch (Throwable $rollbackError) {
            error_log('Unable to rollback product update: ' . $rollbackError->getMessage());
        }

        if ($recipeJsonWritten && $originalRecipeData !== null) {
            try {
                recipeWriteDataFile($originalRecipeData);
            } catch (Throwable $restoreError) {
                error_log('Unable to restore recipe mapping after product rollback: ' . $restoreError->getMessage());
            }
        }

        $updateError = $e->getMessage();
    }
}

/* ── Delete (soft) ── */
if (isset($_POST['delete'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $id   = (int)$_POST['delete'];
    $stmt = $conn->prepare("UPDATE products SET status=0 WHERE id=?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: products.php"); exit;
}

/* ── FIX: load categories once, cache in PHP array — removes N+1 query ── */
$catList = [];
$catResult = $conn->query("SELECT * FROM categories ORDER BY name");
while ($c = $catResult->fetch_assoc()) $catList[$c['id']] = $c['name'];

$products = $conn->query("
    SELECT p.*, c.name category_name
    FROM products p LEFT JOIN categories c ON p.category_id=c.id
    WHERE p.status=1 ORDER BY c.name, p.sort_order, p.name
");

$imported = (int)($_GET['imported'] ?? 0);
$added = isset($_GET['added']);
$updated = isset($_GET['updated']);
$productRecipeData = null;
$recipeLoadError = '';
if ($isAdmin) {
    try {
        $productRecipeData = recipeReadDataFile();
    } catch (Throwable $e) {
        $recipeLoadError = $e->getMessage();
    }
}

$newIngredientRows = [];
if ($addError !== '') {
    $postedNames = productRecipePostArray('new_ingredient_name');
    $postedCommon = productRecipePostArray('new_amount_common');
    $postedHot = productRecipePostArray('new_amount_hot');
    $postedIced = productRecipePostArray('new_amount_iced');
    $postedUnits = productRecipePostArray('new_ingredient_unit');
    $postedRowCount = max(
        count($postedNames),
        count($postedCommon),
        count($postedHot),
        count($postedIced),
        count($postedUnits)
    );

    for ($index = 0; $index < $postedRowCount; $index++) {
        $newIngredientRows[] = [
            'name' => (string)($postedNames[$index] ?? ''),
            'common' => (string)($postedCommon[$index] ?? ''),
            'hot' => (string)($postedHot[$index] ?? ''),
            'iced' => (string)($postedIced[$index] ?? ''),
            'unit' => (string)($postedUnits[$index] ?? ''),
        ];
    }
}
if (!$newIngredientRows) {
    $newIngredientRows[] = ['name' => '', 'common' => '', 'hot' => '', 'iced' => '', 'unit' => ''];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Thực đơn — GHÉ Coffee</title>
<style>
.page-content { width:100%; max-width:1320px; margin:0 auto; }
.prod-toolbar { display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.product-create-card { width:100%; max-width:1000px; }
.new-product-grid {
  display: grid;
  grid-template-columns: minmax(300px, 420px) 150px 220px;
  gap: 10px;
  align-items: end;
}
.edit-select  { font-size:12px; padding:4px 24px 4px 8px; border-radius:6px; border:1px solid var(--s200); background:var(--s50); appearance:none; cursor:pointer; }
.edit-input   { font-size:12px; padding:5px 8px; border-radius:6px; border:1px solid var(--s200); width:100%; background:var(--s50); }
.edit-input:focus, .edit-select:focus { border-color:var(--p); outline:none; background:var(--white); }
.money-input  { text-align:right; font-weight:600; }
.sort-input   { text-align:center; width:50px; }
.row-btns     { display:flex; gap:4px; }
.product-list-width { width:100%; max-width:1080px; }
#productTable { width:1080px; min-width:1080px; table-layout:fixed; }
.new-recipe-box {
  margin-top: 16px;
  padding-top: 16px;
  border-top: 1px solid var(--s200);
}
.new-recipe-grid {
  display: grid;
  grid-template-columns: minmax(300px, 420px) 180px;
  gap: 10px;
}
.new-ingredient-table {
  width: 850px;
  min-width: 850px;
  table-layout: fixed;
  border-collapse: collapse;
}
.new-ingredient-table th {
  padding: 7px 5px;
  color: var(--s500);
  background: var(--s50);
  border-bottom: 1px solid var(--s200);
  font-size: 10px;
  text-align: left;
  text-transform: uppercase;
}
.new-ingredient-table td { padding: 5px; border-bottom: 1px solid var(--s100); }
.new-ingredient-table .form-input { padding: 7px 8px; }
.new-ingredient-table .ingredient-col { width: 330px; }
.new-ingredient-table .amount-col { width: 110px; }
.new-ingredient-table .unit-col { width: 90px; }
.new-ingredient-table .remove-col { width: 44px; text-align: center; }
.new-recipe-help { color: var(--s500); font-size: 11.5px; margin: 3px 0 9px; }
.new-instructions { max-width:850px; }
.new-save-row { display:flex; justify-content:flex-start; margin-top:12px; }
.new-save-row .btn { min-width:260px; }
@media (max-width: 900px) {
  .new-product-grid { grid-template-columns: minmax(240px, 1fr) 160px; }
  .new-product-grid .category-field { grid-column:1/-1; max-width:420px; }
}
@media (max-width: 640px) {
  .new-recipe-grid, .new-product-grid { grid-template-columns: 1fr; }
  .new-product-grid .category-field { grid-column:auto; max-width:none; }
  .new-save-row .btn { width:100%; min-width:0; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🍹 Quản lý thực đơn</h1>
      <p>Thêm, sửa, ẩn sản phẩm · <?= $products->num_rows ?> món đang hiển thị</p>
    </div>
  </div>

  <?php if ($imported > 0): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">✅ Import thành công <?= $imported ?> món mới</div>
  <?php endif; ?>

  <?php if ($added): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">
    ✅ Đã thêm món<?= $isAdmin ? ' và công thức' : '' ?> thành công.
  </div>
  <?php endif; ?>

  <?php if ($updated): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">
    ✅ Đã cập nhật món và đồng bộ tên trong công thức.
  </div>
  <?php endif; ?>

  <?php if ($addError !== ''): ?>
  <div class="alert alert-err mt-8" style="margin-bottom:14px;">
    ⚠️ Không thể thêm món: <?= htmlspecialchars($addError) ?>
  </div>
  <?php endif; ?>

  <?php if ($updateError !== ''): ?>
  <div class="alert alert-err mt-8" style="margin-bottom:14px;">
    ⚠️ Không thể cập nhật món: <?= htmlspecialchars($updateError) ?>
  </div>
  <?php endif; ?>

  <?php if ($recipeLoadError !== ''): ?>
  <div class="alert alert-warn mt-8" style="margin-bottom:14px;">
    ⚠️ Chưa tải được dữ liệu công thức: <?= htmlspecialchars($recipeLoadError) ?>
  </div>
  <?php endif; ?>

  <div class="grid g2" style="gap:14px;margin-bottom:20px;">

    <!-- Add form -->
    <div class="card product-create-card" <?= $isAdmin ? 'style="grid-column:1/-1;"' : '' ?>>
      <div class="section-title">
        ➕ <?= $isAdmin ? 'Thêm món và công thức' : 'Thêm món mới' ?>
      </div>
      <form method="post">
        <?= csrf_field() ?>
        <div class="new-product-grid">
          <div class="form-group">
            <label class="form-label">Tên món</label>
            <input
              type="text"
              name="name"
              class="form-input"
              placeholder="Tên món"
              value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
              required
            >
          </div>
          <div class="form-group">
            <label class="form-label">Giá bán (đ)</label>
            <input
              type="text"
              name="price"
              class="form-input money-input"
              placeholder="Giá"
              value="<?= htmlspecialchars($_POST['price'] ?? '') ?>"
              required
            >
          </div>
          <div class="form-group category-field">
            <label class="form-label">Danh mục</label>
            <select name="category_id" class="form-select">
              <?php foreach ($catList as $cid => $cname): ?>
              <option value="<?= $cid ?>" <?= (int)($_POST['category_id'] ?? 0) === (int)$cid ? 'selected' : '' ?>>
                <?= htmlspecialchars($cname) ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <?php if ($isAdmin): ?>
        <div class="new-recipe-box">
          <div class="new-recipe-grid">
            <div class="form-group">
              <label class="form-label">Tên công thức</label>
              <input
                type="text"
                name="new_recipe_name"
                class="form-input"
                value="<?= htmlspecialchars($_POST['new_recipe_name'] ?? '') ?>"
                placeholder="Để trống sẽ dùng tên món"
              >
            </div>
            <div class="form-group">
              <label class="form-label">Loại công thức</label>
              <select name="new_recipe_type" class="form-select">
                <option value="drink" <?= ($_POST['new_recipe_type'] ?? '') !== 'food' ? 'selected' : '' ?>>Đồ uống</option>
                <option value="food" <?= ($_POST['new_recipe_type'] ?? '') === 'food' ? 'selected' : '' ?>>Món ăn</option>
              </select>
            </div>
          </div>

          <div class="flex-b" style="gap:10px;flex-wrap:wrap;margin-top:2px;">
            <div>
              <div class="section-title" style="margin-bottom:0;">Nguyên liệu và định lượng</div>
              <div class="new-recipe-help">
                Dùng cột Chung nếu định lượng giống nhau; dùng hai cột Nóng/Đá nếu công thức khác nhau.
              </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addNewRecipeRow()">＋ Thêm dòng</button>
          </div>

          <div class="tbl-wrap">
            <table class="new-ingredient-table">
              <thead>
                <tr>
                  <th class="ingredient-col">Nguyên liệu</th>
                  <th class="amount-col">Chung</th>
                  <th class="amount-col">Nóng</th>
                  <th class="amount-col">Đá</th>
                  <th class="unit-col">Đơn vị</th>
                  <th class="remove-col"></th>
                </tr>
              </thead>
              <tbody id="newRecipeRows">
                <?php foreach ($newIngredientRows as $row): ?>
                <tr>
                  <td><input type="text" name="new_ingredient_name[]" class="form-input" value="<?= htmlspecialchars($row['name']) ?>" placeholder="Tên nguyên liệu"></td>
                  <td><input type="text" name="new_amount_common[]" class="form-input" value="<?= htmlspecialchars($row['common']) ?>" placeholder="VD: 25"></td>
                  <td><input type="text" name="new_amount_hot[]" class="form-input" value="<?= htmlspecialchars($row['hot']) ?>" placeholder="VD: 120"></td>
                  <td><input type="text" name="new_amount_iced[]" class="form-input" value="<?= htmlspecialchars($row['iced']) ?>" placeholder="VD: 80"></td>
                  <td><input type="text" name="new_ingredient_unit[]" class="form-input" value="<?= htmlspecialchars($row['unit']) ?>" placeholder="ml, g..."></td>
                  <td class="remove-col">
                    <button type="button" class="btn btn-danger btn-sm" onclick="removeNewRecipeRow(this)" title="Xóa dòng">×</button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="form-group new-instructions" style="margin-top:12px;">
            <label class="form-label">Cách thực hiện</label>
            <textarea
              name="new_recipe_instructions"
              class="form-textarea"
              rows="5"
              placeholder="Nhập từng bước chế biến..."
            ><?= htmlspecialchars($_POST['new_recipe_instructions'] ?? '') ?></textarea>
          </div>
        </div>
        <?php endif; ?>

        <div class="new-save-row">
          <button type="submit" name="add" class="btn btn-primary">
            <?= $isAdmin ? 'Thêm món và lưu công thức' : 'Thêm món' ?>
          </button>
        </div>
      </form>
    </div>

    <!-- Import Excel -->
    <div class="card">
      <div class="section-title">📊 Import từ Excel</div>
      <p class="text-muted text-sm" style="margin-bottom:12px;">
        File .xlsx có 3 cột: <b>Danh mục</b> · <b>Tên món</b> · <b>Giá</b><br>
        Hàng đầu là tiêu đề, bỏ qua tự động. Tên trùng sẽ được bỏ qua.
        Món import chưa có công thức có thể bổ sung bằng nút 📋 trong danh sách.
      </p>
      <form method="post" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="form-group">
          <label class="form-label">Chọn file Excel</label>
          <input type="file" name="excel_file" class="form-input" accept=".xlsx,.xls" required style="padding:7px;">
        </div>
        <button type="submit" name="import_excel" class="btn btn-secondary btn-block">📥 Import menu</button>
      </form>
    </div>
  </div>

  <!-- Filter toolbar -->
  <div class="prod-toolbar product-list-width">
    <select id="catFilter" class="form-select" style="width:200px;" onchange="filterProducts()">
      <option value="">📂 Tất cả danh mục</option>
      <?php foreach ($catList as $cid => $cname): ?>
      <option value="<?= htmlspecialchars($cname) ?>"><?= htmlspecialchars($cname) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="text" id="searchProd" class="form-input" placeholder="🔍 Tìm món..." style="flex:1;max-width:320px;" oninput="filterProducts()">
    <span id="countLabel" class="text-sm text-muted" style="align-self:center;"></span>
  </div>

  <!-- Products table -->
  <div class="tbl-wrap product-list-width">
    <table class="tbl" id="productTable">
      <thead>
        <tr>
          <th style="width:160px;">Danh mục</th>
          <th>Tên món</th>
          <th style="width:110px;">Giá</th>
          <th style="width:60px;text-align:center;">Thứ tự</th>
          <th style="width:<?= $isAdmin ? '145px' : '90px' ?>;">Thao tác</th>
        </tr>
      </thead>
      <tbody>
        <?php $products->data_seek(0); while ($row = $products->fetch_assoc()):
          $rowRecipeMapping = $productRecipeData
              ? recipeFindProductMappingInData($productRecipeData, $row['name'])
              : null;
          $rowHasRecipe = $rowRecipeMapping
              && isset($productRecipeData['recipes'][$rowRecipeMapping['recipe_code']]);
        ?>
        <tr class="product-row" data-cat="<?= htmlspecialchars($row['category_name']) ?>" data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>">
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $row['id'] ?>">
            <td>
              <select name="category_id" class="edit-select">
                <?php foreach ($catList as $cid => $cname): ?>
                <option value="<?= $cid ?>" <?= $cid == $row['category_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($cname) ?>
                </option>
                <?php endforeach; ?>
              </select>
            </td>
            <td>
              <input type="text" name="name" class="edit-input"
                     value="<?= htmlspecialchars($row['name']) ?>" required>
            </td>
            <td>
              <input type="text" name="price" class="edit-input money-input"
                     value="<?= number_format($row['price']) ?>">
            </td>
            <td>
              <input type="number" name="sort_order" class="edit-input sort-input"
                     value="<?= $row['sort_order'] ?>" min="0">
            </td>
            <td>
              <div class="row-btns">
                <?php if ($isAdmin): ?>
                <a
                  href="recipes.php?product_id=<?= $row['id'] ?>"
                  class="btn btn-sm btn-secondary"
                  title="<?= $rowHasRecipe ? 'Sửa công thức' : 'Thêm công thức' ?>"
                ><?= $rowHasRecipe ? '📋' : '＋📋' ?></a>
                <?php endif; ?>
                <button type="submit" name="update" class="btn btn-sm btn-success" title="Lưu">💾</button>
                <button type="submit" name="delete" value="<?= $row['id'] ?>"
                        class="btn btn-sm btn-danger" title="Ẩn"
                        onclick="return confirm('Ẩn món «<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>»?')">🗑</button>
              </div>
            </td>
          </form>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>

</div>

<?php if ($isAdmin): ?>
<template id="newRecipeRowTemplate">
  <tr>
    <td><input type="text" name="new_ingredient_name[]" class="form-input" placeholder="Tên nguyên liệu"></td>
    <td><input type="text" name="new_amount_common[]" class="form-input" placeholder="VD: 25"></td>
    <td><input type="text" name="new_amount_hot[]" class="form-input" placeholder="VD: 120"></td>
    <td><input type="text" name="new_amount_iced[]" class="form-input" placeholder="VD: 80"></td>
    <td><input type="text" name="new_ingredient_unit[]" class="form-input" placeholder="ml, g..."></td>
    <td class="remove-col">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeNewRecipeRow(this)" title="Xóa dòng">×</button>
    </td>
  </tr>
</template>
<?php endif; ?>

<script>
function addNewRecipeRow() {
  const template = document.getElementById('newRecipeRowTemplate');
  const rows = document.getElementById('newRecipeRows');
  if (!template || !rows) return;
  rows.appendChild(template.content.cloneNode(true));
  rows.lastElementChild.querySelector('input').focus();
}

function removeNewRecipeRow(button) {
  const rows = document.getElementById('newRecipeRows');
  if (!rows) return;
  if (rows.children.length === 1) {
    rows.querySelectorAll('input').forEach(input => input.value = '');
    return;
  }
  button.closest('tr').remove();
}

/* Format money inputs */
document.querySelectorAll('.money-input').forEach(el => {
  el.addEventListener('input', function() {
    const v = this.value.replace(/\D/g,'');
    this.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
});

/* Filter */
function filterProducts() {
  const kw  = document.getElementById('searchProd').value.trim().toLowerCase();
  const cat = document.getElementById('catFilter').value.trim().toLowerCase();
  const rows = document.querySelectorAll('.product-row');
  let visible = 0;
  rows.forEach(r => {
    const matchCat  = !cat || (r.dataset.cat || '').toLowerCase().includes(cat);
    const matchName = !kw  || r.dataset.name.includes(kw);
    const show = matchCat && matchName;
    r.style.display = show ? '' : 'none';
    if (show) visible++;
  });
  document.getElementById('countLabel').textContent = visible + ' món';
}
filterProducts();
</script>
</body>
</html>
