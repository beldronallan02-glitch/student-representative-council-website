<?php
// modules/feedback/inc/image_helper.php
if (!defined('FEEDBACK_UPLOAD_DIR')) {
    define('FEEDBACK_UPLOAD_DIR', __DIR__ . '/../uploads/feedback_images');
}

function ensure_feedback_dir($feedback_id) {
    $dir = FEEDBACK_UPLOAD_DIR . '/feedback_' . intval($feedback_id);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return $dir;
}

function is_allowed_image_file($tmpPath) {
    $info = @getimagesize($tmpPath);
    if ($info === false) return false;
    $mime = $info['mime'];
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    return in_array($mime, $allowed, true);
}

function make_unique_filename_fb($ext = 'jpg') {
    return uniqid('fimg_', true) . '.' . $ext;
}

function create_thumbnail_fb($srcPath, $destPath, $maxDim = 200) {
    $info = @getimagesize($srcPath);
    if ($info === false) {
        return @copy($srcPath, $destPath);
    }
    list($w, $h) = [$info[0], $info[1]];
    $mime = $info['mime'];

    // If small enough already or GD not available, fall back to copy
    if (($w <= $maxDim && $h <= $maxDim) || !extension_loaded('gd')) {
        return @copy($srcPath, $destPath);
    }

    $ratio = $w / $h;
    if ($ratio > 1) {
        $newW = $maxDim;
        $newH = (int)($maxDim / $ratio);
    } else {
        $newH = $maxDim;
        $newW = (int)($maxDim * $ratio);
    }

    $srcImg = null;
    switch ($mime) {
        case 'image/jpeg':
            if (function_exists('imagecreatefromjpeg')) { $srcImg = @imagecreatefromjpeg($srcPath); }
            break;
        case 'image/png':
            if (function_exists('imagecreatefrompng')) { $srcImg = @imagecreatefrompng($srcPath); }
            break;
        case 'image/webp':
            if (function_exists('imagecreatefromwebp')) { $srcImg = @imagecreatefromwebp($srcPath); }
            break;
        case 'image/gif':
            if (function_exists('imagecreatefromgif')) { $srcImg = @imagecreatefromgif($srcPath); }
            break;
        default:
            $srcImg = null;
    }
    if (!$srcImg) {
        return @copy($srcPath, $destPath);
    }

    if (!function_exists('imagecreatetruecolor')) {
        imagedestroy($srcImg);
        return @copy($srcPath, $destPath);
    }
    $thumb = imagecreatetruecolor($newW, $newH);
    if ($mime === 'image/png' || $mime === 'image/gif') {
        if (function_exists('imagecolorallocatealpha')) {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0,0,0,127));
        }
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
    }
    imagecopyresampled($thumb, $srcImg, 0, 0, 0, 0, $newW, $newH, $w, $h);

    $ok = false;
    switch ($mime) {
        case 'image/jpeg':
            if (function_exists('imagejpeg')) { $ok = @imagejpeg($thumb, $destPath, 85); }
            break;
        case 'image/png':
            if (function_exists('imagepng')) { $ok = @imagepng($thumb, $destPath); }
            break;
        case 'image/webp':
            if (function_exists('imagewebp')) { $ok = @imagewebp($thumb, $destPath); }
            break;
        case 'image/gif':
            if (function_exists('imagegif')) { $ok = @imagegif($thumb, $destPath); }
            break;
    }

    imagedestroy($srcImg);
    imagedestroy($thumb);

    if (!$ok) {
        return @copy($srcPath, $destPath);
    }
    return true;
}

/**
 * Save uploaded images for feedback
 * $files = $_FILES['images']
 * Returns array of saved meta or [] and fills $errors.
 */
function save_feedback_images(array $files, $feedback_id, &$errors = []) {
    $saved = [];
    $dir = ensure_feedback_dir($feedback_id);
    $maxFiles = 4;
    $maxSize = 5 * 1024 * 1024; // 5MB
    $normalized = [];
    if (!is_array($files['name'])) {
        $normalized[] = $files;
    } else {
        for ($i=0;$i<count($files['name']);$i++) {
            $normalized[] = [
                'name'=>$files['name'][$i],
                'type'=>$files['type'][$i],
                'tmp_name'=>$files['tmp_name'][$i],
                'error'=>$files['error'][$i],
                'size'=>$files['size'][$i],
            ];
        }
    }
    $count = 0;
    foreach ($normalized as $f) {
        if ($f['error'] !== UPLOAD_ERR_OK) continue;
        if ($count >= $maxFiles) { $errors[] = "Max {$maxFiles} images allowed"; break; }
        if ($f['size'] > $maxSize) { $errors[] = "{$f['name']} exceeds size limit"; continue; }
        if (!is_allowed_image_file($f['tmp_name'])) { $errors[] = "{$f['name']} is not a valid image"; continue; }

        $info = getimagesize($f['tmp_name']);
        $mime = $info['mime'];
        $ext = image_type_to_extension($info[2], false);
        $ext = ($ext === 'jpeg') ? 'jpg' : $ext;
        $filename = make_unique_filename_fb($ext);
        $full = $dir . '/' . $filename;
        if (!move_uploaded_file($f['tmp_name'], $full)) { $errors[] = "Failed to save {$f['name']}"; continue; }
        $thumbPath = $dir . '/thumb_' . $filename;
        create_thumbnail_fb($full, $thumbPath, 200);
        $saved[] = [
            'filename'=>$filename,
            'original_name'=>$f['name'],
            'mime'=>$mime,
            'size'=>$f['size'],
            'path'=>$full,
            'thumb'=>$thumbPath
        ];
        $count++;
    }
    return $saved;
}
