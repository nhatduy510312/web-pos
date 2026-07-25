<?php
include 'auth.php';
requireRole('admin');
requireCsrfForFormPost();
include 'config.php';
require_once __DIR__ . '/recipe_helpers.php';

function recipeAdminFindProductMapping($data, $productName)
{
    return recipeFindProductMappingInData($data, $productName);
}

function recipeAdminCleanSingleLine($value)
{
    return trim(preg_replace('/[\r\n\t]+/u', ' ', (string)$value));
}

function recipeAdminPostedArray($key)
{
    return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
}

$error = '';
$saved = isset($_GET['saved']);

try {
    $recipeData = recipeReadDataFile();
    $recipePath = recipeDataPath();
    if (!is_writable($recipePath)) {
        $error = 'Máy chủ chưa cấp quyền ghi cho file công thức. Vui lòng kiểm tra quyền trước khi sửa.';
    }
} catch (Throwable $e) {
    $recipeData = [
        'version' => 1,
        'source' => ['file' => 'CÔNG THỨC GHÉ.xlsx', 'sheets' => ['ĐỒ UỐNG', 'MÓN ĂN']],
        'recipes' => [],
        'product_map' => [],
    ];
    $error = $e->getMessage();
}

$products = [];
$productsById = [];
$productResult = $conn->query("
    SELECT p.id, p.name, p.category_id, c.name AS category_name
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.status = 1
    ORDER BY c.name, p.sort_order, p.name
");
while ($product = $productResult->fetch_assoc()) {
    $product['id'] = (int)$product['id'];
    $products[] = $product;
    $productsById[$product['id']] = $product;
}

$selectedProductId = (int)($_POST['product_id'] ?? $_GET['product_id'] ?? ($products[0]['id'] ?? 0));
$postedRecipe = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === '') {
    $selectedProduct = $productsById[$selectedProductId] ?? null;
    if (!$selectedProduct) {
        $error = 'Không tìm thấy món cần cập nhật.';
    } else {
        $currentMapping = recipeAdminFindProductMapping($recipeData, $selectedProduct['name']);
        $recipeCode = $currentMapping['recipe_code'] ?? '';
        $isNewRecipe = $recipeCode === '' || !isset($recipeData['recipes'][$recipeCode]);

        if ($isNewRecipe) {
            $recipeCode = 'product-' . $selectedProductId;
            $suffix = 2;
            while (isset($recipeData['recipes'][$recipeCode])) {
                $recipeCode = 'product-' . $selectedProductId . '-' . $suffix;
                $suffix++;
            }
        }

        $recipeName = recipeAdminCleanSingleLine($_POST['recipe_name'] ?? '');
        $recipeType = (string)($_POST['recipe_type'] ?? 'drink');
        $recipeCategory = recipeAdminCleanSingleLine($_POST['recipe_category'] ?? '');
        $instructions = trim((string)($_POST['instructions'] ?? ''));
        if (!in_array($recipeType, ['drink', 'food'], true)) {
            $recipeType = 'drink';
        }

        $names = recipeAdminPostedArray('ingredient_name');
        $commonAmounts = recipeAdminPostedArray('amount_common');
        $hotAmounts = recipeAdminPostedArray('amount_hot');
        $icedAmounts = recipeAdminPostedArray('amount_iced');
        $units = recipeAdminPostedArray('ingredient_unit');
        $ingredients = [];

        $rowCount = min(count($names), 100);
        for ($index = 0; $index < $rowCount; $index++) {
            $ingredientName = recipeAdminCleanSingleLine($names[$index] ?? '');
            if ($ingredientName === '') {
                continue;
            }

            $commonAmount = recipeAdminCleanSingleLine($commonAmounts[$index] ?? '');
            $hotAmount = recipeAdminCleanSingleLine($hotAmounts[$index] ?? '');
            $icedAmount = recipeAdminCleanSingleLine($icedAmounts[$index] ?? '');
            $unit = recipeAdminCleanSingleLine($units[$index] ?? '');

            $amount = recipeBuildAmountValue($commonAmount, $hotAmount, $icedAmount);

            $ingredients[] = [
                'name' => $ingredientName,
                'amount' => $amount,
                'unit' => $unit,
                'sort_order' => count($ingredients) + 1,
            ];
        }

        $postedRecipe = [
            'name' => $recipeName,
            'type' => $recipeType,
            'category' => $recipeCategory,
            'instructions' => $instructions,
            'ingredients' => $ingredients,
        ];

        if ($recipeName === '') {
            $error = 'Vui lòng nhập tên công thức.';
        } elseif (strlen($recipeName) > 360 || strlen($recipeCategory) > 360) {
            $error = 'Tên công thức hoặc nhóm công thức quá dài.';
        } elseif (strlen($instructions) > 30000) {
            $error = 'Phần cách thực hiện quá dài.';
        } elseif (!$ingredients && $instructions === '') {
            $error = 'Công thức cần có ít nhất một nguyên liệu hoặc phần cách thực hiện.';
        }

        if ($error === '') {
            foreach ($ingredients as $ingredient) {
                if (
                    strlen($ingredient['name']) > 360
                    || strlen($ingredient['amount']) > 500
                    || strlen($ingredient['unit']) > 100
                ) {
                    $error = 'Một dòng nguyên liệu có nội dung quá dài.';
                    break;
                }
            }
        }

        if ($error === '') {
            $existingRecipe = $recipeData['recipes'][$recipeCode] ?? [];
            $postedRecipe['source'] = $existingRecipe['source'] ?? [
                'sheet' => 'ADMIN',
                'row' => null,
            ];
            $postedRecipe['updated_at'] = date(DATE_ATOM);
            $recipeData['recipes'][$recipeCode] = $postedRecipe;

            if ($isNewRecipe) {
                $recipeData['product_map'][] = [
                    'product_names' => [$selectedProduct['name']],
                    'recipe_code' => $recipeCode,
                    'variant' => '',
                ];
            }

            $recipeData['updated_at'] = date(DATE_ATOM);

            try {
                recipeWriteDataFile($recipeData);
                header('Location: recipes.php?product_id=' . $selectedProductId . '&saved=1');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
}

$selectedProduct = $productsById[$selectedProductId] ?? null;
$selectedMapping = $selectedProduct
    ? recipeAdminFindProductMapping($recipeData, $selectedProduct['name'])
    : null;
$selectedRecipeCode = $selectedMapping['recipe_code'] ?? '';
$selectedRecipe = $selectedRecipeCode !== ''
    ? ($recipeData['recipes'][$selectedRecipeCode] ?? null)
    : null;

if ($postedRecipe !== null && $error !== '') {
    $selectedRecipe = $postedRecipe;
}

if ($selectedProduct && !$selectedRecipe) {
    $defaultType = in_array((int)$selectedProduct['category_id'], [6, 8], true)
        ? 'food'
        : 'drink';
    $selectedRecipe = [
        'name' => $selectedProduct['name'],
        'type' => $defaultType,
        'category' => $selectedProduct['category_name'] ?? '',
        'instructions' => '',
        'ingredients' => [],
    ];
}

$appliedProductNames = [];
if ($selectedRecipeCode !== '') {
    foreach ($recipeData['product_map'] as $mapping) {
        if (($mapping['recipe_code'] ?? '') !== $selectedRecipeCode) {
            continue;
        }
        foreach (($mapping['product_names'] ?? []) as $productName) {
            $appliedProductNames[] = (string)$productName;
        }
    }
    $appliedProductNames = array_values(array_unique($appliedProductNames));
}

$ingredientRows = [];
foreach (($selectedRecipe['ingredients'] ?? []) as $ingredient) {
    $amount = (string)($ingredient['amount'] ?? '');
    $splitAmount = recipeSplitHotIcedAmount($amount);
    $hasVariantAmount = $splitAmount['hot'] !== null || $splitAmount['iced'] !== null;
    $ingredientRows[] = [
        'name' => (string)($ingredient['name'] ?? ''),
        'common' => $hasVariantAmount ? '' : $amount,
        'hot' => $splitAmount['hot'] ?? '',
        'iced' => $splitAmount['iced'] ?? '',
        'unit' => (string)($ingredient['unit'] ?? ''),
    ];
}
if (!$ingredientRows) {
    $ingredientRows[] = ['name' => '', 'common' => '', 'hot' => '', 'iced' => '', 'unit' => ''];
}

$mappedProductCount = 0;
foreach ($products as $product) {
    if (recipeAdminFindProductMapping($recipeData, $product['name'])) {
        $mappedProductCount++;
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Công thức — GHÉ Coffee</title>
<style>
.page-content { width:100%; max-width:1320px; margin:0 auto; }
.recipe-layout {
  display: grid;
  grid-template-columns: minmax(250px, 310px) minmax(0, 1fr);
  gap: 14px;
  align-items: start;
}
.recipe-sidebar {
  position: sticky;
  top: 76px;
  padding: 14px;
}
.recipe-search { margin-bottom: 10px; }
.recipe-products {
  max-height: calc(100vh - 220px);
  overflow-y: auto;
  border: 1px solid var(--s200);
  border-radius: var(--r-md);
}
.recipe-product {
  display: block;
  padding: 10px 12px;
  border-bottom: 1px solid var(--s100);
  transition: var(--tr);
}
.recipe-product:last-child { border-bottom: 0; }
.recipe-product:hover { background: var(--s50); }
.recipe-product.active {
  background: var(--p-lt);
  box-shadow: inset 3px 0 0 var(--p);
}
.recipe-product-name {
  color: var(--s900);
  font-size: 13px;
  font-weight: 650;
}
.recipe-product-meta {
  display: flex;
  justify-content: space-between;
  gap: 8px;
  margin-top: 3px;
  color: var(--s400);
  font-size: 10.5px;
}
.recipe-status {
  flex-shrink: 0;
  font-weight: 700;
}
.recipe-status.mapped { color: var(--ok); }
.recipe-status.unmapped { color: var(--warn); }
.recipe-editor { min-width: 0; }
.recipe-editor-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 18px;
}
.recipe-editor-title { font-size: 18px; }
.recipe-shared {
  margin: 12px 0 16px;
  padding: 10px 12px;
  border: 1px solid var(--p-bd);
  border-radius: var(--r-sm);
  background: var(--p-lt);
  color: var(--s700);
  font-size: 12px;
}
.recipe-form-grid {
  display: grid;
  grid-template-columns: minmax(220px, 2fr) minmax(130px, .8fr) minmax(180px, 1fr);
  gap: 10px;
}
.ingredient-editor {
  width: 100%;
  min-width: 760px;
  border-collapse: collapse;
}
.ingredient-editor th {
  padding: 8px;
  color: var(--s500);
  background: var(--s50);
  border-bottom: 1px solid var(--s200);
  font-size: 10.5px;
  text-align: left;
  text-transform: uppercase;
  white-space: nowrap;
}
.ingredient-editor td {
  padding: 6px 5px;
  border-bottom: 1px solid var(--s100);
}
.ingredient-editor .form-input { padding: 7px 8px; }
.ingredient-editor .amount-col { width: 110px; }
.ingredient-editor .unit-col { width: 90px; }
.ingredient-editor .remove-col { width: 46px; text-align: center; }
.ingredient-editor .variant-head { color: var(--p); }
.recipe-savebar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px solid var(--s200);
}
.recipe-help {
  color: var(--s500);
  font-size: 11.5px;
  line-height: 1.5;
}
@media (max-width: 900px) {
  .recipe-layout { grid-template-columns: 1fr; }
  .recipe-sidebar { position: static; }
  .recipe-products { max-height: 280px; }
}
@media (max-width: 640px) {
  .recipe-form-grid { grid-template-columns: 1fr; }
  .recipe-editor-head, .recipe-savebar { align-items: stretch; flex-direction: column; }
  .recipe-savebar .btn { width: 100%; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>📋 Công thức món</h1>
      <p>
        Sửa định lượng và cách thực hiện ·
        <?= $mappedProductCount ?>/<?= count($products) ?> món đã có công thức
      </p>
    </div>
    <a href="products.php" class="btn btn-secondary">← Về Thực đơn</a>
  </div>

  <?php if ($saved): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">
    ✅ Đã lưu vào file JSON. Phiếu chế biến sẽ sử dụng công thức mới ngay từ đơn tiếp theo.
  </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">
    ⚠️ <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <div class="recipe-layout">
    <aside class="card recipe-sidebar">
      <div class="section-title">Chọn món</div>
      <input
        type="search"
        id="recipeSearch"
        class="form-input recipe-search"
        placeholder="🔍 Tìm tên món..."
        oninput="filterRecipeProducts()"
      >
      <div class="recipe-products" id="recipeProducts">
        <?php foreach ($products as $product):
          $mapping = recipeAdminFindProductMapping($recipeData, $product['name']);
          $isMapped = $mapping && isset($recipeData['recipes'][$mapping['recipe_code']]);
        ?>
        <a
          href="recipes.php?product_id=<?= $product['id'] ?>"
          class="recipe-product <?= $product['id'] === $selectedProductId ? 'active' : '' ?>"
          data-search="<?= htmlspecialchars(recipeNormalizeProductName($product['name'] . ' ' . ($product['category_name'] ?? '')), ENT_QUOTES) ?>"
        >
          <div class="recipe-product-name"><?= htmlspecialchars($product['name']) ?></div>
          <div class="recipe-product-meta">
            <span><?= htmlspecialchars($product['category_name'] ?? 'Chưa phân loại') ?></span>
            <span class="recipe-status <?= $isMapped ? 'mapped' : 'unmapped' ?>">
              <?= $isMapped ? 'Đã có' : 'Chưa có' ?>
            </span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </aside>

    <main class="card recipe-editor">
      <?php if (!$selectedProduct): ?>
      <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <div>Chưa có món nào để tạo công thức.</div>
      </div>
      <?php else: ?>
      <div class="recipe-editor-head">
        <div>
          <div class="text-muted text-sm">Đang chỉnh công thức cho món</div>
          <h2 class="recipe-editor-title"><?= htmlspecialchars($selectedProduct['name']) ?></h2>
        </div>
        <?php if ($selectedRecipeCode !== ''): ?>
        <span class="badge badge-green">Đã liên kết JSON</span>
        <?php else: ?>
        <span class="badge badge-yellow">Sẽ tạo công thức mới</span>
        <?php endif; ?>
      </div>

      <?php if (count($appliedProductNames) > 1): ?>
      <div class="recipe-shared">
        <b>Công thức dùng chung:</b>
        <?= htmlspecialchars(implode(' · ', $appliedProductNames)) ?>.
        Khi lưu, tất cả các món này sẽ cùng nhận nội dung mới.
      </div>
      <?php endif; ?>

      <form method="post" id="recipeForm">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= $selectedProductId ?>">

        <div class="recipe-form-grid">
          <div class="form-group">
            <label class="form-label">Tên công thức</label>
            <input
              type="text"
              name="recipe_name"
              class="form-input"
              value="<?= htmlspecialchars($selectedRecipe['name'] ?? '') ?>"
              required
            >
          </div>
          <div class="form-group">
            <label class="form-label">Loại</label>
            <select name="recipe_type" class="form-select">
              <option value="drink" <?= ($selectedRecipe['type'] ?? '') === 'drink' ? 'selected' : '' ?>>Đồ uống</option>
              <option value="food" <?= ($selectedRecipe['type'] ?? '') === 'food' ? 'selected' : '' ?>>Món ăn</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Nhóm công thức</label>
            <input
              type="text"
              name="recipe_category"
              class="form-input"
              value="<?= htmlspecialchars($selectedRecipe['category'] ?? '') ?>"
              placeholder="Cà phê, Trà, Món ăn..."
            >
          </div>
        </div>

        <div class="flex-b" style="margin:4px 0 8px;gap:10px;flex-wrap:wrap;">
          <div>
            <div class="section-title" style="margin-bottom:2px;">Nguyên liệu và định lượng</div>
            <div class="recipe-help">
              Điền cột Chung nếu Nóng/Đá giống nhau. Nếu có hai công thức, để trống Chung và điền Nóng, Đá.
            </div>
          </div>
          <button type="button" class="btn btn-secondary btn-sm" onclick="addIngredientRow()">＋ Thêm dòng</button>
        </div>

        <div class="tbl-wrap">
          <table class="ingredient-editor">
            <thead>
              <tr>
                <th>Nguyên liệu</th>
                <th class="amount-col">Chung</th>
                <th class="amount-col variant-head">Nóng</th>
                <th class="amount-col variant-head">Đá</th>
                <th class="unit-col">Đơn vị</th>
                <th class="remove-col"></th>
              </tr>
            </thead>
            <tbody id="ingredientRows">
              <?php foreach ($ingredientRows as $row): ?>
              <tr>
                <td><input type="text" name="ingredient_name[]" class="form-input" value="<?= htmlspecialchars($row['name']) ?>" placeholder="Tên nguyên liệu"></td>
                <td><input type="text" name="amount_common[]" class="form-input" value="<?= htmlspecialchars($row['common']) ?>" placeholder="VD: 25"></td>
                <td><input type="text" name="amount_hot[]" class="form-input" value="<?= htmlspecialchars($row['hot']) ?>" placeholder="VD: 120"></td>
                <td><input type="text" name="amount_iced[]" class="form-input" value="<?= htmlspecialchars($row['iced']) ?>" placeholder="VD: 80"></td>
                <td><input type="text" name="ingredient_unit[]" class="form-input" value="<?= htmlspecialchars($row['unit']) ?>" placeholder="ml, g..."></td>
                <td class="remove-col">
                  <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow(this)" title="Xóa dòng">×</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="form-group" style="margin-top:16px;">
          <label class="form-label">Cách thực hiện</label>
          <textarea
            name="instructions"
            class="form-textarea"
            rows="8"
            placeholder="Nhập từng bước chế biến..."
          ><?= htmlspecialchars($selectedRecipe['instructions'] ?? '') ?></textarea>
        </div>

        <div class="recipe-savebar">
          <div class="recipe-help">
            Dữ liệu được ghi trực tiếp vào <b>data/recipes.json</b> và có bản sao lưu
            <b>recipes.json.bak</b>.
          </div>
          <button type="submit" class="btn btn-primary btn-lg">💾 Lưu công thức</button>
        </div>
      </form>
      <?php endif; ?>
    </main>
  </div>
</div>

<template id="ingredientRowTemplate">
  <tr>
    <td><input type="text" name="ingredient_name[]" class="form-input" placeholder="Tên nguyên liệu"></td>
    <td><input type="text" name="amount_common[]" class="form-input" placeholder="VD: 25"></td>
    <td><input type="text" name="amount_hot[]" class="form-input" placeholder="VD: 120"></td>
    <td><input type="text" name="amount_iced[]" class="form-input" placeholder="VD: 80"></td>
    <td><input type="text" name="ingredient_unit[]" class="form-input" placeholder="ml, g..."></td>
    <td class="remove-col">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeIngredientRow(this)" title="Xóa dòng">×</button>
    </td>
  </tr>
</template>

<script>
function addIngredientRow() {
  const template = document.getElementById('ingredientRowTemplate');
  const rows = document.getElementById('ingredientRows');
  rows.appendChild(template.content.cloneNode(true));
  rows.lastElementChild.querySelector('input').focus();
}

function removeIngredientRow(button) {
  const rows = document.getElementById('ingredientRows');
  if (rows.children.length === 1) {
    rows.querySelectorAll('input').forEach(input => input.value = '');
    return;
  }
  button.closest('tr').remove();
}

function filterRecipeProducts() {
  const query = document.getElementById('recipeSearch').value.trim().toLocaleLowerCase('vi');
  document.querySelectorAll('.recipe-product').forEach(item => {
    item.hidden = query !== '' && !item.dataset.search.includes(query);
  });
}
</script>
</body>
</html>
