<?php $pageKey = 'menu'; require __DIR__ . '/site/header.php'; ?>
<section class="page-intro wrap"><p class="eyebrow">THỰC ĐƠN / GHÉ ĐÀ LẠT</p><h1>Chọn một món.<br><em>Ở lại một câu chuyện.</em></h1><p class="lead"><?= ghe_h($site['menu_intro']) ?></p><p class="fine-print">Giá tham khảo theo menu của quán, tính bằng đồng Việt Nam. Vui lòng xác nhận giá và tình trạng món khi gọi. Nếu có dị ứng thực phẩm, hãy trao đổi với nhân viên về thành phần.</p></section>
<div class="wrap menu-tools" data-menu-tools hidden><label for="public-menu-search">Tìm món bạn thích</label><input id="public-menu-search" type="search" placeholder="Thử “bạc xỉu”, “matcha”, “trà”…" autocomplete="off"><p class="fine-print" id="menu-result" role="status" aria-live="polite"></p></div>
<nav class="wrap category-nav" aria-label="Nhóm món"><?php foreach ($menuSections as $section): ?><a href="#<?= ghe_h($section['id']) ?>"><?= ghe_h(explode(' / ', $section['title'])[0]) ?></a><?php endforeach; ?></nav>
<div class="wrap menu-list">
<?php if(!$menuSections): ?><p class="empty-results"><?= $catalogAvailable?'Thực đơn đang được cập nhật.':'Chưa tải được thực đơn lúc này.' ?> Bạn vui lòng gọi <a href="tel:<?= ghe_h($site['telephone']) ?>"><?= ghe_h($site['phone']) ?></a> để hỏi món và giá.</p><?php endif; ?>
<?php foreach ($menuSections as $index => $section): ?>
<section class="menu-section" id="<?= ghe_h($section['id']) ?>" data-menu-section><div class="menu-section-heading"><p class="eyebrow"><?= sprintf('%02d', $index + 1) ?> / <?= ghe_h($section['eyebrow']) ?></p><h2><?= ghe_h($section['title']) ?></h2></div><div class="menu-items">
<?php foreach ($section['items'] as $item): ?><article class="menu-item" data-menu-item data-search="<?= ghe_h($section['title'] . ' ' . $item['name'] . ' ' . $item['detail']) ?>"><?php if(!empty($content['media'][$item['image']])) ghe_menu_photo($item,'menu-photo'); ?><div class="menu-item-top"><h3><?= ghe_h($item['name']) ?></h3><span><?= ghe_price($item['price']) ?></span></div><p><?= ghe_h($item['detail']) ?></p><?php if ($item['tags']): ?><div class="tags"><?php foreach ($item['tags'] as $tag): ?><span><?= ghe_h($tag) ?></span><?php endforeach; ?></div><?php endif; ?></article><?php endforeach; ?>
</div></section>
<?php endforeach; ?>
<p class="empty-results" id="menu-empty" hidden>Chưa tìm thấy món phù hợp. Bạn thử tên ngắn hơn hoặc xóa nội dung tìm kiếm nhé.</p>
</div>
<?php require __DIR__ . '/site/footer.php'; ?>
