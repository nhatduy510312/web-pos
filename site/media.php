<?php
function ghe_media_file(string $id, string $size = 'full'): string {
    if (!preg_match('/^[a-f0-9]{32}$/D', $id) || !in_array($size, ['full','thumb'], true)) throw new InvalidArgumentException('Ảnh không hợp lệ.');
    return ghe_storage_dir() . '/media/' . $id . '-' . $size . '.webp';
}
function ghe_media_upload(array $upload): array {
    if (($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new RuntimeException('Tải ảnh thất bại. Hãy chọn ảnh JPEG, PNG hoặc WebP tối đa 8 MB.');
    if (!is_uploaded_file($upload['tmp_name']) || filesize($upload['tmp_name']) > 8 * 1024 * 1024) throw new RuntimeException('Ảnh phải nhỏ hơn 8 MB.');
    $info = @getimagesize($upload['tmp_name']);
    if (!$info || !in_array($info['mime'], ['image/jpeg','image/png','image/webp'], true)) throw new RuntimeException('Chỉ nhận ảnh JPEG, PNG hoặc WebP. Không nhận SVG hoặc tệp thực thi.');
    if ($info[0] > 10000 || $info[1] > 10000 || $info[0] * $info[1] > 20000000) throw new RuntimeException('Ảnh quá lớn. Vui lòng dùng ảnh tối đa 20 megapixel.');
    if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')) throw new RuntimeException('Hosting cần bật PHP GD có WebP để tối ưu ảnh.');
    $source = @imagecreatefromstring(file_get_contents($upload['tmp_name']));
    if (!$source) throw new RuntimeException('Không đọc được nội dung ảnh.');
    if ($info['mime'] === 'image/jpeg' && function_exists('exif_read_data')) {
        $exif = @exif_read_data($upload['tmp_name']); $orientation = (int)($exif['Orientation'] ?? 1);
        if (in_array($orientation, [2,4,5,7], true)) imageflip($source, IMG_FLIP_HORIZONTAL);
        $angle = [3=>180,4=>180,5=>90,6=>-90,7=>-90,8=>90][$orientation] ?? 0;
        if ($angle) { $rotated = imagerotate($source, $angle, 0); imagedestroy($source); $source = $rotated; }
    }
    $id = bin2hex(random_bytes(16)); $dir = ghe_storage_dir() . '/media';
    if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) { imagedestroy($source); throw new RuntimeException('Không tạo được thư mục ảnh.'); }
    $result = ['id'=>$id]; $created = [];
    try {
        foreach (['full'=>1600,'thumb'=>480] as $size=>$max) {
            $ratio = min(1, $max / max(imagesx($source), imagesy($source)));
            $w = max(1, (int)round(imagesx($source)*$ratio)); $h = max(1, (int)round(imagesy($source)*$ratio));
            $canvas = imagecreatetruecolor($w,$h); imagealphablending($canvas,false); imagesavealpha($canvas,true);
            imagecopyresampled($canvas,$source,0,0,0,0,$w,$h,imagesx($source),imagesy($source));
            $file = ghe_media_file($id,$size); $created[]=$file;
            try { if (!imagewebp($canvas,$file,82)) throw new RuntimeException('Không ghi được ảnh tối ưu.'); } finally { imagedestroy($canvas); }
            $result[$size] = ['width'=>$w,'height'=>$h];
        }
        return $result;
    } catch (Throwable $e) { foreach ($created as $file) if (is_file($file)) unlink($file); throw $e; }
    finally { imagedestroy($source); }
}
