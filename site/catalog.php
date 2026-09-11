<?php
require_once __DIR__.'/photo-collection.php';
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
        $photo=ghe_collection_product($row['name'],$content);
        $image=$meta['image']??'';
        if($image==='' && !($meta['image_manual']??false))$image=$photo['image'];
        $sections[$key]['items'][] = ['id'=>(int)$row['id'], 'name'=>$row['name'], 'price'=>(int)$row['price'],
            'detail'=>$meta['description'] ?? ($seed['detail'] ?? ''), 'tags'=>$seed['tags'] ?? [],
            'image'=>$image, 'image_note'=>$image===$photo['image']?$photo['image_note']:'', 'image_note_en'=>$image===$photo['image']?$photo['image_note_en']:'', 'featured'=>(bool)($meta['featured'] ?? false)];
    }
    // Keep the existing category/item order, with snacks after all other groups.
    // Both public languages and their category links use this shared ordering.
    $main = []; $snacks = [];
    foreach ($sections as $section) {
        $name = mb_strtolower(trim(explode('/', $section['title'])[0]), 'UTF-8');
        if (in_array($name, ['ăn nhẹ', 'snacks'], true)) $snacks[] = $section;
        else $main[] = $section;
    }
    return array_merge($main, $snacks);
}
