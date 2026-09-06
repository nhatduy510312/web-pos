<?php
require __DIR__ . '/site/bootstrap.php';
header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($publicPages as $entry): ?>
  <url><loc><?= ghe_h(ghe_url($entry['file'])) ?></loc></url>
<?php endforeach; ?>
<?php foreach(ghe_english_routes() as $key=>$viPath): ?>
  <url><loc><?= ghe_h(ghe_url(ghe_english_path($key))) ?></loc></url>
<?php endforeach; ?>
<?php foreach($publishedPosts as $entry): ?>
  <url><loc><?= ghe_h(ghe_url('bai-viet.php?slug='.rawurlencode($entry['slug']))) ?></loc></url>
<?php endforeach; ?>
</urlset>
