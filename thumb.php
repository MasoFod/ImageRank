<?php
// thumb.php - 简单缩略图生成器
// 参数：src=相对路径 w=宽度 h=高度 (任选)
// 仅允许 img/ 目录下的文件，防止目录遍历

$src = $_GET['src'] ?? '';
$w = isset($_GET['w']) ? intval($_GET['w']) : 0;
$h = isset($_GET['h']) ? intval($_GET['h']) : 0;

// 提高内存限制以处理大图，但仍有上限
ini_set('memory_limit', '256M');

// 安全检查
$src = str_replace(['..', '\\', '//'], '', $src);
$base = __DIR__ . '/';
$full = realpath($base . $src);
if (!$full || strpos($full, realpath($base . 'img') . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(404);
    exit('Not found');
}

if (!file_exists($full)) {
    http_response_code(404);
    exit('Not found');
}

$info = getimagesize($full);
if (!$info) {
    http_response_code(415);
    exit('Unsupported image');
}

$origW = $info[0];
$origH = $info[1];
$ratio = $origW / $origH;

if ($w && !$h) {
    $h = intval($w / $ratio);
} elseif ($h && !$w) {
    $w = intval($h * $ratio);
} elseif (!$w && !$h) {
    $w = $origW;
    $h = $origH;
}

// 不放大
if ($w > $origW) $w = $origW;
if ($h > $origH) $h = $origH;

switch ($info[2]) {
    case IMAGETYPE_JPEG:
        $img = @imagecreatefromjpeg($full);
        $type = 'jpeg';
        break;
    case IMAGETYPE_PNG:
        $img = @imagecreatefrompng($full);
        $type = 'png';
        break;
    case IMAGETYPE_GIF:
        $img = @imagecreatefromgif($full);
        $type = 'gif';
        break;
    default:
        http_response_code(415);
        exit('Unsupported');
}

if (!$img) {
    // 如果 GD 无法打开（可能是内存不足），直接输出原图以保证可见
    header('Content-Type: ' . $info['mime']);
    readfile($full);
    exit;
}

$thumb = @imagecreatetruecolor($w, $h);
if (!$thumb) {
    // 失败同样回退
    header('Content-Type: ' . $info['mime']);
    readfile($full);
    imagedestroy($img);
    exit;
}

// 保留 PNG/GIF 透明度
if ($type === 'png' || $type === 'gif') {
    imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
    imagealphablending($thumb, false);
    imagesavealpha($thumb, true);
}

imagecopyresampled($thumb, $img, 0,0,0,0, $w,$h, $origW,$origH);

// 输出
header('Content-Type: image/' . $type);
if ($type === 'jpeg') {
    imagejpeg($thumb, null, 75);
} elseif ($type === 'png') {
    imagepng($thumb);
} else {
    imagegif($thumb);
}

imagedestroy($img);
imagedestroy($thumb);
