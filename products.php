<?php
include 'auth.php';
include 'config.php';
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;
require_once __DIR__ . '/recipe_helpers.php';
requireRole('admin');

$isAdmin = ($_SESSION['role'] ?? '') === 'admin';
$importError = '';
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

function productOptionalMoney($value, string $label): ?int
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === '') {
        throw new RuntimeException($label . ' không hợp lệ.');
    }
    return (int)$digits;
}

function productBakeryRevenueAmountFromPost(int $price): ?int
{
    $cafeAmount = productOptionalMoney($_POST['cafe_revenue_amount'] ?? '', 'Doanh thu Cafe');
    $bakeryAmount = productOptionalMoney($_POST['bakery_revenue_amount'] ?? '', 'Doanh thu Bakery');

    if ($cafeAmount === null && $bakeryAmount === null) {
        return null;
    }
    if ($cafeAmount === null || $bakeryAmount === null) {
        throw new RuntimeException('Vui lòng nhập đủ doanh thu Cafe và Bakery cho combo.');
    }
    if ($cafeAmount + $bakeryAmount !== $price) {
        throw new RuntimeException('Doanh thu Cafe + Bakery phải bằng đúng giá bán của combo.');
    }
    return $bakeryAmount;
}

function productPublicError(Throwable $exception, string $operation): string
{
    if ($exception instanceof mysqli_sql_exception) {
        error_log('Product ' . $operation . ' failed: ' . $exception->getMessage());
        return 'Không thể lưu dữ liệu món. Vui lòng thử lại.';
    }
    return $exception->getMessage();
}

function productIsAjaxRequest(): bool
{
    return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
}

function productJsonResponse(bool $success, string $message, array $data = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['success' => $success, 'message' => $message] + $data,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

/* ── Import Excel ── */
if (isset($_POST['import_excel'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $transactionStarted = false;
    $spreadsheet = null;

    try {
        $upload = $_FILES['excel_file'] ?? null;
        if (!is_array($upload) || (int)($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new UnexpectedValueException('Tệp Excel chưa được tải lên hoặc bị lỗi.');
        }

        $tempPath = (string)($upload['tmp_name'] ?? '');
        $originalName = (string)($upload['name'] ?? '');
        $fileSize = (int)($upload['size'] ?? 0);
        if ($tempPath === '' || !is_uploaded_file($tempPath)) {
            throw new UnexpectedValueException('Tệp tải lên không hợp lệ.');
        }
        if ($fileSize < 1 || $fileSize > 5 * 1024 * 1024) {
            throw new UnexpectedValueException('Tệp Excel phải có dung lượng tối đa 5 MB.');
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $expectedReaders = [
            'xlsx' => IOFactory::READER_XLSX,
            'xls' => IOFactory::READER_XLS,
        ];
        if (!isset($expectedReaders[$extension])) {
            throw new UnexpectedValueException('Chỉ chấp nhận tệp .xlsx hoặc .xls.');
        }

        $allowedMimes = [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-ole-storage',
            'application/cdfv2',
            'application/octet-stream',
        ];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = strtolower((string)$fileInfo->file($tempPath));
        if ($mime === '' || !in_array($mime, $allowedMimes, true)) {
            throw new UnexpectedValueException('Nội dung tệp không đúng định dạng Excel.');
        }

        $readerType = IOFactory::identify($tempPath, array_values($expectedReaders));
        if ($readerType !== $expectedReaders[$extension]) {
            throw new UnexpectedValueException('Phần mở rộng và nội dung tệp Excel không khớp.');
        }

        $reader = IOFactory::createReader($readerType);
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);
        $worksheetInfo = $reader->listWorksheetInfo($tempPath);
        if (!$worksheetInfo) {
            throw new UnexpectedValueException('Tệp Excel không có trang tính.');
        }

        $totalRows = (int)($worksheetInfo[0]['totalRows'] ?? 0);
        $totalColumns = (int)($worksheetInfo[0]['totalColumns'] ?? 0);
        if ($totalRows > 2001) {
            throw new UnexpectedValueException('Mỗi lần chỉ được import tối đa 2.000 món.');
        }
        if ($totalColumns > 20) {
            throw new UnexpectedValueException('Tệp Excel có quá nhiều cột.');
        }

        $reader->setLoadSheetsOnly((string)$worksheetInfo[0]['worksheetName']);
        $spreadsheet = $reader->load($tempPath);
        $rows = $spreadsheet->getActiveSheet()->rangeToArray(
            'A1:C' . max(1, $totalRows),
            null,
            true,
            true,
            false
        );

        $categoryMap = [];
        $categoryResult = $conn->query("SELECT id, name FROM categories");
        while ($categoryRow = $categoryResult->fetch_assoc()) {
            $categoryMap[mb_strtolower(trim((string)$categoryRow['name']), 'UTF-8')] = (int)$categoryRow['id'];
        }
        $productNames = [];
        $productResult = $conn->query("SELECT name FROM products");
        while ($productRow = $productResult->fetch_assoc()) {
            $productNames[mb_strtolower(trim((string)$productRow['name']), 'UTF-8')] = true;
        }

        $insertCategory = $conn->prepare("INSERT INTO categories(name) VALUES(?)");
        $insertProduct = $conn->prepare("INSERT INTO products(name,price,category_id) VALUES(?,?,?)");
        $conn->begin_transaction();
        $transactionStarted = true;
        $imported = 0;

        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            $category = productRecipeCleanValue($row[0] ?? '');
            $name = productRecipeCleanValue($row[1] ?? '');
            $priceDigits = preg_replace('/[^0-9]/', '', (string)($row[2] ?? ''));
            if ($category === '' && $name === '' && $priceDigits === '') continue;
            if ($category === '' || $name === '') {
                throw new UnexpectedValueException('Dòng ' . ($i + 1) . ' thiếu danh mục hoặc tên món.');
            }
            if (mb_strlen($category, 'UTF-8') > 100 || mb_strlen($name, 'UTF-8') > 100) {
                throw new UnexpectedValueException('Dòng ' . ($i + 1) . ' có tên quá dài.');
            }

            $price = (int)$priceDigits;
            if ($price > 9999999999) {
                throw new UnexpectedValueException('Dòng ' . ($i + 1) . ' có giá vượt giới hạn.');
            }
            $categoryKey = mb_strtolower($category, 'UTF-8');
            $nameKey = mb_strtolower($name, 'UTF-8');
            if (isset($productNames[$nameKey])) continue;

            if (!isset($categoryMap[$categoryKey])) {
                $insertCategory->bind_param('s', $category);
                $insertCategory->execute();
                $categoryMap[$categoryKey] = (int)$conn->insert_id;
            }
            $cid = $categoryMap[$categoryKey];
            $insertProduct->bind_param('sii', $name, $price, $cid);
            $insertProduct->execute();
            $productNames[$nameKey] = true;
            $imported++;
        }

        $conn->commit();
        $transactionStarted = false;
        $spreadsheet->disconnectWorksheets();
        header('Location: products.php?imported=' . $imported);
        exit;
    } catch (Throwable $e) {
        if ($transactionStarted) {
            try {
                $conn->rollback();
            } catch (Throwable $rollbackError) {
                error_log('Unable to rollback product import: ' . $rollbackError->getMessage());
            }
        }
        if ($spreadsheet !== null) {
            $spreadsheet->disconnectWorksheets();
        }
        if ($e instanceof UnexpectedValueException) {
            $importError = $e->getMessage();
        } else {
            error_log('Product Excel import failed: ' . $e->getMessage());
            $importError = 'Không thể đọc tệp Excel. Vui lòng kiểm tra lại tệp và thử lại.';
        }
    }
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
        if (mb_strlen($name, 'UTF-8') > 100 || $price > 9999999999) {
            throw new RuntimeException('Tên món hoặc giá vượt giới hạn cho phép.');
        }
        $bakeryRevenueAmount = productBakeryRevenueAmountFromPost($price);

        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO products(name,price,bakery_revenue_amount,category_id) VALUES(?,?,?,?)");
        $stmt->bind_param("siii", $name, $price, $bakeryRevenueAmount, $cid);
        $stmt->execute();
        $newProductId = (int)$conn->insert_id;

        if ($isAdmin) {
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
            if (strlen($instructions) > 30000) {
                throw new RuntimeException('Phần cách thực hiện quá dài.');
            }

            $recipeName = productRecipeCleanValue($_POST['new_recipe_name'] ?? '');
            $recipeType = (string)($_POST['new_recipe_type'] ?? 'drink');
            if (!in_array($recipeType, ['drink', 'food'], true)) {
                $recipeType = 'drink';
            }

            $shouldCreateRecipe = $recipeName !== '' || $instructions !== '' || !empty($ingredients);
            if ($shouldCreateRecipe) {
                $categoryStmt = $conn->prepare("SELECT name FROM categories WHERE id=? LIMIT 1");
                $categoryStmt->bind_param("i", $cid);
                $categoryStmt->execute();
                $categoryRow = $categoryStmt->get_result()->fetch_assoc();
                if (!$categoryRow) {
                    throw new RuntimeException('Danh mục đã chọn không tồn tại.');
                }

                if ($recipeName === '') {
                    $recipeName = $name;
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

        $addError = productPublicError($e, 'creation');
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
        if (mb_strlen($name, 'UTF-8') > 100 || $price > 9999999999) {
            throw new RuntimeException('Tên món hoặc giá vượt giới hạn cho phép.');
        }
        $bakeryRevenueAmount = productBakeryRevenueAmountFromPost($price);

        $conn->begin_transaction();
        $oldProductStmt = $conn->prepare("SELECT name FROM products WHERE id=? LIMIT 1");
        $oldProductStmt->bind_param("i", $id);
        $oldProductStmt->execute();
        $oldProduct = $oldProductStmt->get_result()->fetch_assoc();
        if (!$oldProduct) {
            throw new RuntimeException('Không tìm thấy món cần cập nhật.');
        }

        $stmt = $conn->prepare("UPDATE products SET name=?,price=?,bakery_revenue_amount=?,category_id=?,sort_order=? WHERE id=?");
        $stmt->bind_param("siiiii", $name, $price, $bakeryRevenueAmount, $cid, $sort, $id);
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
        if (productIsAjaxRequest()) {
            productJsonResponse(true, 'Đã lưu thay đổi.', [
                'product_id' => $id,
                'name' => $name,
            ]);
        }
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

        $updateError = productPublicError($e, 'update');
        if (productIsAjaxRequest()) {
            productJsonResponse(false, $updateError, [], 422);
        }
    }
}

/* ── Delete (soft) ── */
if (isset($_POST['delete'])) {
    csrf_require($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['delete'];
    try {
        if ($id <= 0) {
            throw new RuntimeException('Món cần ẩn không hợp lệ.');
        }
        $stmt = $conn->prepare("UPDATE products SET status=0 WHERE id=? AND status=1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new RuntimeException('Không tìm thấy món đang hoạt động để ẩn.');
        }
        if (productIsAjaxRequest()) {
            productJsonResponse(true, 'Đã ẩn món.', ['product_id' => $id]);
        }
    } catch (Throwable $e) {
        $message = productPublicError($e, 'hide');
        if (productIsAjaxRequest()) {
            productJsonResponse(false, $message, [], 422);
        }
        $updateError = $message;
    }
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
.product-create-card { width:100%; max-width:1080px; }
.new-product-grid {
  display: grid;
  grid-template-columns: minmax(280px, 2fr) minmax(130px, .7fr) minmax(180px, 1fr);
  gap: 10px;
  align-items: end;
}
.edit-select, .edit-input {
  min-height:40px;font-size:13px;padding:8px 10px;border-radius:7px;
  border:1px solid var(--s200);width:100%;background:var(--s50);
}
.edit-select { padding-right:28px;cursor:pointer; }
.edit-input:focus, .edit-select:focus { border-color:var(--p); outline:none; background:var(--white); }
.money-input  { text-align:right; font-weight:600; }
.sort-input   { text-align:center; }
.row-btns     { display:flex;gap:6px;align-items:center; }
.row-btns .btn { min-height:40px;white-space:nowrap; }
.product-list-width { width:100%;max-width:1080px; }
.product-card-list { display:grid;gap:8px; }
.product-edit-card {
  display:grid;grid-template-columns:170px minmax(220px,1fr) 125px 78px 230px;
  gap:10px;align-items:end;padding:11px 12px;background:var(--white);
  border:1px solid var(--s200);border-radius:var(--r-sm);box-shadow:var(--sh-sm);
}
.product-edit-card .form-label { margin-bottom:4px;font-size:10px; }
.product-actions { min-width:0; }
.product-edit-card.is-saving { opacity:.65;pointer-events:none; }
.product-action-notice {
  position:fixed;right:18px;bottom:18px;z-index:2000;max-width:min(380px,calc(100vw - 28px));
  padding:11px 14px;border-radius:10px;color:var(--white);font-size:13px;font-weight:700;
  box-shadow:var(--sh-lg);opacity:0;transform:translateY(10px);pointer-events:none;
  transition:opacity .18s ease,transform .18s ease;
}
.product-action-notice.show { opacity:1;transform:translateY(0); }
.product-action-notice.ok { background:var(--ok); }
.product-action-notice.error { background:var(--err); }
.optional-recipe {
  margin-top:14px;border:1px solid var(--s200);border-radius:var(--r-sm);background:var(--s50);
}
.optional-recipe summary {
  cursor:pointer;list-style:none;padding:12px 14px;font-weight:700;color:var(--s700);
  min-height:44px;display:flex;align-items:center;justify-content:space-between;gap:10px;
}
.optional-recipe summary::-webkit-details-marker { display:none; }
.optional-recipe summary::after { content:'⌄';font-size:18px;color:var(--s400); }
.optional-recipe[open] summary { border-bottom:1px solid var(--s200); }
.optional-recipe[open] summary::after { transform:rotate(180deg); }
.optional-recipe-body { padding:14px; }
.optional-badge {
  padding:3px 7px;border-radius:999px;background:var(--white);border:1px solid var(--s200);
  color:var(--s500);font-size:10px;font-weight:600;
}
.revenue-split-grid {
  display:grid;grid-template-columns:repeat(2,minmax(150px,220px));gap:10px;align-items:end;
}
.revenue-split-help { margin:0 0 10px;color:var(--s500);font-size:12px; }
.product-split-details { grid-column:1/-1;margin-top:0; }
.product-split-details summary { min-height:40px;padding:9px 11px;font-size:12px; }
.product-split-details .optional-recipe-body { padding:11px; }
.new-recipe-grid {
  display: grid;
  grid-template-columns: minmax(260px, 420px) 180px;
  gap: 10px;
}
.new-ingredient-list {
  margin-top:8px;border:1px solid var(--s200);border-radius:var(--r-sm);background:var(--white);
}
.new-ingredient-row {
  display:grid;grid-template-columns:minmax(180px,2fr) repeat(3,minmax(72px,.7fr)) minmax(72px,.7fr) 42px;
  gap:8px;align-items:end;padding:10px;
}
.new-ingredient-row + .new-ingredient-row { border-top:1px solid var(--s100); }
.new-ingredient-row .form-group { margin:0;min-width:0; }
.new-ingredient-row .form-label { font-size:10px;margin-bottom:4px; }
.new-ingredient-row .form-input { padding:8px; }
.ingredient-remove .btn { width:100%;min-height:40px; }
.new-recipe-help { color: var(--s500); font-size: 11.5px; margin: 3px 0 9px; }
.new-instructions { max-width:900px; }
.new-save-row { display:flex; justify-content:flex-start; margin-top:12px; }
.new-save-row .btn { min-width:220px;min-height:44px; }
@media (max-width: 900px) {
  .product-edit-card { grid-template-columns:150px minmax(200px,1fr) 115px 72px; }
  .product-actions { grid-column:1/-1; }
}
@media (max-width: 640px) {
  .page-hdr { margin-bottom:14px; }
  .product-create-card { padding:14px; }
  .new-product-grid, .new-recipe-grid, .product-edit-card, .revenue-split-grid { grid-template-columns:1fr 1fr; }
  .new-product-grid .name-field, .new-recipe-grid .recipe-name-field,
  .product-name-field, .product-category-field, .product-actions { grid-column:1/-1; }
  .optional-recipe-body { padding:12px; }
  .optional-recipe summary { padding:11px 12px; }
  .new-ingredient-row { grid-template-columns:1fr 1fr;padding:10px; }
  .new-ingredient-row .ingredient-main { grid-column:1/-1; }
  .ingredient-remove { align-self:end; }
  .prod-toolbar > * { width:100%!important;max-width:none!important; }
  .product-edit-card { padding:12px;gap:9px; }
  .edit-select, .edit-input, .new-product-grid .form-input,
  .new-product-grid .form-select, .new-recipe-grid .form-input,
  .new-recipe-grid .form-select, .new-ingredient-row .form-input { font-size:16px;min-height:44px; }
  .row-btns { display:grid;grid-template-columns:1fr 1fr 1fr;width:100%; }
  .row-btns .btn { width:100%;min-height:44px;padding:8px 6px; }
  .new-save-row .btn { width:100%; min-width:0; }
}
@media (max-width: 420px) {
  .revenue-split-grid { grid-template-columns:1fr; }
}
</style>
</head>
<body>
<?php include 'menu.php'; ?>
<div class="page-content">

  <div id="productActionNotice" class="product-action-notice" role="status" aria-live="polite"></div>

  <div class="page-hdr">
    <div class="page-hdr-left">
      <h1>🍹 Quản lý thực đơn</h1>
      <p>Thêm, sửa, ẩn sản phẩm · <?= $products->num_rows ?> món đang hiển thị</p>
    </div>
  </div>

  <?php if ($imported > 0): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">✅ Import thành công <?= $imported ?> món mới</div>
  <?php endif; ?>

  <?php if ($importError !== ''): ?>
  <div class="alert alert-err mt-8" style="margin-bottom:14px;">
    ⚠️ <?= htmlspecialchars($importError, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
  </div>
  <?php endif; ?>

  <?php if ($added): ?>
  <div class="alert alert-ok mt-8" style="margin-bottom:14px;">
    ✅ Đã thêm món thành công. Nguyên liệu và công thức có thể bổ sung sau.
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
        ➕ Thêm món mới
      </div>
      <form method="post" data-revenue-split-form>
        <?= csrf_field() ?>
        <div class="new-product-grid">
          <div class="form-group name-field">
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
              inputmode="numeric"
              data-total-price
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

        <details class="optional-recipe" <?= trim((string)($_POST['bakery_revenue_amount'] ?? '')) !== '' ? 'open' : '' ?>>
          <summary>
            <span>☕🥖 Phân bổ doanh thu combo</span>
            <span class="optional-badge">Không bắt buộc</span>
          </summary>
          <div class="optional-recipe-body">
            <p class="revenue-split-help">
              Chỉ dùng cho combo Cafe + Bakery. Nhập một bên, hệ thống tự tính bên còn lại; tổng phải bằng giá bán.
            </p>
            <div class="revenue-split-grid">
              <div class="form-group">
                <label class="form-label">Doanh thu Cafe (đ)</label>
                <input
                  type="text"
                  name="cafe_revenue_amount"
                  class="form-input money-input"
                  inputmode="numeric"
                  data-cafe-amount
                  value="<?= htmlspecialchars($_POST['cafe_revenue_amount'] ?? '') ?>"
                  placeholder="VD: 30.000"
                >
              </div>
              <div class="form-group">
                <label class="form-label">Doanh thu Bakery (đ)</label>
                <input
                  type="text"
                  name="bakery_revenue_amount"
                  class="form-input money-input"
                  inputmode="numeric"
                  data-bakery-amount
                  value="<?= htmlspecialchars($_POST['bakery_revenue_amount'] ?? '') ?>"
                  placeholder="VD: 65.000"
                >
              </div>
            </div>
          </div>
        </details>

        <?php if ($isAdmin): ?>
        <details class="optional-recipe" <?= $addError !== '' ? 'open' : '' ?>>
          <summary>
            <span>📋 Thêm công thức</span>
            <span class="optional-badge">Không bắt buộc</span>
          </summary>
          <div class="optional-recipe-body">
          <div class="new-recipe-grid">
            <div class="form-group recipe-name-field">
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
              <div class="section-title" style="margin-bottom:0;">Nguyên liệu và định lượng <span class="text-muted">(không bắt buộc)</span></div>
              <div class="new-recipe-help">
                Có thể bỏ trống hoàn toàn. Khi cần, dùng cột Chung hoặc nhập riêng định lượng Nóng/Đá.
              </div>
            </div>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addNewRecipeRow()">＋ Thêm dòng</button>
          </div>

          <div class="new-ingredient-list" id="newRecipeRows">
            <?php foreach ($newIngredientRows as $row): ?>
            <div class="new-ingredient-row">
              <div class="form-group ingredient-main">
                <label class="form-label">Nguyên liệu</label>
                <input type="text" name="new_ingredient_name[]" class="form-input" value="<?= htmlspecialchars($row['name']) ?>" placeholder="Tên nguyên liệu">
              </div>
              <div class="form-group">
                <label class="form-label">Chung</label>
                <input type="text" name="new_amount_common[]" class="form-input" inputmode="decimal" value="<?= htmlspecialchars($row['common']) ?>" placeholder="VD: 25">
              </div>
              <div class="form-group">
                <label class="form-label">Nóng</label>
                <input type="text" name="new_amount_hot[]" class="form-input" inputmode="decimal" value="<?= htmlspecialchars($row['hot']) ?>" placeholder="VD: 120">
              </div>
              <div class="form-group">
                <label class="form-label">Đá</label>
                <input type="text" name="new_amount_iced[]" class="form-input" inputmode="decimal" value="<?= htmlspecialchars($row['iced']) ?>" placeholder="VD: 80">
              </div>
              <div class="form-group">
                <label class="form-label">Đơn vị</label>
                <input type="text" name="new_ingredient_unit[]" class="form-input" value="<?= htmlspecialchars($row['unit']) ?>" placeholder="ml, g...">
              </div>
              <div class="ingredient-remove">
                <button type="button" class="btn btn-danger btn-sm" onclick="removeNewRecipeRow(this)" title="Xóa dòng">×</button>
              </div>
            </div>
            <?php endforeach; ?>
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
        </details>
        <?php endif; ?>

        <div class="new-save-row">
          <button type="submit" name="add" class="btn btn-primary">
            Thêm món
          </button>
        </div>
      </form>
    </div>

    <!-- Import Excel -->
    <div class="card">
      <div class="section-title">📊 Import từ Excel</div>
      <p class="text-muted text-sm" style="margin-bottom:12px;">
        File .xlsx hoặc .xls, tối đa 5 MB và 2.000 món, có 3 cột: <b>Danh mục</b> · <b>Tên món</b> · <b>Giá</b><br>
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
    <input type="search" id="searchProd" class="form-input" placeholder="🔍 Tìm món..." enterkeyhint="search" style="flex:1;max-width:320px;" oninput="filterProducts()">
    <span id="countLabel" class="text-sm text-muted" style="align-self:center;"></span>
  </div>

  <!-- Product cards: compact on desktop, easy to edit on mobile -->
  <div class="product-card-list product-list-width" id="productList">
    <?php $products->data_seek(0); while ($row = $products->fetch_assoc()):
      $rowRecipeMapping = $productRecipeData
          ? recipeFindProductMappingInData($productRecipeData, $row['name'])
          : null;
      $rowHasRecipe = $rowRecipeMapping
          && isset($productRecipeData['recipes'][$rowRecipeMapping['recipe_code']]);
      $rowBakeryRevenue = $row['bakery_revenue_amount'] !== null
          ? (int)$row['bakery_revenue_amount']
          : null;
      $rowCafeRevenue = $rowBakeryRevenue !== null
          ? (int)$row['price'] - $rowBakeryRevenue
          : null;
    ?>
    <form
      method="post"
      class="product-row product-edit-card"
      data-revenue-split-form
      data-cat="<?= htmlspecialchars($row['category_name']) ?>"
      data-name="<?= htmlspecialchars(strtolower($row['name'])) ?>"
    >
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= $row['id'] ?>">

      <div class="product-category-field">
        <label class="form-label">Danh mục</label>
        <select name="category_id" class="edit-select">
          <?php foreach ($catList as $cid => $cname): ?>
          <option value="<?= $cid ?>" <?= $cid == $row['category_id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($cname) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="product-name-field">
        <label class="form-label">Tên món</label>
        <input type="text" name="name" class="edit-input" value="<?= htmlspecialchars($row['name']) ?>" required>
      </div>

      <div>
        <label class="form-label">Giá bán (đ)</label>
        <input type="text" name="price" class="edit-input money-input" inputmode="numeric" data-total-price value="<?= number_format($row['price']) ?>">
      </div>

      <div>
        <label class="form-label">Thứ tự</label>
        <input type="number" name="sort_order" class="edit-input sort-input" value="<?= $row['sort_order'] ?>" min="0">
      </div>

      <div class="product-actions">
        <label class="form-label">Thao tác</label>
        <div class="row-btns">
          <?php if ($isAdmin): ?>
          <a
            href="recipes.php?product_id=<?= $row['id'] ?>"
            class="btn btn-sm btn-secondary"
            title="<?= $rowHasRecipe ? 'Sửa công thức' : 'Thêm công thức' ?>"
          >📋 Công thức</a>
          <?php endif; ?>
          <button type="submit" name="update" class="btn btn-sm btn-success" title="Lưu">💾 Lưu</button>
          <button
            type="submit"
            name="delete"
            value="<?= $row['id'] ?>"
            class="btn btn-sm btn-danger"
            title="Ẩn"
            onclick="return confirm('Ẩn món «<?= htmlspecialchars($row['name'],ENT_QUOTES) ?>»?')"
          >🗑 Ẩn</button>
        </div>
      </div>

      <details class="optional-recipe product-split-details">
        <summary>
          <span>☕🥖 Phân bổ doanh thu combo</span>
          <span class="optional-badge">
            <?php if ($rowBakeryRevenue === null): ?>
              Theo danh mục
            <?php else: ?>
              Cafe <?= number_format($rowCafeRevenue) ?>đ · Bakery <?= number_format($rowBakeryRevenue) ?>đ
            <?php endif; ?>
          </span>
        </summary>
        <div class="optional-recipe-body">
          <p class="revenue-split-help">Để trống cả hai ô nếu món không phải combo. Tổng hai phần phải bằng giá bán.</p>
          <div class="revenue-split-grid">
            <div class="form-group">
              <label class="form-label">Doanh thu Cafe (đ)</label>
              <input
                type="text"
                name="cafe_revenue_amount"
                class="form-input money-input"
                inputmode="numeric"
                data-cafe-amount
                value="<?= $rowCafeRevenue !== null ? number_format($rowCafeRevenue) : '' ?>"
                placeholder="VD: 30.000"
              >
            </div>
            <div class="form-group">
              <label class="form-label">Doanh thu Bakery (đ)</label>
              <input
                type="text"
                name="bakery_revenue_amount"
                class="form-input money-input"
                inputmode="numeric"
                data-bakery-amount
                value="<?= $rowBakeryRevenue !== null ? number_format($rowBakeryRevenue) : '' ?>"
                placeholder="VD: 65.000"
              >
            </div>
          </div>
        </div>
      </details>
    </form>
    <?php endwhile; ?>
  </div>

</div>

<?php if ($isAdmin): ?>
<template id="newRecipeRowTemplate">
  <div class="new-ingredient-row">
    <div class="form-group ingredient-main">
      <label class="form-label">Nguyên liệu</label>
      <input type="text" name="new_ingredient_name[]" class="form-input" placeholder="Tên nguyên liệu">
    </div>
    <div class="form-group">
      <label class="form-label">Chung</label>
      <input type="text" name="new_amount_common[]" class="form-input" inputmode="decimal" placeholder="VD: 25">
    </div>
    <div class="form-group">
      <label class="form-label">Nóng</label>
      <input type="text" name="new_amount_hot[]" class="form-input" inputmode="decimal" placeholder="VD: 120">
    </div>
    <div class="form-group">
      <label class="form-label">Đá</label>
      <input type="text" name="new_amount_iced[]" class="form-input" inputmode="decimal" placeholder="VD: 80">
    </div>
    <div class="form-group">
      <label class="form-label">Đơn vị</label>
      <input type="text" name="new_ingredient_unit[]" class="form-input" placeholder="ml, g...">
    </div>
    <div class="ingredient-remove">
      <button type="button" class="btn btn-danger btn-sm" onclick="removeNewRecipeRow(this)" title="Xóa dòng">×</button>
    </div>
  </div>
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
  button.closest('.new-ingredient-row').remove();
}

/* Format money inputs */
document.querySelectorAll('.money-input').forEach(el => {
  el.addEventListener('input', function() {
    const v = this.value.replace(/\D/g,'');
    this.value = v ? Number(v).toLocaleString('vi-VN') : '';
  });
});

/* Tự tính phần doanh thu còn lại của combo theo giá bán. */
document.querySelectorAll('[data-revenue-split-form]').forEach(form => {
  const totalInput = form.querySelector('[data-total-price]');
  const cafeInput = form.querySelector('[data-cafe-amount]');
  const bakeryInput = form.querySelector('[data-bakery-amount]');
  if (!totalInput || !cafeInput || !bakeryInput) return;

  const readMoney = input => Number((input.value || '').replace(/\D/g, ''));
  const formatMoney = value => Number(value).toLocaleString('vi-VN');
  const syncOtherSide = (source, target) => {
    if (source.value.trim() === '') {
      target.value = '';
      return;
    }
    const total = readMoney(totalInput);
    const sourceAmount = readMoney(source);
    if (total >= sourceAmount) target.value = formatMoney(total - sourceAmount);
  };

  cafeInput.addEventListener('input', () => syncOtherSide(cafeInput, bakeryInput));
  bakeryInput.addEventListener('input', () => syncOtherSide(bakeryInput, cafeInput));
  totalInput.addEventListener('input', () => {
    if (bakeryInput.value.trim() !== '') syncOtherSide(bakeryInput, cafeInput);
    else if (cafeInput.value.trim() !== '') syncOtherSide(cafeInput, bakeryInput);
  });
});

/* Lưu/ẩn món tại chỗ để thao tác hàng loạt mà không tải lại trang. */
const productActionNotice = document.getElementById('productActionNotice');
let productNoticeTimer = null;
function showProductNotice(message, type = 'ok') {
  if (!productActionNotice) return;
  window.clearTimeout(productNoticeTimer);
  productActionNotice.textContent = message;
  productActionNotice.className = 'product-action-notice ' + type + ' show';
  productNoticeTimer = window.setTimeout(() => {
    productActionNotice.classList.remove('show');
  }, 2600);
}

document.querySelectorAll('.product-edit-card').forEach(form => {
  form.addEventListener('submit', async event => {
    const submitter = event.submitter || form.querySelector('button[name="update"]');
    if (!submitter || !['update', 'delete'].includes(submitter.name)) return;
    event.preventDefault();

    const buttons = form.querySelectorAll('button[type="submit"]');
    form.classList.add('is-saving');
    buttons.forEach(button => button.disabled = true);

    try {
      const payload = new FormData(form);
      payload.set(submitter.name, submitter.value || '1');
      const response = await fetch('products.php', {
        method: 'POST',
        body: payload,
        credentials: 'same-origin',
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      });
      const responseText = await response.text();
      let result = null;
      try { result = JSON.parse(responseText); } catch (error) { result = null; }
      if (!response.ok || !result || !result.success) {
        throw new Error(result?.message || 'Không thể lưu thay đổi. Vui lòng thử lại.');
      }

      if (submitter.name === 'delete') {
        form.remove();
        filterProducts();
      } else {
        const nameInput = form.querySelector('input[name="name"]');
        const categoryInput = form.querySelector('select[name="category_id"]');
        form.dataset.name = (nameInput?.value || '').trim().toLowerCase();
        form.dataset.cat = categoryInput?.selectedOptions[0]?.textContent.trim() || '';

        const cafeValue = form.querySelector('[data-cafe-amount]')?.value.trim() || '';
        const bakeryValue = form.querySelector('[data-bakery-amount]')?.value.trim() || '';
        const splitBadge = form.querySelector('.product-split-details .optional-badge');
        if (splitBadge) {
          splitBadge.textContent = cafeValue && bakeryValue
            ? `Cafe ${cafeValue}đ · Bakery ${bakeryValue}đ`
            : 'Theo danh mục';
        }
        filterProducts();
      }
      showProductNotice(result.message || 'Đã lưu thay đổi.');
    } catch (error) {
      showProductNotice(error.message || 'Không thể lưu thay đổi.', 'error');
    } finally {
      form.classList.remove('is-saving');
      buttons.forEach(button => button.disabled = false);
    }
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
