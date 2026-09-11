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
            'home_title' => "Một chút cà phê.\nMột cuộc hẹn.\nMột lần ghé.",
            'home_lead' => 'Một ly cà phê, một chỗ ngồi vừa ý. Dành chút thời gian cho riêng mình, hoặc cho một cuộc hẹn bạn đã mong từ lâu.',
            'home_intro' => 'Ghé có sân vườn và không gian trong nhà yên tĩnh tại 30 Võ Trường Toản, Đà Lạt. Wi-Fi 6, đường truyền 1 Gbps và ổ điện ở mỗi bàn, phù hợp làm việc, học tập và trò chuyện. Mở cửa 7h–18h mỗi ngày.',
            'about_title' => 'Một khoảng yên tĩnh, giữa những cuộc hẹn ở Đà Lạt.',
            'about_body' => "Ghé là quán cà phê tại 30 Võ Trường Toản, Đà Lạt, có không gian sân vườn và chỗ ngồi trong nhà. Bạn có thể chọn khoảng ngồi phù hợp với cuộc hẹn của mình.\n\nKhông gian yên tĩnh của Ghé phù hợp để làm việc, học tập và trò chuyện. Quán có Wi-Fi 6 với đường truyền 1 Gbps, ổ điện ở mỗi bàn để bạn thuận tiện dùng máy tính hoặc sạc thiết bị.\n\nBạn có thể đỗ ô tô và xe máy dọc đường. Quán mở cửa từ 7h đến 18h hằng ngày. Xem menu trước khi đến, hoặc gọi quán nếu bạn có nhu cầu cụ thể cho nhóm của mình.",
            'menu_intro' => 'Chọn món cho buổi làm việc, giờ học hoặc cuộc hẹn ở Ghé. Tên món và giá được cập nhật từ thực đơn bán hàng của quán.',
            'visit_note' => 'Nếu đi theo nhóm hoặc có nhu cầu riêng về chỗ ngồi, bạn có thể gọi quán trước khi đến.',
            'hero_image' => '',
        ],
        'facilities' => ['garden' => 'yes', 'indoor' => 'yes', 'quiet' => 'yes', 'work' => 'yes', 'study' => 'yes', 'chat' => 'yes', 'wifi' => 'yes', 'sockets' => 'yes', 'parking' => 'yes'],
        'facility_details' => ['wifi'=>'Wi-Fi 6, đường truyền 1 Gbps','sockets'=>'Ổ điện ở mỗi bàn','parking'=>'Chỗ đỗ ô tô và xe máy thoải mái dọc đường'],
        'media' => [], 'products' => [], 'posts' => [],
    ];
}
function ghe_facility_labels(): array {
    return ['garden'=>'Không gian sân vườn','indoor'=>'Không gian trong nhà','quiet'=>'Không gian yên tĩnh','work'=>'Phù hợp làm việc','study'=>'Phù hợp học tập','chat'=>'Phù hợp trò chuyện','wifi'=>'Wi-Fi','sockets'=>'Ổ cắm điện','parking'=>'Chỗ đỗ xe'];
}
function ghe_state_read(): array {
    $file = ghe_storage_dir() . '/content.json';
    if (!is_file($file)) return ['revision'=>0, 'draft'=>ghe_apply_photo_collection(ghe_content_defaults()), 'published'=>ghe_apply_photo_collection(ghe_content_defaults()), 'published_at'=>null];
    $data = json_decode((string)file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($data) || !isset($data['revision'], $data['draft'], $data['published'])) throw new RuntimeException('Dữ liệu website không hợp lệ. Vui lòng kiểm tra bản sao lưu.');
    foreach(['draft','published'] as $version)$data[$version]=ghe_apply_photo_collection($data[$version]);
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
