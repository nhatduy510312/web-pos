<?php

function recipeDataPath()
{
    $customPath = getenv('POS_RECIPE_FILE');
    if ($customPath !== false && trim($customPath) !== '') {
        return $customPath;
    }

    return __DIR__ . '/data/recipes.json';
}

/**
 * Đọc file JSON với shared lock để không bắt gặp dữ liệu đang được ghi dở.
 */
function recipeReadDataFile()
{
    $path = recipeDataPath();
    $handle = @fopen($path, 'rb');
    if (!$handle) {
        throw new RuntimeException('Không thể mở file công thức.');
    }

    try {
        if (!flock($handle, LOCK_SH)) {
            throw new RuntimeException('Không thể khóa file công thức để đọc.');
        }

        $json = stream_get_contents($handle);
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }

    $data = json_decode($json, true);
    if (
        !is_array($data)
        || !isset($data['recipes'])
        || !is_array($data['recipes'])
        || !isset($data['product_map'])
        || !is_array($data['product_map'])
    ) {
        throw new RuntimeException('File công thức không đúng cấu trúc JSON.');
    }

    return $data;
}

/**
 * Ghi đè JSON có khóa độc quyền và sao lưu bản cũ trước khi lưu.
 */
function recipeWriteDataFile($data)
{
    if (
        !is_array($data)
        || !isset($data['recipes'])
        || !is_array($data['recipes'])
        || !isset($data['product_map'])
        || !is_array($data['product_map'])
    ) {
        throw new InvalidArgumentException('Dữ liệu công thức không hợp lệ.');
    }

    $json = json_encode(
        $data,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
    if ($json === false) {
        throw new RuntimeException('Không thể chuyển dữ liệu công thức thành JSON.');
    }
    $json .= PHP_EOL;

    $path = recipeDataPath();
    $handle = @fopen($path, 'c+b');
    if (!$handle) {
        throw new RuntimeException('File công thức không có quyền ghi.');
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            throw new RuntimeException('Không thể khóa file công thức để lưu.');
        }

        rewind($handle);
        $previousJson = stream_get_contents($handle);
        if ($previousJson !== false && $previousJson !== '') {
            $backupPath = $path . '.bak';
            if (file_put_contents($backupPath, $previousJson, LOCK_EX) === false) {
                throw new RuntimeException('Không thể tạo bản sao lưu công thức.');
            }
        }

        rewind($handle);
        if (!ftruncate($handle, 0)) {
            throw new RuntimeException('Không thể làm trống file công thức cũ.');
        }

        $length = strlen($json);
        $written = 0;
        while ($written < $length) {
            $bytes = fwrite($handle, substr($json, $written));
            if ($bytes === false || $bytes === 0) {
                throw new RuntimeException('Không thể ghi đầy đủ file công thức.');
            }
            $written += $bytes;
        }

        fflush($handle);
        if (function_exists('fsync')) {
            fsync($handle);
        }
        flock($handle, LOCK_UN);
    } finally {
        fclose($handle);
    }
}

/**
 * Chuẩn hóa tên sản phẩm để ánh xạ ổn định với data/recipes.json.
 */
function recipeNormalizeProductName($name)
{
    $name = trim((string)$name);
    $name = preg_replace('/\s+/u', ' ', $name);

    if (function_exists('mb_strtolower')) {
        return mb_strtolower($name, 'UTF-8');
    }

    $vietnameseUppercase = [
        'À' => 'à', 'Á' => 'á', 'Ả' => 'ả', 'Ã' => 'ã', 'Ạ' => 'ạ',
        'Ă' => 'ă', 'Ằ' => 'ằ', 'Ắ' => 'ắ', 'Ẳ' => 'ẳ', 'Ẵ' => 'ẵ', 'Ặ' => 'ặ',
        'Â' => 'â', 'Ầ' => 'ầ', 'Ấ' => 'ấ', 'Ẩ' => 'ẩ', 'Ẫ' => 'ẫ', 'Ậ' => 'ậ',
        'Đ' => 'đ',
        'È' => 'è', 'É' => 'é', 'Ẻ' => 'ẻ', 'Ẽ' => 'ẽ', 'Ẹ' => 'ẹ',
        'Ê' => 'ê', 'Ề' => 'ề', 'Ế' => 'ế', 'Ể' => 'ể', 'Ễ' => 'ễ', 'Ệ' => 'ệ',
        'Ì' => 'ì', 'Í' => 'í', 'Ỉ' => 'ỉ', 'Ĩ' => 'ĩ', 'Ị' => 'ị',
        'Ò' => 'ò', 'Ó' => 'ó', 'Ỏ' => 'ỏ', 'Õ' => 'õ', 'Ọ' => 'ọ',
        'Ô' => 'ô', 'Ồ' => 'ồ', 'Ố' => 'ố', 'Ổ' => 'ổ', 'Ỗ' => 'ỗ', 'Ộ' => 'ộ',
        'Ơ' => 'ơ', 'Ờ' => 'ờ', 'Ớ' => 'ớ', 'Ở' => 'ở', 'Ỡ' => 'ỡ', 'Ợ' => 'ợ',
        'Ù' => 'ù', 'Ú' => 'ú', 'Ủ' => 'ủ', 'Ũ' => 'ũ', 'Ụ' => 'ụ',
        'Ư' => 'ư', 'Ừ' => 'ừ', 'Ứ' => 'ứ', 'Ử' => 'ử', 'Ữ' => 'ữ', 'Ự' => 'ự',
        'Ỳ' => 'ỳ', 'Ý' => 'ý', 'Ỷ' => 'ỷ', 'Ỹ' => 'ỹ', 'Ỵ' => 'ỵ',
    ];

    return strtolower(strtr($name, $vietnameseUppercase));
}

function recipeFindProductMappingInData($data, $productName)
{
    $normalizedProductName = recipeNormalizeProductName($productName);

    foreach (($data['product_map'] ?? []) as $index => $mapping) {
        $productNames = isset($mapping['product_names']) && is_array($mapping['product_names'])
            ? $mapping['product_names']
            : [];

        foreach ($productNames as $mappedName) {
            if (recipeNormalizeProductName($mappedName) === $normalizedProductName) {
                return [
                    'index' => $index,
                    'recipe_code' => (string)($mapping['recipe_code'] ?? ''),
                    'variant' => (string)($mapping['variant'] ?? ''),
                ];
            }
        }
    }

    return null;
}

function recipeBuildAmountValue($commonAmount, $hotAmount, $icedAmount)
{
    $commonAmount = trim((string)$commonAmount);
    $hotAmount = trim((string)$hotAmount);
    $icedAmount = trim((string)$icedAmount);

    if ($hotAmount === '' && $icedAmount === '') {
        return $commonAmount;
    }

    $parts = [];
    if ($hotAmount !== '') {
        $parts[] = 'Nóng: ' . $hotAmount;
    }
    if ($icedAmount !== '') {
        $parts[] = 'Đá: ' . $icedAmount;
    }

    return implode(' · ', $parts);
}

/**
 * Đọc danh mục công thức từ JSON và tạo chỉ mục theo tên sản phẩm.
 *
 * Kết quả được cache trong phạm vi một request PHP để không đọc file nhiều lần.
 */
function loadRecipeCatalog()
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    $catalog = [
        'recipes' => [],
        'lookup' => [],
    ];

    try {
        $data = recipeReadDataFile();
    } catch (Throwable $e) {
        error_log('Recipe JSON unavailable: ' . $e->getMessage());
        return $catalog;
    }

    $catalog['recipes'] = $data['recipes'];

    foreach ($data['product_map'] as $mapping) {
        $recipeCode = isset($mapping['recipe_code']) ? (string)$mapping['recipe_code'] : '';
        $productNames = isset($mapping['product_names']) && is_array($mapping['product_names'])
            ? $mapping['product_names']
            : [];

        if ($recipeCode === '' || !isset($catalog['recipes'][$recipeCode])) {
            continue;
        }

        foreach ($productNames as $productName) {
            $normalizedName = recipeNormalizeProductName($productName);
            if ($normalizedName === '') {
                continue;
            }

            $catalog['lookup'][$normalizedName] = [
                'recipe_code' => $recipeCode,
                'variant' => isset($mapping['variant']) ? (string)$mapping['variant'] : '',
            ];
        }
    }

    return $catalog;
}

/**
 * Nếu công thức có đủ hai định lượng, hiển thị rõ đây là công thức Nóng / Đá.
 */
function recipeDisplayVariantLabel($ingredients, $fallbackLabel)
{
    $hasHotAmount = false;
    $hasIcedAmount = false;

    foreach ($ingredients as $ingredient) {
        $amount = isset($ingredient['amount']) ? (string)$ingredient['amount'] : '';
        $hasHotAmount = $hasHotAmount || preg_match('/Nóng/ui', $amount);
        $hasIcedAmount = $hasIcedAmount || preg_match('/Đá/ui', $amount);
    }

    if ($hasHotAmount && $hasIcedAmount) {
        return 'Nóng / Đá';
    }

    return (string)$fallbackLabel;
}

/**
 * Tách chuỗi định lượng như "Nóng: 5 · Đá: 7" thành hai giá trị thuần.
 */
function recipeSplitHotIcedAmount($amount)
{
    $amount = trim((string)$amount);
    $hotAmount = null;
    $icedAmount = null;

    if (preg_match('/Nóng\s*:?\s*([^·,]+)/ui', $amount, $matches)) {
        $hotAmount = trim($matches[1]);
    }
    if (preg_match('/Đá\s*:?\s*([^·,]+)/ui', $amount, $matches)) {
        $icedAmount = trim($matches[1]);
    }

    return [
        'hot' => $hotAmount,
        'iced' => $icedAmount,
    ];
}

/**
 * Tìm công thức theo đúng tên sản phẩm đang lưu trong bảng products.
 */
function findRecipeForProductName($catalog, $productName)
{
    $normalizedName = recipeNormalizeProductName($productName);
    if ($normalizedName === '' || !isset($catalog['lookup'][$normalizedName])) {
        return null;
    }

    $mapping = $catalog['lookup'][$normalizedName];
    $recipeCode = $mapping['recipe_code'];
    if (!isset($catalog['recipes'][$recipeCode])) {
        return null;
    }

    $sourceRecipe = $catalog['recipes'][$recipeCode];
    $variant = $mapping['variant'];
    $sourceIngredients = isset($sourceRecipe['ingredients']) && is_array($sourceRecipe['ingredients'])
        ? $sourceRecipe['ingredients']
        : [];
    $displayVariant = recipeDisplayVariantLabel($sourceIngredients, $variant);
    $hasHotIcedAmounts = $displayVariant === 'Nóng / Đá';
    $ingredients = [];

    foreach ($sourceIngredients as $index => $ingredient) {
        $amount = isset($ingredient['amount']) ? (string)$ingredient['amount'] : '';
        $variantAmounts = recipeSplitHotIcedAmount($amount);

        if (
            $hasHotIcedAmounts
            && $variantAmounts['hot'] === null
            && $variantAmounts['iced'] === null
        ) {
            $variantAmounts['hot'] = $amount;
            $variantAmounts['iced'] = $amount;
        }

        $ingredients[] = [
            'name' => isset($ingredient['name']) ? (string)$ingredient['name'] : '',
            'amount' => $amount,
            'hot_amount' => $variantAmounts['hot'],
            'iced_amount' => $variantAmounts['iced'],
            'unit' => isset($ingredient['unit']) ? (string)$ingredient['unit'] : '',
            'sort_order' => isset($ingredient['sort_order'])
                ? (int)$ingredient['sort_order']
                : $index + 1,
        ];
    }

    return [
        'id' => $recipeCode,
        'name' => isset($sourceRecipe['name']) ? (string)$sourceRecipe['name'] : '',
        'type' => isset($sourceRecipe['type']) ? (string)$sourceRecipe['type'] : '',
        'category' => isset($sourceRecipe['category']) ? (string)$sourceRecipe['category'] : '',
        'instructions' => isset($sourceRecipe['instructions'])
            ? (string)$sourceRecipe['instructions']
            : '',
        'variant_label' => $displayVariant,
        'has_hot_iced_amounts' => $hasHotIcedAmounts,
        'ingredients' => $ingredients,
    ];
}

/**
 * Lấy các món trong hóa đơn rồi gắn công thức từ data/recipes.json.
 *
 * Đơn hàng và sản phẩm vẫn đọc từ MySQL; không cần bảng công thức trong database.
 */
function loadOrderPreparationItems($conn, $orderId)
{
    $orderId = (int)$orderId;
    if ($orderId <= 0) {
        return [];
    }

    $queries = [
        "
            SELECT
                oi.id AS order_item_id,
                oi.product_id,
                oi.qty,
                oi.note,
                p.name AS product_name
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ",
        "
            SELECT
                oi.id AS order_item_id,
                oi.product_id,
                oi.qty,
                '' AS note,
                p.name AS product_name
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
            ORDER BY oi.id
        ",
    ];

    $stmt = null;
    $result = null;

    foreach ($queries as $index => $sql) {
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new RuntimeException('Unable to prepare order item query.');
            }
            $stmt->bind_param('i', $orderId);
            $stmt->execute();
            $result = $stmt->get_result();
            break;
        } catch (Throwable $e) {
            if ($stmt) {
                $stmt->close();
                $stmt = null;
            }

            if ($index === count($queries) - 1) {
                error_log('Unable to load order items for preparation sheet: ' . $e->getMessage());
                return [];
            }
        }
    }

    if (!$result || !$stmt) {
        return [];
    }

    $catalog = loadRecipeCatalog();
    $items = [];

    while ($row = $result->fetch_assoc()) {
        $productName = $row['product_name'] ?? 'Món đã xóa';
        $items[] = [
            'id' => (int)$row['order_item_id'],
            'product_id' => (int)$row['product_id'],
            'product_name' => $productName,
            'qty' => (float)$row['qty'],
            'note' => $row['note'] ?? '',
            'recipe' => findRecipeForProductName($catalog, $productName),
        ];
    }

    $stmt->close();
    return $items;
}
