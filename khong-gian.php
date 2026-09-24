<?php $pageKey='gallery'; require __DIR__.'/site/header.php'; ?>
<section class="page-intro wrap"><p class="eyebrow">KHÔNG GIAN Ở GHÉ</p><h1>Ngoài vườn xanh.<br><em>Trong nhà thoáng.</em></h1><p class="lead">Tìm một góc hợp với nhịp của bạn — để tập trung, gặp gỡ hoặc ngồi yên một chút.</p><div class="actions"><a class="button" href="<?= ghe_h(ghe_link('den-ghe.php')) ?>">Đường đến Ghé ↗</a><a class="text-link" href="<?= ghe_h(ghe_link('thuc-don.php')) ?>">Xem thực đơn →</a></div></section>
<?php ghe_gallery(true); require __DIR__.'/site/footer.php'; ?>
