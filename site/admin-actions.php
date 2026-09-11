<?php
require_once __DIR__ . '/content-store.php';
require_once __DIR__ . '/media.php';
require_once __DIR__ . '/english.php';
function ghe_admin_action(array $post, array $files, array $catalog): string {
    $action = (string)($post['action'] ?? '');
    $revision = filter_var($post['revision'] ?? null, FILTER_VALIDATE_INT);
    if ($revision === false || $revision === null) throw new InvalidArgumentException('Thiếu phiên bản nội dung. Hãy tải lại trang.');
    $uploaded = null;
    if ($action === 'upload') {
        ghe_text($post['alt'] ?? '', 200, true);
        $uploaded = ghe_media_upload($files['photo'] ?? []);
    }
    try {
        ghe_state_update($revision, function ($state) use ($post,$action,$catalog,$uploaded) {
            $draft = &$state['draft'];
            switch ($action) {
                case 'english_settings':
                    foreach (ghe_english_defaults() as $key=>$default) {
                        $limits=['home_title'=>160,'home_lead'=>600,'home_intro'=>1000,'about_title'=>180,'about_body'=>15000,'menu_intro'=>1500,'visit_note'=>1500,'hours_note'=>250];
                        if($key==='home_lead' && !array_key_exists($key,$post))continue;
                        $draft['settings_en'][$key]=ghe_text($post[$key]??'', $limits[$key]);
                    }
                    foreach (ghe_facility_labels() as $key=>$label) $draft['facility_details_en'][$key]=ghe_text($post['facility_details_en'][$key]??'',200);
                    break;
                case 'settings':
                    $draft['settings'] = ghe_settings_validate($post, $draft['settings']);
                    foreach (ghe_facility_labels() as $key=>$label) {
                        $value = $post['facilities'][$key] ?? 'unknown';
                        if (!in_array($value, ['yes','no','unknown'], true)) throw new InvalidArgumentException('Tiện ích không hợp lệ.');
                        $draft['facilities'][$key] = $value;
                        $draft['facility_details'][$key] = ghe_text($post['facility_details'][$key] ?? '',200);
                    }
                    break;
                case 'upload':
                    $draft['media'][$uploaded['id']] = $uploaded + ['alt'=>ghe_text($post['alt'],200,true), 'caption'=>ghe_text($post['caption'] ?? '',300), 'gallery'=>isset($post['gallery']), 'order'=>count($draft['media'])];
                    $draft['media'][$uploaded['id']]['alt_en']=ghe_text($post['alt_en']??'',200);
                    $draft['media'][$uploaded['id']]['caption_en']=ghe_text($post['caption_en']??'',300);
                    break;
                case 'media':
                    $id = ghe_text($post['id'] ?? '',32,true);
                    if (!isset($draft['media'][$id])) throw new InvalidArgumentException('Không tìm thấy ảnh.');
                    $draft['media'][$id]['alt'] = ghe_text($post['alt'] ?? '',200,true);
                    $draft['media'][$id]['caption'] = ghe_text($post['caption'] ?? '',300);
                    foreach (['alt_en'=>200,'caption_en'=>300] as $key=>$limit) if (array_key_exists($key,$post)) $draft['media'][$id][$key]=ghe_text($post[$key],$limit);
                    $draft['media'][$id]['gallery'] = isset($post['gallery']);
                    $draft['media'][$id]['order'] = max(0,min(9999,(int)($post['order'] ?? 0)));
                    if (isset($post['hero'])) $draft['settings']['hero_image'] = $id;
                    elseif ($draft['settings']['hero_image'] === $id) $draft['settings']['hero_image'] = '';
                    break;
                case 'remove_media':
                    $id = ghe_text($post['id'] ?? '',32,true);
                    unset($draft['media'][$id]);
                    if ($draft['settings']['hero_image'] === $id) $draft['settings']['hero_image']='';
                    foreach ($draft['products'] as &$product) if (($product['image'] ?? '') === $id) $product['image']='';
                    unset($product);
                    break;
                case 'product':
                    $id = (int)($post['id'] ?? 0);
                    if (!in_array($id, array_map('intval',array_column($catalog,'id')),true)) throw new InvalidArgumentException('Món không còn trong POS. Hãy tải lại trang.');
                    $image = ghe_text($post['image'] ?? '',32);
                    if ($image !== '' && !isset($draft['media'][$image])) throw new InvalidArgumentException('Ảnh không tồn tại trong thư viện.');
                    $english=[];
                    foreach (['name_en'=>200,'description_en'=>1000] as $key=>$limit) $english[$key]=ghe_text($post[$key]??($draft['products'][(string)$id][$key]??''),$limit);
                    $draft['products'][(string)$id] = ['description'=>ghe_text($post['description'] ?? '',1000), 'visible'=>isset($post['visible']), 'featured'=>isset($post['featured']), 'image'=>$image] + $english;
                    $draft['products'][(string)$id]['image_manual']=true;
                    break;
                case 'post':
                    $slug = ghe_text($post['slug'] ?? '',100,true);
                    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)) throw new InvalidArgumentException('Đường dẫn bài viết chỉ gồm chữ thường không dấu, số và dấu gạch ngang.');
                    if (($post['original_slug'] ?? '') !== $slug && isset($draft['posts'][$slug])) throw new InvalidArgumentException('Đường dẫn này đã được sử dụng.');
                    if (!empty($post['original_slug']) && $post['original_slug'] !== $slug) throw new InvalidArgumentException('Giữ nguyên đường dẫn bài đã lưu để tránh hỏng liên kết.');
                    $draft['posts'][$slug] = ['slug'=>$slug,'title'=>ghe_text($post['title'] ?? '',160,true),'excerpt'=>ghe_text($post['excerpt'] ?? '',320,true),'body'=>ghe_text($post['body'] ?? '',30000,true),'enabled'=>isset($post['enabled']), 'published_at'=>$draft['posts'][$slug]['published_at'] ?? null];
                    break;
                case 'remove_post': unset($draft['posts'][ghe_text($post['slug'] ?? '',100,true)]); break;
                case 'publish':
                    foreach ($draft['posts'] as $slug=>&$entry) {
                        if ($entry['enabled'] && !$entry['published_at']) $entry['published_at'] = gmdate(DATE_ATOM);
                        if (($state['published']['posts'][$slug]??null)!==$entry) $entry['updated_at']=gmdate(DATE_ATOM);
                    }
                    unset($entry);
                    $state['published'] = $draft;
                    $state['published_at'] = gmdate(DATE_ATOM);
                    break;
                case 'reset_draft': $draft = $state['published']; break;
                default: throw new InvalidArgumentException('Thao tác không hợp lệ.');
            }
            return $state;
        });
    } catch (Throwable $e) {
        // Only remove files created by this failed upload; existing or published images are retained.
        if ($uploaded) foreach (['full','thumb'] as $size) { $file=ghe_media_file($uploaded['id'],$size); if (is_file($file)) unlink($file); }
        throw $e;
    }
    return $action === 'publish' ? 'Đã xuất bản website.' : 'Đã lưu bản nháp. Xem trước và bấm Xuất bản để áp dụng cho khách.';
}
