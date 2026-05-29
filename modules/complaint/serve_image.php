<?php
// serve_image.php - secure image delivery for complaint images
$PROJECT_ROOT = dirname(dirname(__DIR__));
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$img_id = isset($_GET['img']) ? intval($_GET['img']) : 0;
$thumb = isset($_GET['thumb']) ? true : false;

if ($img_id <= 0) { http_response_code(400); exit('Bad request'); }

try {
    // Note: removed c.is_anonymous as it might be missing in some DB schemas
    $stmt = $pdo->prepare("SELECT ci.*, c.user_id AS complaint_owner FROM complaint_images ci JOIN complaints c ON c.id = ci.complaint_id WHERE ci.id = :id LIMIT 1");
    $stmt->execute([':id'=>$img_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$img) { http_response_code(404); exit('Not found'); }

    $user = current_user();
    $is_admin = $user && in_array($user['role'] ?? '', ['mpp','admin'], true);
    $is_owner = $user && $user['id'] == $img['complaint_owner'];

    // if anonymous complaint: only admin or owner can view (owner may be the anonymous flag but still has id)
    if (!$is_admin && !$is_owner) {
        http_response_code(403); exit('Forbidden');
    }

    // Build safe path from DB filename (DB stores just 'cimg_....jpg')
    $base = __DIR__ . '/uploads/complaints/complaint_' . intval($img['complaint_id']);
    $name = basename((string)$img['filename']);
    $file = $base . '/' . $name;
    if ($thumb) {
        $thumbPath = $base . '/thumb_' . $name;
        if (is_file($thumbPath)) $file = $thumbPath;
    }

    if (!is_file($file)) { http_response_code(404); exit('Not found'); }
    
    // Robust MIME detection (avoid fatal if fileinfo extension missing)
    $mime = is_string($img['mime']) && $img['mime'] !== '' ? $img['mime'] : '';
    if ($mime === '') {
        if (function_exists('mime_content_type')) {
            $mime = @mime_content_type($file) ?: '';
        }
        if ($mime === '' && class_exists('finfo')) {
            $fi = new finfo(FILEINFO_MIME_TYPE);
            $det = $fi->file($file);
            if (is_string($det) && $det !== '') { $mime = $det; }
        }
        if ($mime === '') {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            $map = [
                'jpg'=>'image/jpeg','jpeg'=>'image/jpeg','png'=>'image/png','gif'=>'image/gif','webp'=>'image/webp','bmp'=>'image/bmp'
            ];
            $mime = $map[$ext] ?? 'application/octet-stream';
        }
    }
    
    // Clean output buffer to prevent corrupted images
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400');
    readfile($file);
    exit;
} catch (Throwable $e) {
    error_log('serve_image error: '.$e->getMessage());
    http_response_code(500); 
    exit('Server error: ' . $e->getMessage());
}
