<?php // A focused, image-led introduction to Ghé. Visit details appear in home-footer.php. ?>
<section class="hero wrap">
  <div class="hero-copy">
    <p class="eyebrow"><?= ghe_t('GHÉ · MỘT KHOẢNG XANH Ở ĐÀ LẠT','GHÉ · A GREEN PAUSE IN DA LAT') ?></p>
    <h1><?= nl2br(ghe_h($site['home_title'])) ?></h1>
    <p class="home-lead"><?= ghe_h(trim($site['home_lead']??'') ?: (ghe_is_english()?ghe_english_defaults()['home_lead']:ghe_content_defaults()['settings']['home_lead'])) ?></p>
    <div class="actions">
      <a class="button" href="#spaces"><?= ghe_t('Tìm một góc ở Ghé','Find your corner') ?> <span aria-hidden="true">↓</span></a>
      <a class="text-link" href="<?= ghe_h(ghe_link('thuc-don.php')) ?>"><?= ghe_t('Xem thực đơn','Browse the menu') ?> →</a>
    </div>
  </div>
  <?php if(!empty($content['media'][$site['hero_image']])): ?>
    <div class="hero-photo"><?php ghe_photo($site['hero_image'],'',true); ?><p><?= ghe_t('Sân vườn tại Ghé','The garden at Ghé') ?></p></div>
  <?php else: ?>
    <div class="home-word-art" aria-hidden="true">ghé<span><?= ghe_t('chậm lại một chút.','slow down for a while.') ?></span></div>
  <?php endif; ?>
</section>

<section class="home-statement" aria-labelledby="home-statement-heading">
  <div class="wrap">
    <p class="eyebrow"><?= ghe_t('GHÉ, THEO CÁCH CỦA BẠN','GHÉ, YOUR WAY') ?></p>
    <h2 id="home-statement-heading"><?= ghe_t('Đến để tập trung. Ở lại để sẻ chia.<br><em>Hay chỉ đơn giản là thở chậm hơn.</em>','Come to focus. Stay to connect.<br><em>Or simply breathe a little slower.</em>') ?></h2>
  </div>
</section>

<section class="home-spaces section" id="spaces" aria-labelledby="home-spaces-heading">
  <div class="wrap">
    <div class="section-heading">
      <div><p class="eyebrow"><?= ghe_t('KHÔNG GIAN Ở GHÉ','SPACES AT GHÉ') ?></p><h2 id="home-spaces-heading"><?= ghe_t('Mỗi ngày, một góc vừa ý.','A place for your kind of day.') ?></h2></div>
      <a class="text-link" href="<?= ghe_h(ghe_link('khong-gian.php')) ?>"><?= ghe_t('Xem thêm không gian','See more of Ghé') ?> →</a>
    </div>
    <?php ghe_home_spaces(); ?>
  </div>
</section>

<?php $featured=ghe_featured_items(); if($featured): ?>
<section class="section home-drinks wrap" id="menu"><div class="section-heading"><div><p class="eyebrow"><?= ghe_t('MỘT CHÚT TỪ THỰC ĐƠN','A LITTLE FROM THE MENU') ?></p><h2><?= ghe_t('Chọn một món. Rồi thong thả.','Choose a drink. Take your time.') ?></h2></div><a class="text-link" href="<?= ghe_h(ghe_link('thuc-don.php')) ?>"><?= ghe_t('Xem thực đơn','Explore the menu') ?> →</a></div><div class="drink-grid">
<?php foreach($featured as $drink): ?><article class="drink-card"><?php if(!empty($content['media'][$drink['image']])): ghe_menu_photo($drink,'drink-photo');else: ?><div class="drink-placeholder" aria-hidden="true"><span>ghé</span><small><?= ghe_t('MÓN TẠI QUÁN','ON OUR MENU') ?></small></div><?php endif; ?><div class="drink-title"><h3><?= ghe_h($drink['name']) ?></h3><span><?= ghe_price($drink['price']) ?></span></div><p><?= ghe_h($drink['detail']) ?></p></article><?php endforeach; ?>
</div></section>
<?php else: ?>
<section class="home-menu-note wrap" id="menu"><p class="eyebrow"><?= ghe_t('MỘT CHÚT TỪ THỰC ĐƠN','A LITTLE FROM THE MENU') ?></p><div><h2><?= ghe_t('Cà phê, trà<br>và một chút bánh.','Coffee, tea<br>and something baked.') ?></h2><div><p><?= ghe_t('Chọn một món vừa ý rồi thong thả tận hưởng khoảng thời gian của bạn ở Ghé.','Choose something you like, then take your time and enjoy your moment at Ghé.') ?></p><a class="button outline" href="<?= ghe_h(ghe_link('thuc-don.php')) ?>"><?= ghe_t('Xem thực đơn','Explore the menu') ?> →</a></div></div></section>
<?php endif; ?>

<section class="home-practical"><div class="wrap"><p><?= ghe_t('Một chỗ ngồi thoải mái, Wi-Fi và ổ điện — để bạn yên tâm dành thời gian cho điều mình đang làm.','Comfortable seating, Wi-Fi and power outlets — so you can give your attention to what matters.') ?></p><a class="text-link" href="<?= ghe_h(ghe_link('gioi-thieu.php')) ?>"><?= ghe_t('Biết thêm về Ghé','A little more about Ghé') ?> →</a></div></section>
