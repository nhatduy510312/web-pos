<?php
require_once __DIR__ . '/bootstrap.php';
$page = $publicPages[$pageKey];
header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="<?= ghe_t('vi','en') ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#f5f0e6">
<title><?= ghe_h($page['title']) ?></title>
<meta name="description" content="<?= ghe_h($page['description']) ?>">
<meta name="robots" content="<?= defined('GHE_PREVIEW') && GHE_PREVIEW ? 'noindex, nofollow' : 'index, follow, max-image-preview:large' ?>">
<link rel="canonical" href="<?= ghe_h(ghe_url($page['file'])) ?>">
<?php $languageRoutes=ghe_english_routes(); if(isset($languageRoutes[$pageKey])): ?>
<link rel="alternate" hreflang="vi" href="<?= ghe_h(ghe_url($languageRoutes[$pageKey])) ?>">
<link rel="alternate" hreflang="en" href="<?= ghe_h(ghe_url(ghe_english_path($pageKey))) ?>">
<link rel="alternate" hreflang="x-default" href="<?= ghe_h(ghe_url($languageRoutes[$pageKey])) ?>">
<?php endif; ?>
<meta property="og:locale" content="<?= ghe_t('vi_VN','en_US') ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="<?= ghe_t('Ghé — Cà phê Đà Lạt','Ghé — Da Lat Café') ?>">
<meta property="og:title" content="<?= ghe_h($page['title']) ?>">
<meta property="og:description" content="<?= ghe_h($page['description']) ?>">
<meta property="og:url" content="<?= ghe_h(ghe_url($page['file'])) ?>">
<?php $socialPhoto=$content['media'][$site['hero_image']]??null; ?>
<meta property="og:image" content="<?= ghe_h(ghe_url($socialPhoto?'ghe-image.php?id='.$site['hero_image']:'assets/ghe-social.png')) ?>">
<meta property="og:image:width" content="<?= $socialPhoto?(int)$socialPhoto['full']['width']:1200 ?>"><meta property="og:image:height" content="<?= $socialPhoto?(int)$socialPhoto['full']['height']:630 ?>">
<meta property="og:image:alt" content="<?= ghe_h(ghe_is_english() ? (trim($socialPhoto['alt_en']??'') ?: 'Ghé — Da Lat Café') : ($socialPhoto?$socialPhoto['alt']:'Ghé — Cà phê Đà Lạt')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/svg+xml" href="<?= ghe_h(ghe_link('favicon.svg')) ?>">
<link rel="stylesheet" href="<?= ghe_h(ghe_link('assets/public.css?v=home-refined-1')) ?>">
<?php if($pageKey==='home'): ?><link rel="stylesheet" href="<?= ghe_h(ghe_link('assets/home.css?v=1')) ?>"><?php endif; ?>
<script type="application/ld+json"><?= json_encode(ghe_schema($pageKey), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= ghe_h(ghe_link('assets/public.js?v=photos-1')) ?>" defer></script>
</head>
<body<?= $pageKey==='home'?' class="public-home"':'' ?>>
<?php if(defined('GHE_PREVIEW') && GHE_PREVIEW): ?><div class="preview-banner">BẢN XEM TRƯỚC — Khách chưa thấy thay đổi này. <a href="website-admin.php">Về quản trị</a></div><?php endif; ?>
<a class="skip-link" href="#main"><?= ghe_t('Đến nội dung chính','Skip to content') ?></a>
<?php if($pageKey!=='home'): ?><div class="topline"><?= ghe_h($site['hours']) ?><span><?= ghe_h($site['street']) ?></span></div><?php endif; ?>
<header class="header wrap">
  <a class="wordmark" href="<?= ghe_h(ghe_link()) ?>" aria-label="<?= ghe_t('Ghé — Trang chủ','Ghé — Home') ?>">ghé<span><?= ghe_t('cà phê & những cuộc hẹn','coffee & good company') ?></span></a>
  <nav class="desktop-nav" aria-label="<?= ghe_t('Điều hướng chính','Main navigation') ?>">
  <?php foreach ($publicPages as $key => $entry): if ($key === 'home' || ($entry['navigation']??true)===false) continue; ?>
    <a href="<?= ghe_h(ghe_link($entry['file'])) ?>"<?= $key === $pageKey ? ' aria-current="page"' : '' ?>><?= ghe_h($entry['label']) ?></a>
  <?php endforeach; ?>
  </nav>
  <details class="mobile-nav"><summary>Menu <span aria-hidden="true">☰</span></summary><nav aria-label="<?= ghe_t('Điều hướng di động','Mobile navigation') ?>">
  <?php foreach ($publicPages as $key => $entry): if(($entry['navigation']??true)===false)continue; ?>
    <a href="<?= ghe_h(ghe_link($entry['file'])) ?>"<?= $key === $pageKey ? ' aria-current="page"' : '' ?>><?= ghe_h($entry['label']) ?></a>
  <?php endforeach; ?>
  </nav></details>
  <nav class="language-switch" aria-label="<?= ghe_t('Ngôn ngữ','Language') ?>">
    <a href="<?= ghe_h(ghe_link($languageRoutes[$pageKey]??$page['file'],false)) ?>" lang="vi" hreflang="vi"<?= !ghe_is_english()?' aria-current="true"':'' ?>>VI<span class="sr-only"> — Tiếng Việt</span></a>
    <a href="<?= ghe_h(ghe_link(ghe_english_path(isset($languageRoutes[$pageKey])?$pageKey:'home'),false)) ?>" lang="en" hreflang="en"<?= ghe_is_english()?' aria-current="true"':'' ?>>EN<span class="sr-only"> — English</span></a>
  </nav>
</header>
<main id="main">
<?php if ($pageKey !== 'home'): ?><nav class="breadcrumb wrap" aria-label="<?= ghe_t('Đường dẫn','Breadcrumb') ?>"><a href="<?= ghe_h(ghe_link()) ?>"><?= ghe_t('Trang chủ','Home') ?></a><span aria-hidden="true"> / </span><span aria-current="page"><?= ghe_h($page['label']) ?></span></nav><?php endif; ?>
