<?php
function ghe_database(): mysqli {
    if (!extension_loaded('mysqli')) throw new RuntimeException('Máy chủ chưa bật mysqli.');
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $db = mysqli_init();
    $db->options(MYSQLI_OPT_CONNECT_TIMEOUT, 2);
    $db->real_connect(getenv('POS_DB_HOST') ?: 'localhost', getenv('POS_DB_USER') ?: 'root', getenv('POS_DB_PASS') ?: '', getenv('POS_DB_NAME') ?: 'cafe_pos', (int)(getenv('POS_DB_PORT') ?: 3306));
    $db->set_charset('utf8mb4');
    return $db;
}
function ghe_catalog_rows(mysqli $db): array {
    // Read-only, no inclusion of POS config.php (which performs shift-close mutations).
    return $db->query('SELECT p.id, p.name, p.price, p.status, p.category_id, p.sort_order, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY c.name, p.sort_order, p.name')->fetch_all(MYSQLI_ASSOC);
}
function ghe_catalog_sections(array $rows, array $content): array {
    $sections = [];
    $original = require __DIR__ . '/menu-data.php';
    $descriptions = [];
    foreach ($original as $section) foreach ($section['items'] as $item) $descriptions[mb_strtolower(trim($item['name']), 'UTF-8')] = $item;
    foreach ($rows as $row) {
        $meta = $content['products'][(string)$row['id']] ?? [];
        if ((int)$row['status'] !== 1 || !($meta['visible'] ?? true)) continue;
        $key = 'category-' . (int)$row['category_id'];
        if (!isset($sections[$key])) $sections[$key] = ['id'=>$key, 'title'=>$row['category_name'] ?: 'Các món khác', 'eyebrow'=>'Thực đơn tại Ghé', 'theme'=>'coffee', 'wide'=>true, 'items'=>[]];
        $seed = $descriptions[mb_strtolower(trim($row['name']), 'UTF-8')] ?? [];
        $sections[$key]['items'][] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'price'=>(int)$row['price'],
            'detail'=>$meta['description'] ?? ($seed['detail'] ?? ''), 'tags'=>$seed['tags'] ?? [],
            'image'=>$meta['image'] ?? '', 'featured'=>(bool)($meta['featured'] ?? false)];
    }
    return array_values($sections);
}
