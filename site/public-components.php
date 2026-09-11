<?php
function ghe_paragraphs(string $text): void {
    foreach (preg_split('/\n\s*\n/u',trim($text)) as $paragraph) if ($paragraph!=='') echo '<p>'.nl2br(ghe_h($paragraph)).'</p>';
}
function ghe_facilities_sentence(): string {
    global $content;
    $yes=[];
    foreach (ghe_facility_labels() as $key=>$label) if (($content['facilities'][$key]??'unknown')==='yes') $yes[]=($content['facility_details'][$key]??'') ?: mb_strtolower($label,'UTF-8');
    return $yes ? 'Ghé có các đặc điểm: ' . implode(', ',$yes) . '.' : 'Bạn vui lòng liên hệ quán để hỏi thông tin phù hợp với nhu cầu của mình.';
}
function ghe_facilities(): void {
    global $content;
    echo '<ul class="facility-list">';
    foreach (ghe_facility_labels() as $key=>$label) if (($content['facilities'][$key]??'unknown')!=='unknown') echo '<li>'.ghe_h(($content['facilities'][$key]==='yes'?'✓ ':'Không có: ').$label).($content['facilities'][$key]==='yes' && !empty($content['facility_details'][$key])?'<small>'.ghe_h($content['facility_details'][$key]).'</small>':'').'</li>';
    echo '</ul>';
}
function ghe_photo(string $id,string $class='',bool $hero=false,bool $thumbnailOnly=false): void {
    global $content;
    $media=$content['media'][$id] ?? null;
    if (!$media) return;
    $suffix=defined('GHE_PREVIEW') && GHE_PREVIEW?'&preview=1':'';
    $base='ghe-image.php?id='.$id.$suffix;
    $src=ghe_link($base); $thumb=ghe_link($base.'&size=thumb');
    if($thumbnailOnly)$src=$thumb;
    // Width descriptors reflect actual output dimensions, including portrait photos.
    $srcset=$thumb.' '.$media['thumb']['width'].'w';
    if(!$thumbnailOnly && $media['full']['width']!==$media['thumb']['width']) $srcset.=', '.$src.' '.$media['full']['width'].'w';
    $alt=ghe_is_english() ? (trim($media['alt_en']??'') ?: 'Ghé café in Da Lat') : $media['alt'];
    echo '<img class="'.ghe_h($class).'" src="'.ghe_h($src).'" srcset="'.ghe_h($srcset).'" sizes="(max-width:720px) 90vw, '.($hero?'50vw':'33vw').'" alt="'.ghe_h($alt).'" width="'.(int)$media['full']['width'].'" height="'.(int)$media['full']['height'].'" '.($hero?'fetchpriority="high"':'loading="lazy"').' decoding="async">';
}
function ghe_gallery(bool $full=false, int $limit=6, bool $excludeHero=false): void {
    global $content;
    $photos=array_filter($content['media'],function($m){return $m['gallery'];});
    if(!$photos) return;
    uasort($photos,function($a,$b){return $a['order']<=>$b['order'];});
    $total=count($photos);
    if($excludeHero)unset($photos[$content['settings']['hero_image']]);
    if(!$full)$photos=array_slice($photos,0,max(0,$limit),true);
    echo '<section class="wrap section"><div class="section-heading"><div><p class="eyebrow">'.ghe_t('ẢNH THẬT TẠI GHÉ','REAL MOMENTS AT GHÉ').'</p><h2>'.ghe_t('Một vòng quanh Ghé.','Take a look around.').'</h2></div>';
    if(!$full)echo '<a class="text-link" href="'.ghe_h(ghe_link('khong-gian.php')).'">'.ghe_t('Xem toàn bộ ảnh','View all photos').' ('.$total.') →</a>';
    echo '</div><div class="photo-gallery'.($full?' photo-gallery-full':'').'" data-photo-gallery>';
    foreach($photos as $id=>$photo) {
        $caption=ghe_is_english()?($photo['caption_en']??''):$photo['caption'];
        $alt=ghe_is_english()?($photo['alt_en']??'Ghé café in Da Lat'):$photo['alt'];
        $suffix=defined('GHE_PREVIEW')&&GHE_PREVIEW?'&preview=1':'';
        echo '<figure><a class="gallery-link" href="'.ghe_h(ghe_link('ghe-image.php?id='.$id.$suffix)).'" data-gallery-photo data-caption="'.ghe_h($caption?:$alt).'" aria-label="'.ghe_h(ghe_t('Xem ảnh lớn: ','Enlarge photo: ').$alt).'">';
        ghe_photo($id,'gallery-photo',false,true);
        echo '<span class="photo-expand" aria-hidden="true">↗</span></a>';
        if($caption!=='')echo '<figcaption>'.ghe_h($caption).'</figcaption>';echo '</figure>';
    }
    echo '</div></section>';
}
function ghe_menu_photo(array $item,string $class): void {
    ghe_photo($item['image'],$class);
    $note=ghe_is_english()?($item['image_note_en']??''):($item['image_note']??'');
    if($note!=='')echo '<p class="photo-note">'.ghe_h($note).'</p>';
}
function ghe_featured_items(): array {
    global $menuSections;
    $items=[];
    foreach($menuSections as $section) foreach($section['items'] as $item) $items[]=$item;
    $featured=array_values(array_filter($items,function($item){return $item['featured'];}));
    if($featured)return array_slice($featured,0,3);
    // Prioritise real photos and show different photos instead of repeating a paired shot.
    global $content;
    $priority=['bạc xỉu'=>10,'bạc sỉu'=>10,'ổi chanh dây'=>20,'trà ổi chanh dây'=>20,'muối'=>30,'cà phê muối'=>30,'sữa dừa'=>31,'cà phê cốt dừa'=>31,'sữa chua trái cây'=>40,'sữa chua dâu tằm'=>40];
    usort($items,function($a,$b)use($priority){return ($priority[mb_strtolower($a['name_vi']??$a['name'],'UTF-8')]??100)<=>($priority[mb_strtolower($b['name_vi']??$b['name'],'UTF-8')]??100);});
    $pictured=[];$seen=[];
    foreach($items as $item)if(!empty($content['media'][$item['image']]) && !isset($seen[$item['image']])){$pictured[]=$item;$seen[$item['image']]=true;}
    return array_slice($pictured ?: $items,0,3);
}
