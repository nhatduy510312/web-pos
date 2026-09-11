</main>
<?php if($pageKey==='home'): ?>
<?php require __DIR__.'/home-footer.php'; ?>
<?php else: ?>
<section class="invitation"><div class="wrap invitation-inner"><div><p class="eyebrow"><?= ghe_t('HẸN BẠN Ở GHÉ','SEE YOU AT GHÉ') ?></p><h2><?= ghe_t('Có dịp đến Đà Lạt,<br>ghé một chút nhé.','In Da Lat?<br>Stop by for a while.') ?></h2></div><a class="button light" href="<?= ghe_h($site['maps']) ?>" target="_blank" rel="noopener noreferrer"><?= ghe_t('Mở Google Maps ','Open Google Maps ') ?><span aria-hidden="true">↗</span></a></div></section>
<footer class="footer wrap">
  <div><a class="wordmark" href="<?= ghe_h(ghe_link()) ?>">ghé</a><p><?= ghe_t('Cà phê, trà & những cuộc hẹn ở Đà Lạt.','Coffee, tea & good company in Da Lat.') ?></p></div>
  <div><p class="eyebrow"><?= ghe_t('TÌM GHÉ','FIND US') ?></p><address><?= ghe_h($site['address']) ?><br><a href="tel:<?= ghe_h($site['telephone']) ?>"><?= ghe_h($site['phone']) ?></a></address><p><?= ghe_h($site['hours']) ?></p><a class="text-link" href="<?= ghe_h($site['maps']) ?>" target="_blank" rel="noopener noreferrer"><?= ghe_t('Xem Ghé trên Maps ↗','Find Ghé on Maps ↗') ?></a></div>
  <div><p class="eyebrow"><?= ghe_t('ĐI MỘT VÒNG','EXPLORE') ?></p><a href="<?= ghe_h(ghe_link('gioi-thieu.php')) ?>"><?= ghe_t('Về Ghé','About Ghé') ?></a><a href="<?= ghe_h(ghe_link('thuc-don.php')) ?>"><?= ghe_t('Thực đơn','Menu') ?></a><a href="<?= ghe_h(ghe_link('ghe-uong-gi.php')) ?>"><?= ghe_t('Ghé uống gì?','What to order') ?></a><a href="<?= ghe_h(ghe_link('khong-gian.php')) ?>"><?= ghe_t('Không gian & ảnh thật','Spaces & photos') ?></a></div>
  <div class="footer-bottom"><span>© <?= date('Y') ?> Ghé · Đà Lạt</span><a href="<?= ghe_h(ghe_link('index.php')) ?>" rel="nofollow"><?= ghe_t('Dành cho nhân viên ↗','Staff access ↗') ?></a></div>
</footer>
<nav class="mobile-contact" aria-label="<?= ghe_t('Liên hệ nhanh','Quick contact') ?>"><a href="tel:<?= ghe_h($site['telephone']) ?>"><?= ghe_t('Gọi Ghé','Call Ghé') ?></a><a href="<?= ghe_h($site['directions']) ?>" target="_blank" rel="noopener noreferrer"><?= ghe_t('Chỉ đường ↗','Directions ↗') ?></a></nav>
<?php endif; ?>
</body>
</html>
