<?php
require_once __DIR__ . '/content-store.php';
require_once __DIR__ . '/catalog.php';
require_once __DIR__ . '/english.php';
$site = require __DIR__ . '/config.php';
$websiteState = ghe_state_read();
$content = $websiteState[defined('GHE_PREVIEW') && GHE_PREVIEW ? 'draft' : 'published'];
$settings = $content['settings'];
$site = array_merge($site, $settings);
$site['address'] = $settings['street'] . ', ' . $settings['city'] . ', ' . $settings['region'];
$phoneDigits = preg_replace('/\D/', '', $settings['phone']);
$site['telephone'] = '+' . (strpos($phoneDigits,'0') === 0 ? '84' . substr($phoneDigits,1) : $phoneDigits);
$site['directions'] = 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode('Ghé, ' . $site['address']);
$site['hours'] = $site['opens'] . '–' . $site['closes'] . ' hằng ngày';
$catalogAvailable=true;
try { $publicDb=ghe_database(); $menuSections=ghe_catalog_sections(ghe_catalog_rows($publicDb),$content); $publicDb->close(); }
catch (Throwable $e) { $catalogAvailable=false; $menuSections=[]; error_log('Public menu unavailable: '.$e->getMessage()); }
$publicPages = [
    'home' => ['file' => '', 'label' => 'Trang chủ', 'title' => 'Ghé — Cà phê ở 30 Võ Trường Toản, Đà Lạt', 'description' => 'Ghé, quán cà phê tại 30 Võ Trường Toản, Đà Lạt. Khám phá menu cà phê, trà, matcha, bánh và lưu đường đến quán trên Google Maps.'],
    'about' => ['file' => 'gioi-thieu.php', 'label' => 'Về Ghé', 'title' => 'Về Ghé — Một lời hẹn cà phê ở Đà Lạt', 'description' => 'Làm quen với Ghé tại 30 Võ Trường Toản, Đà Lạt: một lời mời gặp nhau bên cà phê, trà, matcha và những món bánh trong menu của quán.'],
    'menu' => ['file' => 'thuc-don.php', 'label' => 'Thực đơn', 'title' => 'Thực đơn Ghé Đà Lạt — Cà phê, trà, matcha và giá món', 'description' => 'Xem menu và giá tham khảo tại Ghé Đà Lạt: cà phê đen, nâu, bạc xỉu, cold brew, trà, matcha, chocolate, bánh và món ăn nhẹ.'],
    'story' => ['file' => 'ghe-uong-gi.php', 'label' => 'Ghé uống gì?', 'title' => 'Ghé uống gì? Gợi ý chọn cà phê, trà và matcha ở Đà Lạt', 'description' => 'Lần đầu đến Ghé ở Đà Lạt nên gọi món gì? Chọn theo gu từ cà phê truyền thống, cold brew chanh sả đến trà nóng, matcha và bánh ăn kèm.'],
    'visit' => ['file' => 'den-ghe.php', 'label' => 'Đến Ghé', 'title' => 'Đường đến Ghé — 30 Võ Trường Toản, Đà Lạt', 'description' => 'Địa chỉ Ghé: 30 Võ Trường Toản, Đà Lạt, Lâm Đồng. Mở Google Maps để chỉ đường, xem đánh giá và liên hệ quán qua số 0705 926 614.'],
];
$publicPages['home']['description'] = mb_substr($settings['home_intro'],0,180,'UTF-8');
$publicPages['gallery']=['file'=>'khong-gian.php','label'=>'Không gian','title'=>'Không gian Ghé Đà Lạt — Sân vườn, góc ngồi và món uống','description'=>'Xem ảnh thật của Ghé ở 30 Võ Trường Toản, Đà Lạt: không gian sân vườn, góc ngồi trong nhà, những cuộc hẹn và các món uống tại quán.'];
$publicPages['about']['description'] = mb_substr(preg_replace('/\s+/u',' ',$settings['about_body']),0,180,'UTF-8');
$publicPages['visit']['description'] = 'Ghé tại ' . $site['address'] . '. Mở cửa ' . $site['hours'] . '. Liên hệ ' . $site['phone'] . ' hoặc xem chỉ đường trên Google Maps.';
$publishedPosts = array_filter($content['posts'],function($post){ return $post['enabled']; });
if ($publishedPosts) $publicPages['journal'] = ['file'=>'chuyen-o-ghe.php','label'=>'Chuyện ở Ghé','title'=>'Chuyện ở Ghé — Cà phê và những cuộc hẹn Đà Lạt','description'=>'Những câu chuyện, thông tin và cập nhật từ quán Ghé ở Đà Lạt.'];
function ghe_h($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function ghe_url(string $path = ''): string { global $site; return $site['url'] . '/' . ltrim($path, '/'); }
function ghe_link(string $path = '', bool $localize = true): string {
    global $site;
    if ($localize && ghe_is_english()) {
        $key=array_search($path,ghe_english_routes(),true);
        if ($key!==false) $path=ghe_english_path($key);
    }
    if (defined('GHE_PREVIEW') && GHE_PREVIEW) {
        $parts=explode('?', $path,2); $file=$parts[0] ?: 'home.php';
        if (in_array($file,['home.php','gioi-thieu.php','thuc-don.php','ghe-uong-gi.php','den-ghe.php','khong-gian.php','chuyen-o-ghe.php','bai-viet.php','en.php'],true)) $path='website-preview.php?page=' . rawurlencode($file) . (isset($parts[1])?'&'.$parts[1]:'');
    }
    return rtrim(parse_url($site['url'], PHP_URL_PATH) ?: '', '/') . '/' . ltrim($path, '/');
}
function ghe_price(int $price): string { return ghe_is_english() ? number_format($price,0,'.',',').' VND' : number_format($price, 0, ',', '.') . 'đ'; }
function ghe_item(string $name): ?array {
    global $menuSections;
    foreach ($menuSections as $section) foreach ($section['items'] as $item) if ($item['name'] === $name) return $item;
    return null;
}
function ghe_item_price(string $name): string { $item=ghe_item($name); return $item ? ghe_price($item['price']) : 'xem thực đơn'; }
function ghe_faqs(): array {
    if (ghe_is_english()) return ghe_english_faqs();
    global $site;
    return [
        ['Ghé ở đâu tại Đà Lạt?', 'Ghé ở ' . $site['address'] . '. Bạn có thể mở Google Maps từ trang Đến Ghé để xem vị trí và chỉ đường.'],
        ['Ghé có những món gì ngoài cà phê?', 'Menu có trà, matcha, chocolate, kombucha, nước ép, sữa chua, bánh croissant, cookie và một số món ăn nhẹ.'],
        ['Món ở Ghé giá bao nhiêu?', 'Bạn có thể xem giá từng món trên trang Thực đơn. Giá được cập nhật từ hệ thống bán hàng của quán.'],
        ['Ghé mở cửa lúc mấy giờ?', 'Ghé mở cửa ' . $site['hours'] . '. ' . $site['hours_note']],
        ['Không gian Ghé phù hợp với hoạt động nào?', ghe_facilities_sentence()],
    ];
}
function ghe_schema(string $key): array {
    global $site, $publicPages, $menuSections;
    $page = $publicPages[$key]; $url = ghe_url($page['file']);
    $business = ['@type' => 'CafeOrCoffeeShop', '@id' => ghe_url('#ghe'), 'name' => $site['name'], 'url' => ghe_url(),
        'description' => $site['home_intro'], 'telephone' => $site['telephone'],
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => $site['street'], 'addressLocality' => $site['city'], 'addressRegion' => $site['region'], 'addressCountry' => 'VN'],
        'openingHoursSpecification' => [['@type'=>'OpeningHoursSpecification','dayOfWeek'=>['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'],'opens'=>$site['opens'],'closes'=>$site['closes']]],
        'hasMap' => $site['maps'], 'sameAs' => [$site['maps']], 'hasMenu' => ghe_url($publicPages['menu']['file'])];
    global $content;
    $business['amenityFeature']=[];
    $facilityLabels=ghe_is_english()?ghe_english_facility_labels():ghe_facility_labels();
    $facilityDetails=ghe_is_english()?ghe_english_facility_details($content):$content['facility_details'];
    foreach($facilityLabels as $facility=>$label) if (($content['facilities'][$facility]??'unknown')!=='unknown') $business['amenityFeature'][]=['@type'=>'LocationFeatureSpecification','name'=>$label,'value'=>$content['facilities'][$facility]==='yes','description'=>$content['facilities'][$facility]==='yes'?($facilityDetails[$facility]??''):''];
    if ($site['street']==='30 Võ Trường Toản' && $site['city']==='Đà Lạt') $business['geo']=['@type'=>'GeoCoordinates','latitude'=>$site['latitude'],'longitude'=>$site['longitude']];
    if (!empty($content['media'][$site['hero_image']])) $business['image']=ghe_url('ghe-image.php?id='.$site['hero_image']);
    $webpage = ['@type' => $key === 'about' ? 'AboutPage' : ($key === 'visit' ? 'ContactPage' : 'WebPage'),
        '@id' => $url . '#page', 'url' => $url, 'name' => $page['title'], 'description' => $page['description'],
        'inLanguage' => ghe_t('vi-VN','en'), 'isPartOf' => ['@id' => ghe_url('#website')], 'about' => ['@id' => ghe_url('#ghe')]];
    $graph = [$business, ['@type' => 'WebSite', '@id' => ghe_url('#website'), 'url' => ghe_url(), 'name' => 'Ghé — Cà phê Đà Lạt', 'publisher' => ['@id' => ghe_url('#ghe')]], $webpage];
    if($key==='gallery') {
        $graph[2]['@type']='ImageGallery';
        $graph[2]['hasPart']=[];
        foreach($content['media'] as $id=>$photo)if($photo['gallery'])$graph[2]['hasPart'][]=['@type'=>'ImageObject','contentUrl'=>ghe_url('ghe-image.php?id='.$id),'thumbnailUrl'=>ghe_url('ghe-image.php?id='.$id.'&size=thumb'),'caption'=>ghe_is_english()?($photo['alt_en']??'Ghé café in Da Lat'):$photo['alt']];
    }
    if ($key !== 'home') $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => ghe_t('Trang chủ','Home'), 'item' => ghe_url($publicPages['home']['file'])],
        ['@type' => 'ListItem', 'position' => 2, 'name' => $page['label'], 'item' => $url]]];
    if ($key === 'menu') {
        $sections = [];
        foreach ($menuSections as $section) {
            $items = [];
            foreach ($section['items'] as $item) $items[] = ['@type' => 'MenuItem', 'name' => $item['name'], 'description' => $item['detail'],
                'offers' => ['@type' => 'Offer', 'price' => $item['price'], 'priceCurrency' => 'VND']];
            $sections[] = ['@type' => 'MenuSection', 'name' => $section['title'], 'hasMenuItem' => $items];
        }
        $graph[] = ['@type' => 'Menu', '@id' => $url . '#menu', 'url' => $url, 'name' => ghe_t('Thực đơn Ghé','Ghé menu'), 'inLanguage'=>ghe_t('vi-VN','en'), 'hasMenuSection' => $sections];
    }
    if ($key === 'visit') $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(function ($faq) {
        return ['@type' => 'Question', 'name' => $faq[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]]];
    }, ghe_faqs())];
    if ($key==='article') {
        global $articleContent;
        $articleSchema=['@type'=>'BlogPosting','headline'=>$articleContent['title'],'description'=>$articleContent['excerpt'],'articleBody'=>$articleContent['body'],'mainEntityOfPage'=>['@id'=>$url.'#page'],'author'=>['@id'=>ghe_url('#ghe')],'publisher'=>['@id'=>ghe_url('#ghe')],'inLanguage'=>'vi-VN'];
        if($articleContent['published_at'])$articleSchema['datePublished']=$articleContent['published_at'];
        if(!empty($articleContent['updated_at']))$articleSchema['dateModified']=$articleContent['updated_at'];
        $graph[]=$articleSchema;
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}
require_once __DIR__ . '/public-components.php';
if (ghe_is_english()) ghe_english_apply();
