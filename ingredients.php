<?php
include 'auth.php';
requireRole(['admin', 'staff', 'user']);
require_once __DIR__ . '/recipe_helpers.php';

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$error = '';
$saved = isset($_GET['saved']);
$materials = [];
$recipeData = null;
$editorRecipe = null;
$editCode = (string)($_POST['material_code'] ?? $_GET['edit'] ?? '');
$isNewEditor = $isAdmin && isset($_GET['new']);

function materialAdminCleanSingleLine($value)
{
    return trim(preg_replace('/[\r\n\t]+/u', ' ', (string)$value));
}

function materialAdminPostedArray($key)
{
    return isset($_POST[$key]) && is_array($_POST[$key]) ? $_POST[$key] : [];
}

function materialIsDrinkBaseRecipe($recipe)
{
    $sourceSheet = isset($recipe['source']['sheet'])
        ? (string)$recipe['source']['sheet']
        : '';

    return ($recipe['type'] ?? '') === 'base'
        && in_array($sourceSheet, ['ĐỒ UỐNG', 'ADMIN'], true);
}

try {
    $recipeData = recipeReadDataFile();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        requireRole('admin');
        csrf_require($_POST['csrf_token'] ?? '');

        $materialName = materialAdminCleanSingleLine($_POST['material_name'] ?? '');
        $instructions = trim((string)($_POST['material_instructions'] ?? ''));
        $ingredientNames = materialAdminPostedArray('material_ingredient_name');
        $ingredientAmounts = materialAdminPostedArray('material_ingredient_amount');
        $ingredientUnits = materialAdminPostedArray('material_ingredient_unit');
        $ingredients = [];

        $rowCount = min(count($ingredientNames), 100);
        for ($index = 0; $index < $rowCount; $index++) {
            $ingredientName = materialAdminCleanSingleLine($ingredientNames[$index] ?? '');
            if ($ingredientName === '') {
                continue;
            }

            $amount = materialAdminCleanSingleLine($ingredientAmounts[$index] ?? '');
            $unit = materialAdminCleanSingleLine($ingredientUnits[$index] ?? '');

            if (
                strlen($ingredientName) > 500
                || strlen($amount) > 300
                || strlen($unit) > 100
            ) {
                throw new RuntimeException('Một dòng thành phần có nội dung quá dài.');
            }

            $ingredients[] = [
                'name' => $ingredientName,
                'amount' => $amount,
                'unit' => $unit,
                'sort_order' => count($ingredients) + 1,
            ];
        }

        $editorRecipe = [
            'name' => $materialName,
            'type' => 'base',
            'category' => 'NGUYÊN LIỆU',
            'instructions' => $instructions,
            'ingredients' => $ingredients,
        ];

        if ($materialName === '') {
            throw new RuntimeException('Vui lòng nhập tên nguyên liệu.');
        }
        if (strlen($materialName) > 360 || strlen($instructions) > 30000) {
            throw new RuntimeException('Tên hoặc cách chuẩn bị quá dài.');
        }
        if (!$ingredients && $instructions === '') {
            throw new RuntimeException('Vui lòng nhập thành phần hoặc cách chuẩn bị.');
        }

        $isNewMaterial = $editCode === '';
        if ($isNewMaterial) {
            do {
                $editCode = 'material-' . bin2hex(random_bytes(4));
            } while (isset($recipeData['recipes'][$editCode]));
            $source = ['sheet' => 'ADMIN', 'row' => null];
        } else {
            $existingRecipe = $recipeData['recipes'][$editCode] ?? null;
            if (!$existingRecipe || !materialIsDrinkBaseRecipe($existingRecipe)) {
                throw new RuntimeException('Không tìm thấy nguyên liệu được phép chỉnh sửa.');
            }
            $source = $existingRecipe['source'];
        }

        $editorRecipe['source'] = $source;
        $editorRecipe['updated_at'] = date(DATE_ATOM);
        $recipeData['recipes'][$editCode] = $editorRecipe;
        $recipeData['updated_at'] = date(DATE_ATOM);
        recipeWriteDataFile($recipeData);

        header('Location: ingredients.php?saved=1&edit=' . urlencode($editCode));
        exit;
    }

    foreach ($recipeData['recipes'] as $code => $recipe) {
        $source = isset($recipe['source']) && is_array($recipe['source'])
            ? $recipe['source']
            : [];

        if (!materialIsDrinkBaseRecipe($recipe)) {
            continue;
        }

        $recipe['code'] = $code;
        $recipe['source_row'] = (int)($source['row'] ?? 0);
        $recipe['source_sheet'] = (string)($source['sheet'] ?? '');
        $recipe['sort_order'] = $recipe['source_row'] > 0
            ? $recipe['source_row']
            : 999999;
        $materials[] = $recipe;
    }

    usort($materials, function ($left, $right) {
        if ($left['sort_order'] === $right['sort_order']) {
            return strcmp((string)$left['name'], (string)$right['name']);
        }
        return $left['sort_order'] <=> $right['sort_order'];
    });
} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (is_array($recipeData) && !$materials) {
    foreach ($recipeData['recipes'] as $code => $recipe) {
        if (!materialIsDrinkBaseRecipe($recipe)) {
            continue;
        }

        $source = isset($recipe['source']) && is_array($recipe['source'])
            ? $recipe['source']
            : [];
        $recipe['code'] = $code;
        $recipe['source_row'] = (int)($source['row'] ?? 0);
        $recipe['source_sheet'] = (string)($source['sheet'] ?? '');
        $recipe['sort_order'] = $recipe['source_row'] > 0
            ? $recipe['source_row']
            : 999999;
        $materials[] = $recipe;
    }

    usort($materials, function ($left, $right) {
        if ($left['sort_order'] === $right['sort_order']) {
            return strcmp((string)$left['name'], (string)$right['name']);
        }
        return $left['sort_order'] <=> $right['sort_order'];
    });
}

if ($isAdmin && $editorRecipe === null) {
    if ($isNewEditor) {
        $editorRecipe = [
            'name' => '',
            'instructions' => '',
            'ingredients' => [],
        ];
        $editCode = '';
    } elseif ($editCode !== '' && isset($recipeData['recipes'][$editCode])) {
        $candidate = $recipeData['recipes'][$editCode];
        if (materialIsDrinkBaseRecipe($candidate)) {
            $editorRecipe = $candidate;
        }
    }
}

$editorIngredientRows = [];
if ($editorRecipe !== null) {
    foreach (($editorRecipe['ingredients'] ?? []) as $ingredient) {
        $editorIngredientRows[] = [
            'name' => (string)($ingredient['name'] ?? ''),
            'amount' => (string)($ingredient['amount'] ?? ''),
            'unit' => (string)($ingredient['unit'] ?? ''),
        ];
    }
    if (!$editorIngredientRows) {
        $editorIngredientRows[] = ['name' => '', 'amount' => '', 'unit' => ''];
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/svg+xml" href="favicon.svg">
<title>Nguyên liệu — GHÉ Coffee</title>
<style>
.page-content { width:100%; max-width:1320px; margin:0 auto; }
.material-toolbar {
  display:flex;
  align-items:center;
  gap:10px;
  margin-bottom:14px;
  flex-wrap:wrap;
}
.material-search { width:100%; max-width:360px; }
.material-count { color:var(--s500); font-size:12px; }
.material-table { min-width:940px; table-layout:fixed; }
.material-table .number-col { width:54px; text-align:center; }
.material-table .name-col { width:210px; }
.material-table .formula-col { width:330px; }
.material-table .steps-col { width:auto; }
.material-table .action-col { width:78px; text-align:center; }
table.material-table > tbody > tr > td {
  vertical-align:top;
  line-height:1.55;
}
.material-name {
  color:var(--s900);
  font-size:13px;
  font-weight:750;
}
.material-source {
  margin-top:4px;
  color:var(--s400);
  font-size:10.5px;
}
.material-ingredients {
  width:100%;
  border-collapse:collapse;
}
table.material-table .material-ingredients td,
.material-card .material-ingredients td {
  padding:3px 0;
  border:0;
  font-size:12.5px;
}
table.material-table .material-ingredients td:last-child,
.material-card .material-ingredients td:last-child {
  width:105px;
  padding-left:12px;
  color:var(--s900);
  text-align:right;
  font-weight:650;
  white-space:nowrap;
}
.material-steps {
  color:var(--s700);
  font-size:12.5px;
  white-space:pre-line;
}
.material-empty { color:var(--s400); font-style:italic; }
.material-mobile { display:none; }
.material-card {
  background:var(--white);
  border:1px solid var(--s200);
  border-radius:var(--r-lg);
  box-shadow:var(--sh);
  overflow:hidden;
}
.material-card + .material-card { margin-top:10px; }
.material-card-head {
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:10px;
  padding:13px 14px;
  background:var(--s50);
  border-bottom:1px solid var(--s200);
}
.material-card-body { padding:13px 14px 15px; }
.material-card-section + .material-card-section { margin-top:13px; }
.material-card-label {
  margin-bottom:5px;
  color:var(--s500);
  font-size:10px;
  font-weight:750;
  letter-spacing:.05em;
  text-transform:uppercase;
}
.material-admin-card {
  width:100%;
  max-width:960px;
  margin-bottom:16px;
}
.material-form-grid {
  display:grid;
  grid-template-columns:minmax(280px, 420px) 1fr;
  gap:14px;
  align-items:start;
}
.material-edit-table {
  width:700px;
  min-width:700px;
  table-layout:fixed;
  border-collapse:collapse;
}
.material-edit-table th {
  padding:7px 5px;
  color:var(--s500);
  background:var(--s50);
  border-bottom:1px solid var(--s200);
  font-size:10px;
  text-align:left;
  text-transform:uppercase;
}
.material-edit-table td { padding:5px; border-bottom:1px solid var(--s100); }
.material-edit-table .form-input { padding:7px 8px; }
.material-edit-table .ingredient-col { width:390px; }
.material-edit-table .amount-col { width:140px; }
.material-edit-table .unit-col { width:100px; }
.material-edit-table .remove-col { width:46px; text-align:center; }
.material-editor-actions {
  display:flex;
  justify-content:flex-end;
  gap:8px;
  margin-top:14px;
}
.material-card-actions { display:flex; align-items:center; gap:7px; }
@media (max-width:760px) {
  .material-desktop { display:none; }
  .material-mobile { display:block; }
  .material-form-grid { grid-template-columns:1fr; }
  .material-editor-actions { flex-direction:column-reverse; }
  .material-editor-actions .btn { width:100%; }
}
@media print {
  .material-toolbar, .material-admin-card { display:none; }
  .material-desktop { display:block; }
  .material-mobile { display:none; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">
  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🧪 Bảng nguyên liệu</h1>
      <p>
        Công thức nguyên liệu nền từ sheet ĐỒ UỐNG ·
        <?= $isAdmin ? 'Admin được thêm và chỉnh sửa' : 'Chỉ xem' ?>
      </p>
    </div>
    <div class="flex no-print">
      <?php if ($isAdmin): ?>
      <a href="ingredients.php?new=1" class="btn btn-primary">＋ Thêm nguyên liệu</a>
      <?php endif; ?>
      <button type="button" class="btn btn-secondary" onclick="window.print()">🖨 In bảng</button>
    </div>
  </div>

  <?php if ($saved): ?>
  <div class="alert alert-ok" style="margin-bottom:14px;">
    ✅ Đã lưu nguyên liệu vào file JSON. Danh sách của nhân viên đã được cập nhật.
  </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
  <div class="alert alert-err" style="margin-bottom:14px;">
    ⚠️ <?= htmlspecialchars($error) ?>
  </div>
  <?php endif; ?>

  <?php if ($isAdmin && $editorRecipe !== null): ?>
  <section class="card material-admin-card no-print">
    <div class="flex-b" style="gap:10px;flex-wrap:wrap;margin-bottom:14px;">
      <div>
        <div class="section-title" style="margin-bottom:2px;">
          <?= $editCode === '' ? '＋ Thêm nguyên liệu mới' : '✏️ Sửa nguyên liệu' ?>
        </div>
        <div class="text-sm text-muted">
          Thành phần và cách chuẩn bị sẽ được lưu trực tiếp vào data/recipes.json.
        </div>
      </div>
      <?php if ($editCode !== ''): ?>
      <span class="badge badge-blue">
        <?= (($editorRecipe['source']['sheet'] ?? '') === 'ADMIN') ? 'Tạo bởi admin' : 'Nguồn ĐỒ UỐNG' ?>
      </span>
      <?php endif; ?>
    </div>

    <form method="post">
      <?= csrf_field() ?>
      <input type="hidden" name="material_code" value="<?= htmlspecialchars($editCode, ENT_QUOTES) ?>">

      <div class="form-group" style="max-width:420px;">
        <label class="form-label">Tên nguyên liệu</label>
        <input
          type="text"
          name="material_name"
          class="form-input"
          value="<?= htmlspecialchars($editorRecipe['name'] ?? '') ?>"
          placeholder="VD: Cốt trà nhài"
          required
        >
      </div>

      <div class="flex-b" style="gap:10px;flex-wrap:wrap;margin-bottom:8px;">
        <div>
          <div class="section-title" style="margin-bottom:1px;">Thành phần · định lượng</div>
          <div class="text-sm text-muted">Có thể để trống nếu toàn bộ nội dung nằm trong cách chuẩn bị.</div>
        </div>
        <button type="button" class="btn btn-secondary btn-sm" onclick="addMaterialIngredientRow()">＋ Thêm dòng</button>
      </div>

      <div class="tbl-wrap" style="max-width:700px;">
        <table class="material-edit-table">
          <thead>
            <tr>
              <th class="ingredient-col">Thành phần</th>
              <th class="amount-col">Định lượng</th>
              <th class="unit-col">Đơn vị</th>
              <th class="remove-col"></th>
            </tr>
          </thead>
          <tbody id="materialIngredientRows">
            <?php foreach ($editorIngredientRows as $row): ?>
            <tr>
              <td><input type="text" name="material_ingredient_name[]" class="form-input" value="<?= htmlspecialchars($row['name']) ?>" placeholder="Tên thành phần"></td>
              <td><input type="text" name="material_ingredient_amount[]" class="form-input" value="<?= htmlspecialchars($row['amount']) ?>" placeholder="VD: 200"></td>
              <td><input type="text" name="material_ingredient_unit[]" class="form-input" value="<?= htmlspecialchars($row['unit']) ?>" placeholder="ml, g..."></td>
              <td class="remove-col">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeMaterialIngredientRow(this)" title="Xóa dòng">×</button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="form-group" style="max-width:700px;margin-top:14px;">
        <label class="form-label">Cách chuẩn bị</label>
        <textarea
          name="material_instructions"
          class="form-textarea"
          rows="7"
          placeholder="Nhập công thức hoặc từng bước chuẩn bị..."
        ><?= htmlspecialchars($editorRecipe['instructions'] ?? '') ?></textarea>
      </div>

      <div class="material-editor-actions" style="max-width:700px;">
        <a href="ingredients.php" class="btn btn-secondary">Hủy</a>
        <button type="submit" class="btn btn-primary">💾 Lưu nguyên liệu</button>
      </div>
    </form>
  </section>
  <?php endif; ?>

  <div class="material-toolbar no-print">
    <input
      type="search"
      id="materialSearch"
      class="form-input material-search"
      placeholder="🔍 Tìm tên hoặc thành phần..."
      oninput="filterMaterials()"
    >
    <span class="material-count" id="materialCount"><?= count($materials) ?> công thức</span>
  </div>

  <?php if (!$materials && $error === ''): ?>
  <div class="card empty-state">
    <div class="empty-state-icon">🧪</div>
    <div>Chưa có dữ liệu nguyên liệu từ sheet ĐỒ UỐNG.</div>
  </div>
  <?php else: ?>
  <div class="tbl-wrap material-desktop">
    <table class="tbl material-table">
      <thead>
        <tr>
          <th class="number-col">STT</th>
          <th class="name-col">Tên nguyên liệu</th>
          <th class="formula-col">Thành phần · định lượng</th>
          <th class="steps-col">Cách chuẩn bị</th>
          <?php if ($isAdmin): ?>
          <th class="action-col">Thao tác</th>
          <?php endif; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materials as $index => $material):
          $ingredients = isset($material['ingredients']) && is_array($material['ingredients'])
              ? $material['ingredients']
              : [];
          $searchText = recipeNormalizeProductName(
              ($material['name'] ?? '') . ' '
              . implode(' ', array_column($ingredients, 'name')) . ' '
              . ($material['instructions'] ?? '')
          );
        ?>
        <tr class="material-row" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>">
          <td class="number-col"><?= $index + 1 ?></td>
          <td>
            <div class="material-name"><?= htmlspecialchars($material['name'] ?? '') ?></div>
            <div class="material-source">
              <?php if ((int)$material['source_row'] > 0): ?>
              Dòng <?= (int)$material['source_row'] ?> · ĐỒ UỐNG
              <?php else: ?>
              Tạo bởi admin
              <?php endif; ?>
            </div>
          </td>
          <td>
            <?php if ($ingredients): ?>
            <table class="material-ingredients">
              <?php foreach ($ingredients as $ingredient):
                $measure = trim(
                    (string)($ingredient['amount'] ?? '')
                    . ' '
                    . (string)($ingredient['unit'] ?? '')
                );
              ?>
              <tr>
                <td><?= htmlspecialchars($ingredient['name'] ?? '') ?></td>
                <td><?= $measure !== '' ? htmlspecialchars($measure) : '—' ?></td>
              </tr>
              <?php endforeach; ?>
            </table>
            <?php else: ?>
            <span class="material-empty">Xem nội dung cách chuẩn bị</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if (!empty($material['instructions'])): ?>
            <div class="material-steps"><?= htmlspecialchars($material['instructions']) ?></div>
            <?php else: ?>
            <span class="material-empty">Không có hướng dẫn riêng</span>
            <?php endif; ?>
          </td>
          <?php if ($isAdmin): ?>
          <td class="action-col">
            <a href="ingredients.php?edit=<?= urlencode($material['code']) ?>" class="btn btn-secondary btn-sm">✏️ Sửa</a>
          </td>
          <?php endif; ?>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <div class="material-mobile">
    <?php foreach ($materials as $index => $material):
      $ingredients = isset($material['ingredients']) && is_array($material['ingredients'])
          ? $material['ingredients']
          : [];
      $searchText = recipeNormalizeProductName(
          ($material['name'] ?? '') . ' '
          . implode(' ', array_column($ingredients, 'name')) . ' '
          . ($material['instructions'] ?? '')
      );
    ?>
    <article class="material-card material-row" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES) ?>">
      <div class="material-card-head">
        <div>
          <div class="material-name"><?= ($index + 1) ?>. <?= htmlspecialchars($material['name'] ?? '') ?></div>
          <div class="material-source">
            <?php if ((int)$material['source_row'] > 0): ?>
            Dòng <?= (int)$material['source_row'] ?> · ĐỒ UỐNG
            <?php else: ?>
            Tạo bởi admin
            <?php endif; ?>
          </div>
        </div>
        <div class="material-card-actions">
          <?php if ($isAdmin): ?>
          <a href="ingredients.php?edit=<?= urlencode($material['code']) ?>" class="btn btn-secondary btn-sm">✏️ Sửa</a>
          <?php endif; ?>
          <span class="badge badge-blue">Nguyên liệu</span>
        </div>
      </div>
      <div class="material-card-body">
        <?php if ($ingredients): ?>
        <div class="material-card-section">
          <div class="material-card-label">Thành phần · định lượng</div>
          <table class="material-ingredients">
            <?php foreach ($ingredients as $ingredient):
              $measure = trim(
                  (string)($ingredient['amount'] ?? '')
                  . ' '
                  . (string)($ingredient['unit'] ?? '')
              );
            ?>
            <tr>
              <td><?= htmlspecialchars($ingredient['name'] ?? '') ?></td>
              <td><?= $measure !== '' ? htmlspecialchars($measure) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
          </table>
        </div>
        <?php endif; ?>

        <div class="material-card-section">
          <div class="material-card-label">Cách chuẩn bị</div>
          <?php if (!empty($material['instructions'])): ?>
          <div class="material-steps"><?= htmlspecialchars($material['instructions']) ?></div>
          <?php else: ?>
          <span class="material-empty">Không có hướng dẫn riêng</span>
          <?php endif; ?>
        </div>
      </div>
    </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<template id="materialIngredientRowTemplate">
  <tr>
    <td><input type="text" name="material_ingredient_name[]" class="form-input" placeholder="Tên thành phần"></td>
    <td><input type="text" name="material_ingredient_amount[]" class="form-input" placeholder="VD: 200"></td>
    <td><input type="text" name="material_ingredient_unit[]" class="form-input" placeholder="ml, g..."></td>
    <td class="remove-col">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeMaterialIngredientRow(this)" title="Xóa dòng">×</button>
    </td>
  </tr>
</template>
<?php endif; ?>

<script>
function addMaterialIngredientRow() {
  const template = document.getElementById('materialIngredientRowTemplate');
  const rows = document.getElementById('materialIngredientRows');
  if (!template || !rows) return;
  rows.appendChild(template.content.cloneNode(true));
  rows.lastElementChild.querySelector('input').focus();
}

function removeMaterialIngredientRow(button) {
  const rows = document.getElementById('materialIngredientRows');
  if (!rows) return;
  if (rows.children.length === 1) {
    rows.querySelectorAll('input').forEach(input => input.value = '');
    return;
  }
  button.closest('tr').remove();
}

function filterMaterials() {
  const query = document.getElementById('materialSearch').value.trim().toLocaleLowerCase('vi');
  const rows = document.querySelectorAll('.material-row');
  let visibleDesktop = 0;

  rows.forEach(row => {
    const show = query === '' || row.dataset.search.includes(query);
    row.hidden = !show;
    if (show && row.tagName === 'TR') visibleDesktop++;
  });

  document.getElementById('materialCount').textContent = visibleDesktop + ' công thức';
}
</script>
</body>
</html>
