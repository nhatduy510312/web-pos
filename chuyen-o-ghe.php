<?php
require_once __DIR__.'/site/bootstrap.php';
if(!$publishedPosts) { http_response_code(404); header('X-Robots-Tag: noindex'); exit('Chưa có bài viết công khai.'); }
$pageKey='journal'; require __DIR__.'/site/header.php';
?>
<section class="wrap page-intro"><p class="eyebrow">CHUYỆN Ở GHÉ</p><h1>Những câu chuyện,<br><em>từ quán của chúng mình.</em></h1></section>
<section class="wrap section post-grid"><?php foreach(array_reverse($publishedPosts,true) as $entry): ?><article><h2><a href="<?= ghe_h(ghe_link('bai-viet.php?slug='.rawurlencode($entry['slug']))) ?>"><?= ghe_h($entry['title']) ?></a></h2><p><?= ghe_h($entry['excerpt']) ?></p><a class="text-link" href="<?= ghe_h(ghe_link('bai-viet.php?slug='.rawurlencode($entry['slug']))) ?>">Đọc bài →</a></article><?php endforeach; ?></section>
<?php require __DIR__.'/site/footer.php'; ?>
