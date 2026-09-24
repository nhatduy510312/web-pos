<?php
// Server-rendered English pages: no external translation service or browser language redirect.
function ghe_is_english(): bool { return defined('GHE_ENGLISH') && GHE_ENGLISH; }
function ghe_t(string $vi, string $en): string { return ghe_is_english() ? $en : $vi; }
function ghe_english_routes(): array {
    return ['home'=>'', 'about'=>'gioi-thieu.php', 'menu'=>'thuc-don.php', 'story'=>'ghe-uong-gi.php', 'visit'=>'den-ghe.php', 'gallery'=>'khong-gian.php'];
}
function ghe_english_path(string $key): string { return 'en.php' . ($key === 'home' ? '' : '?view=' . rawurlencode($key)); }
function ghe_english_defaults(): array {
    return [
        'home_title'=>"Stop by for a while.\nSlow down for a while.",
        'home_lead'=>'Between a green garden and light-filled indoor corners, Ghé is a calm place to study, work and connect — or simply give yourself a slower moment.',
        'home_intro'=>'Ghé is a garden café with airy, thoughtful indoor spaces at 30 Vo Truong Toan, Da Lat. A place to study, work, meet and slow down for a while.',
        'about_title'=>'A place for your own pace.',
        'about_body'=>"At Ghé, choose a seat beneath the trees or a light-filled corner indoors. The space is airy, warm and quiet enough for everyone to find their own pace.\n\nCome to study, work, meet and connect. Or come with no particular plan — order something you like and slow down for a while.\n\nReliable Wi-Fi and a power outlet at every table make longer study and work sessions easier. Ghé is open daily from 07:00 to 18:00.",
        'menu_intro'=>'A coffee, a tea or something small from the bakery — just enough for your time at Ghé.',
        'visit_note'=>'For group visits or specific seating needs, please contact us before coming. Street parking is subject to availability and local signs; please keep entrances clear.',
        'hours_note'=>'Opening times are shown in local Vietnam time (UTC+7).',
    ];
}
function ghe_english_facility_labels(): array {
    return ['garden'=>'Garden seating','indoor'=>'Indoor seating','quiet'=>'A quiet atmosphere','work'=>'Suitable for working','study'=>'Suitable for studying','chat'=>'Space to catch up with friends','wifi'=>'Wi-Fi','sockets'=>'Power outlets','parking'=>'Parking'];
}
function ghe_english_facility_details(array $data): array {
    $defaults=ghe_content_defaults()['facility_details'];
    $translations=['wifi'=>'Wi-Fi 6 with a 1 Gbps internet connection. Actual speeds vary by device and usage.','sockets'=>'A power outlet at every table.','parking'=>'Room for cars and motorbikes along the street; not a private car park.'];
    $details=[];
    foreach (ghe_english_facility_labels() as $key=>$label) {
        $details[$key]=trim($data['facility_details_en'][$key]??'');
        // Do not retain old technical claims when the Vietnamese details change.
        if ($details[$key]==='' && isset($defaults[$key]) && ($data['facility_details'][$key]??'')===$defaults[$key]) $details[$key]=$translations[$key];
    }
    return $details;
}
function ghe_english_menu_dictionary(): array {
    static $dictionary=null;
    if ($dictionary===null) {
        $dictionary=[];
        foreach (require __DIR__.'/menu-en.php' as $vi=>$entry) $dictionary[mb_strtolower(trim($vi),'UTF-8')]=$entry;
    }
    return $dictionary;
}
function ghe_english_menu(array $sections, array $data): array {
    $dictionary=ghe_english_menu_dictionary();
    $categories=['Phê / Coffee'=>'Coffee','Cà phê'=>'Coffee','Phê'=>'Coffee','Trà / Tea'=>'Tea','Trà'=>'Tea','Matcha'=>'Matcha','Choco'=>'Chocolate','Kombucha'=>'Kombucha','Nước ép / Juice'=>'Juice','Nước ép'=>'Juice','Sữa chua / Yogurt'=>'Yogurt','Sữa chua'=>'Yogurt','Ăn nhẹ / Snacks'=>'Snacks','Ăn nhẹ'=>'Snacks','Croissant & Cookie'=>'Croissants & cookies','Bánh'=>'Bakery'];
    $categories=array_combine(array_map(function($key){return mb_strtolower($key,'UTF-8');},array_keys($categories)),array_values($categories));
    foreach ($sections as &$section) {
        $section['title']=$categories[mb_strtolower(trim($section['title']),'UTF-8')] ?? 'More from the menu';
        $section['eyebrow']='ON THE MENU AT GHÉ';
        foreach ($section['items'] as &$item) {
            $meta=$data['products'][(string)$item['id']]??[];
            $translation=$dictionary[mb_strtolower(trim($item['name']),'UTF-8')]??null;
            $item['name_vi']=$item['name'];
            $item['name']=trim($meta['name_en']??'') ?: ($translation[0]??$item['name']);
            $item['detail']=trim($meta['description_en']??'') ?: ($translation && $item['detail']===$translation[1] ? $translation[2] : 'Please ask our team about this item and its ingredients.');
            $item['tags']=array_map(function($tag){return ['Nóng'=>'Hot','Đá'=>'Iced','Chỉ đá'=>'Iced only'][$tag]??$tag;},$item['tags']);
        }
        unset($item);
    }
    unset($section);
    return $sections;
}
function ghe_english_apply(): void {
    global $site,$content,$menuSections,$publicPages;
    foreach (ghe_english_defaults() as $key=>$fallback) $site[$key]=trim($content['settings_en'][$key]??'') ?: $fallback;
    $site['hours']=$site['opens'].'–'.$site['closes'].' daily';
    $menuSections=ghe_english_menu($menuSections,$content);
    $publicPages=[
        'home'=>['label'=>'Home','title'=>'Ghé Café in Da Lat — Coffee, Work & Catch-ups','description'=>$site['home_intro']],
        'about'=>['label'=>'About Ghé','title'=>'About Ghé — A Green Pause in Da Lat','description'=>'Meet Ghé at '.$site['address'].': a green garden, airy indoor corners and a place to move at your own pace.','navigation'=>false],
        'menu'=>['label'=>'Menu','title'=>'Ghé Da Lat Menu — Coffee, Tea & Prices in VND','description'=>'Browse the English menu at Ghé café in Da Lat. Coffee, tea, matcha and snacks, with prices in Vietnamese dong and Vietnamese names to help you order.'],
        'story'=>['label'=>'What to order','title'=>'What to Order at Ghé Café in Da Lat','description'=>'Find a drink for your coffee break at Ghé. Explore the current coffee, tea and snack selection with English descriptions and prices.','navigation'=>false],
        'visit'=>['label'=>'Visit us','title'=>'Visit Ghé — '.$site['street'].', Da Lat','description'=>'Find Ghé at '.$site['address'].'. Open '.$site['hours'].'. Directions, contact details, seating, Wi-Fi and parking information.'],
    ];
    foreach ($publicPages as $key=>&$page) $page['file']=ghe_english_path($key);
    $publicPages['gallery']=['file'=>ghe_english_path('gallery'),'label'=>'Gallery','title'=>'Ghé Da Lat Photo Gallery — Garden, Indoor Spaces & Drinks','description'=>'Explore real photos of Ghé café in Da Lat: garden seating, indoor corners, coffee breaks and drinks. Find your favourite spot before your visit.'];
    unset($page);
}
function ghe_english_facilities(): void {
    global $content;
    $details=ghe_english_facility_details($content);
    echo '<ul class="facility-list">';
    foreach(ghe_english_facility_labels() as $key=>$label) {
        $value=$content['facilities'][$key]??'unknown';
        if($value==='unknown')continue;
        echo '<li>'.ghe_h(($value==='yes'?'✓ ':'Not available: ').$label);
        if($value==='yes' && $details[$key]!=='')echo '<small>'.ghe_h($details[$key]).'</small>';
        echo '</li>';
    }
    echo '</ul>';
}
function ghe_english_faqs(): array {
    global $site,$content;
    $details=ghe_english_facility_details($content);
    $facilities=[];
    foreach(ghe_english_facility_labels() as $key=>$label)if(($content['facilities'][$key]??'unknown')==='yes')$facilities[]=$label.($details[$key]!==''?' — '.$details[$key]:'');
    return [
        ['Where is Ghé in Da Lat?','You can find us at '.$site['address'].', Vietnam. Use the Google Maps directions link on this page.'],
        ['What are your opening hours?','We are open '.$site['hours'].'. '.$site['hours_note']],
        ['What facilities are available?', $facilities ? implode('; ',$facilities) : 'Please contact our team to check the facilities you need.'],
        ['Where can I see the menu and prices?','Our English menu lists current items and prices in Vietnamese dong (VND). Vietnamese names are also shown to help you order. Please confirm availability with our team.'],
        ['Can I ask about allergies or ingredients?','Please speak to our team before ordering if you have a food allergy or dietary requirement.'],
    ];
}
