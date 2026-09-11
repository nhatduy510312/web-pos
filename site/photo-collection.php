<?php
// A versioned, additive photo release. Never overwrite a stored content.json on deploy.
function ghe_photo_collection(): array {
    static $photos=null;
    if($photos===null) {
        $photos=[];
        foreach(json_decode(file_get_contents(__DIR__.'/photo-collection.json'),true,512,JSON_THROW_ON_ERROR) as $photo) $photos[$photo['id']]=$photo;
    }
    return $photos;
}
function ghe_apply_photo_collection(array $content): array {
    if(($content['photo_collection_version']??0)>=1)return $content;
    $photos=ghe_photo_collection();
    // Stored metadata takes priority. The version marker lets later removals stay removed.
    $content['media']=array_replace($photos,$content['media']??[]);
    if(empty($content['settings']['hero_image']))foreach($photos as $id=>$photo)if($photo['number']===10)$content['settings']['hero_image']=$id;
    $content['photo_collection_version']=1;
    return $content;
}
function ghe_collection_file(string $id,string $size): ?string {
    if(!isset(ghe_photo_collection()[$id]) || !in_array($size,['full','thumb'],true))return null;
    return __DIR__.'/photos/collection-20260910/'.$id.'-'.$size.'.webp';
}
function ghe_collection_product(string $name,array $content): array {
    $name=mb_strtolower(trim(preg_replace('/\s+/u',' ',$name)),'UTF-8');
    $number=0; $note=''; $english='';
    if(in_array($name,['bạc xỉu','bạc sỉu','croissant truyền thống'],true)) {
        $number=26;$note='Ảnh gồm bạc xỉu và bánh croissant.';$english='Photo shows bạc xỉu coffee and a croissant.';
    } elseif(in_array($name,['muối','cà phê muối','sữa dừa','cà phê cốt dừa','cà phê sữa dừa'],true)) {
        $number=37;$note='Ảnh gồm cà phê cốt dừa và cà phê muối.';$english='Photo shows coconut milk coffee and salt cream coffee.';
    } elseif(in_array($name,['sữa chua trái cây','sữa chua dâu tằm'],true)) {
        $number=41;$note='Ảnh phiên bản sữa chua dâu tằm.';$english='The mulberry yogurt option is pictured.';
    } elseif(in_array($name,['ổi chanh dây','trà ổi chanh dây'],true))$number=42;
    foreach(ghe_photo_collection() as $id=>$photo)if($photo['number']===$number && isset($content['media'][$id]))return ['image'=>$id,'image_note'=>$note,'image_note_en'=>$english];
    return ['image'=>'','image_note'=>'','image_note_en'=>''];
}
