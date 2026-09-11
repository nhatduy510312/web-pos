<?php
require_once __DIR__ . '/site/content-store.php';
require_once __DIR__ . '/site/media.php';
$preview = ($_GET['preview'] ?? '') === '1';
if ($preview) require __DIR__ . '/site/admin-auth.php';
try {
    $id = is_string($_GET['id'] ?? null) ? $_GET['id'] : '';
    $size = ($_GET['size'] ?? '') === 'thumb' ? 'thumb' : 'full';
    $content=ghe_state_read()[$preview?'draft':'published'];
    if (!isset($content['media'][$id])) throw new RuntimeException('Not found');
    $used=$preview || $content['media'][$id]['gallery'] || $content['settings']['hero_image']===$id;
    foreach ($content['products'] as $meta) if (($meta['visible']??true) && ($meta['image']??'')===$id) $used=true;
    // A collection photo may be assigned by the current POS name before its first admin save.
    if(!$used && ghe_collection_file($id,$size)!==null) {
        require_once __DIR__.'/site/catalog.php';
        $imageDb=ghe_database();
        try { foreach(ghe_catalog_sections(ghe_catalog_rows($imageDb),$content) as $section)foreach($section['items'] as $item)if($item['image']===$id)$used=true; }
        finally { $imageDb->close(); }
    }
    if (!$used) throw new RuntimeException('Not found');
    $file=ghe_media_file($id,$size);
    if(!is_file($file))$file=ghe_collection_file($id,$size)??$file;
    if (!is_file($file)) throw new RuntimeException('Not found');
    header('Content-Type: image/webp'); header('X-Content-Type-Options: nosniff');
    header('Cache-Control: ' . ($preview?'private, no-store':'public, max-age=300'));
    header('Content-Length: ' . filesize($file)); readfile($file);
} catch (Throwable $e) { http_response_code(404); header('Cache-Control: no-store'); }
