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
function ghe_photo(string $id,string $class='',bool $hero=false): void {
    global $content;
    $media=$content['media'][$id] ?? null;
    if (!$media) return;
    $suffix=defined('GHE_PREVIEW') && GHE_PREVIEW?'&preview=1':'';
    $base='ghe-image.php?id='.$id.$suffix;
    $src=ghe_link($base); $thumb=ghe_link($base.'&size=thumb');
    // Width descriptors reflect actual output dimensions, including portrait photos.
    $srcset=$thumb.' '.$media['thumb']['width'].'w';
    if($media['full']['width']!==$media['thumb']['width']) $srcset.=', '.$src.' '.$media['full']['width'].'w';
    $alt=ghe_is_english() ? (trim($media['alt_en']??'') ?: 'Ghé café in Da Lat') : $media['alt'];
    echo '<img class="'.ghe_h($class).'" src="'.ghe_h($src).'" srcset="'.ghe_h($srcset).'" sizes="(max-width:720px) 90vw, '.($hero?'50vw':'33vw').'" alt="'.ghe_h($alt).'" width="'.(int)$media['full']['width'].'" height="'.(int)$media['full']['height'].'" '.($hero?'fetchpriority="high"':'loading="lazy"').' decoding="async">';
}
function ghe_gallery(): void {
    global $content;
    $photos=array_filter($content['media'],function($m){return $m['gallery'];});
    if(!$photos) return;
    uasort($photos,function($a,$b){return $a['order']<=>$b['order'];});
    echo '<section class="wrap section"><p class="eyebrow">'.ghe_t('NHỮNG GÓC QUÁN','AROUND THE CAFÉ').'</p><h2>'.ghe_t('Một vòng quanh Ghé.','Take a look around.').'</h2><div class="photo-gallery">';
    foreach($photos as $id=>$photo) {echo '<figure>';ghe_photo($id,'gallery-photo');$caption=ghe_is_english()?($photo['caption_en']??''):$photo['caption'];if($caption!=='')echo '<figcaption>'.ghe_h($caption).'</figcaption>';echo '</figure>';}
    echo '</div></section>';
}
function ghe_featured_items(): array {
    global $menuSections;
    $items=[];
    foreach($menuSections as $section) foreach($section['items'] as $item) $items[]=$item;
    $featured=array_values(array_filter($items,function($item){return $item['featured'];}));
    return array_slice($featured ?: $items,0,3);
}
