<?php
require_once __DIR__.'/photo-collection.php';
// Private, versioned website content. All mutations require the authenticated controller.
function ghe_storage_dir(): string { return getenv('GHE_STORAGE_DIR') ?: __DIR__ . '/storage'; }
function ghe_content_defaults(): array {
    return [
        'settings' => [
            'street' => '30 Võ Trường Toản', 'city' => 'Đà Lạt', 'region' => 'Lâm Đồng',
            'phone' => '0705 926 614', 'maps' => 'https://maps.app.goo.gl/ySJSyKgaPvpcgA549',
            'opens' => '07:00', 'closes' => '18:00', 'hours_note' => 'Mở cửa hằng ngày.',
            'home_title' => "Ghé một chút.\nChậm lại một chút.",
            'home_lead' => 'Giữa sân vườn xanh và những góc ngồi trong nhà thoáng mát, tinh tế, Ghé là nơi để học tập, làm việc, sẻ chia — hoặc đơn giản là dành cho mình một nhịp chậm hơn.',
            'home_intro' => 'Ghé là quán cà phê có sân vườn xanh và không gian trong nhà thoáng mát, tinh tế tại 30 Võ Trường Toản, Đà Lạt. Một nơi phù hợp để học tập, làm việc, gặp gỡ và chậm lại một chút.',
            'about_title' => 'Một nơi cho nhịp riêng của bạn.',
            'about_body' => "Ở Ghé, bạn có thể chọn một chỗ dưới tán cây hoặc một góc trong nhà ngập ánh sáng. Không gian thoáng mát, mộc mạc và đủ riêng tư để mỗi người tìm được nhịp của mình.\n\nĐến để học tập, làm việc, gặp gỡ và sẻ chia. Hoặc không cần một lý do đặc biệt — chỉ là ghé lại, gọi một món mình thích và chậm lại một chút.\n\nWi-Fi ổn định và ổ điện ở mỗi bàn giúp những buổi học hay ngày làm việc thêm thuận tiện. Ghé mở cửa từ 7h đến 18h hằng ngày.",
            'menu_intro' => 'Một ly cà phê, chút trà hay món bánh nhỏ — vừa đủ cho khoảng thời gian của bạn ở Ghé.',
            'visit_note' => 'Nếu đi theo nhóm hoặc có nhu cầu riêng về chỗ ngồi, bạn có thể gọi quán trước khi đến.',
            'hero_image' => '',
        ],
        'facilities' => ['garden' => 'yes', 'indoor' => 'yes', 'quiet' => 'yes', 'work' => 'yes', 'study' => 'yes', 'chat' => 'yes', 'wifi' => 'yes', 'sockets' => 'yes', 'parking' => 'yes'],
        'facility_details' => ['wifi'=>'Wi-Fi 6, đường truyền 1 Gbps','sockets'=>'Ổ điện ở mỗi bàn','parking'=>'Chỗ đỗ ô tô và xe máy thoải mái dọc đường'],
        'media' => [], 'products' => [], 'posts' => [],
    ];
}
function ghe_apply_brand_refinement(array $content): array {
    if (($content['brand_refinement_version'] ?? 0) >= 1) return $content;
    $content['settings'] = array_replace($content['settings'] ?? [], [
        'home_title' => "Ghé một chút.\nChậm lại một chút.",
        'home_lead' => 'Giữa sân vườn xanh và những góc ngồi trong nhà thoáng mát, tinh tế, Ghé là nơi để học tập, làm việc, sẻ chia — hoặc đơn giản là dành cho mình một nhịp chậm hơn.',
        'home_intro' => 'Ghé là quán cà phê có sân vườn xanh và không gian trong nhà thoáng mát, tinh tế tại 30 Võ Trường Toản, Đà Lạt. Một nơi phù hợp để học tập, làm việc, gặp gỡ và chậm lại một chút.',
        'about_title' => 'Một nơi cho nhịp riêng của bạn.',
        'about_body' => "Ở Ghé, bạn có thể chọn một chỗ dưới tán cây hoặc một góc trong nhà ngập ánh sáng. Không gian thoáng mát, mộc mạc và đủ riêng tư để mỗi người tìm được nhịp của mình.\n\nĐến để học tập, làm việc, gặp gỡ và sẻ chia. Hoặc không cần một lý do đặc biệt — chỉ là ghé lại, gọi một món mình thích và chậm lại một chút.\n\nWi-Fi ổn định và ổ điện ở mỗi bàn giúp những buổi học hay ngày làm việc thêm thuận tiện. Ghé mở cửa từ 7h đến 18h hằng ngày.",
        'menu_intro' => 'Một ly cà phê, chút trà hay món bánh nhỏ — vừa đủ cho khoảng thời gian của bạn ở Ghé.',
    ]);
    $content['settings_en'] = array_replace($content['settings_en'] ?? [], [
        'home_title' => "Stop by for a while.\nSlow down for a while.",
        'home_lead' => 'Between a green garden and light-filled indoor corners, Ghé is a calm place to study, work and connect — or simply give yourself a slower moment.',
        'home_intro' => 'Ghé is a garden café with airy, thoughtful indoor spaces at 30 Vo Truong Toan, Da Lat. A place to study, work, meet and slow down for a while.',
        'about_title' => 'A place for your own pace.',
        'about_body' => "At Ghé, choose a seat beneath the trees or a light-filled corner indoors. The space is airy, warm and quiet enough for everyone to find their own pace.\n\nCome to study, work, meet and connect. Or come with no particular plan — order something you like and slow down for a while.\n\nReliable Wi-Fi and a power outlet at every table make longer study and work sessions easier. Ghé is open daily from 07:00 to 18:00.",
        'menu_intro' => 'A coffee, a tea or something small from the bakery — just enough for your time at Ghé.',
    ]);
    $content['brand_refinement_version'] = 1;
    return $content;
}
function ghe_facility_labels(): array {
    return ['garden'=>'Không gian sân vườn','indoor'=>'Không gian trong nhà','quiet'=>'Không gian yên tĩnh','work'=>'Phù hợp làm việc','study'=>'Phù hợp học tập','chat'=>'Phù hợp trò chuyện','wifi'=>'Wi-Fi','sockets'=>'Ổ cắm điện','parking'=>'Chỗ đỗ xe'];
}
function ghe_state_read(): array {
    $file = ghe_storage_dir() . '/content.json';
    if (!is_file($file)) return ['revision'=>0, 'draft'=>ghe_apply_brand_refinement(ghe_apply_photo_collection(ghe_content_defaults())), 'published'=>ghe_apply_brand_refinement(ghe_apply_photo_collection(ghe_content_defaults())), 'published_at'=>null];
    $data = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['revision'], $data['draft'], $data['published'])) throw new RuntimeException('Dữ liệu website không hợp lệ. Vui lòng kiểm tra bản sao lưu.');
    foreach(['draft','published'] as $version)$data[$version]=ghe_apply_brand_refinement(ghe_apply_photo_collection($data[$version]));
    return $data;
}
function ghe_state_update(int $expectedRevision, callable $change): array {
    $dir = ghe_storage_dir();
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) throw new RuntimeException('Không tạo được thư mục nội dung.');
    $lock = fopen($dir . '/content.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Không khóa được nội dung để lưu.');
    $temp = null;
    try {
        $state = ghe_state_read();
        if ($expectedRevision !== (int)$state['revision']) throw new RuntimeException('Nội dung đã được cập nhật ở cửa sổ khác. Hãy tải lại trang trước khi sửa tiếp.');
        $next = $change($state);
        $next['revision'] = $state['revision'] + 1;
        $json = json_encode($next, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temp = tempnam($dir, 'write-');
        if ($temp === false || file_put_contents($temp, $json) !== strlen($json)) throw new RuntimeException('Không ghi được nội dung. Kiểm tra dung lượng và quyền ghi thư mục.');
        // Keep the previous version recoverable, and only expose complete JSON files.
        if (is_file($dir . '/content.json') && !copy($dir . '/content.json', $dir . '/content.previous.json')) throw new RuntimeException('Không tạo được bản sao lưu.');
        if (!rename($temp, $dir . '/content.json')) throw new RuntimeException('Không thay thế được nội dung website.');
        $temp = null;
        return $next;
    } finally { if ($temp && is_file($temp)) unlink($temp); flock($lock, LOCK_UN); fclose($lock); }
}
function ghe_text($value, int $limit, bool $required = false): string {
    if (!is_string($value)) throw new InvalidArgumentException('Nội dung nhập không hợp lệ.');
    $value = trim(str_replace("\r\n", "\n", $value));
    if (($required && $value === '') || mb_strlen($value, 'UTF-8') > $limit || !mb_check_encoding($value, 'UTF-8')) throw new InvalidArgumentException('Nội dung trống, quá dài hoặc không đúng UTF-8.');
    return $value;
}
function ghe_settings_validate(array $input, array $current): array {
    if(array_key_exists('home_lead',$input))$current['home_lead']=ghe_text($input['home_lead'],600);
    $limits = ['street'=>180,'city'=>100,'region'=>100,'phone'=>30,'maps'=>1000,'opens'=>5,'closes'=>5,'hours_note'=>250,'home_title'=>160,'home_intro'=>1000,'about_title'=>180,'about_body'=>15000,'menu_intro'=>1500,'visit_note'=>1500];
    foreach ($limits as $key=>$limit) $current[$key] = ghe_text($input[$key] ?? '', $limit, $key !== 'hours_note');
    if (!preg_match('/^\+?[0-9 ()-]{9,25}$/', $current['phone'])) throw new InvalidArgumentException('Số điện thoại không hợp lệ.');
    $maps = parse_url($current['maps']);
    if (($maps['scheme'] ?? '') !== 'https' || !in_array(strtolower($maps['host'] ?? ''), ['maps.app.goo.gl','maps.google.com','www.google.com','www.google.com.vn'], true) || isset($maps['user']) || isset($maps['pass'])) throw new InvalidArgumentException('Vui lòng dùng liên kết HTTPS của Google Maps.');
    foreach (['opens','closes'] as $field) if (!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $current[$field])) throw new InvalidArgumentException('Giờ hoạt động phải có dạng HH:MM.');
    if ($current['opens'] >= $current['closes']) throw new InvalidArgumentException('Giờ đóng cửa phải sau giờ mở cửa trong cùng ngày.');
    return $current;
}
