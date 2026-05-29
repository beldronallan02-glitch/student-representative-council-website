<?php
// modules/feedback/serve_image_feedback.php
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$img_id = isset($_GET['img']) ? intval($_GET['img']) : 0;
$thumb = isset($_GET['thumb']) ? true : false;

if ($img_id <= 0) { http_response_code(400); exit('Bad request'); }

try {
    $stmt = $pdo->prepare("SELECT fi.*, f.user_id AS feedback_user, f.anonymous FROM feedback_images fi JOIN feedbacks f ON f.id = fi.feedback_id WHERE fi.id = :id LIMIT 1");
    $stmt->execute([':id'=>$img_id]);
    $img = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$img) { http_response_code(404); exit('Not found'); }

    $user = current_user();
    $is_admin = $user && in_array($user['role'] ?? '', ['mpp','admin'], true);
    $is_owner = $user && $user['id'] == $img['feedback_user'];

    // Allow admin to view all images; allow owner to view their feedback images
    if (!$is_admin && !$is_owner) { http_response_code(403); exit('Forbidden'); }

    $base = __DIR__ . '/uploads/feedback_images/feedback_' . intval($img['feedback_id']);
    $file = $base . '/' . $img['filename'];
    if ($thumb) {
        $thumbPath = $base . '/thumb_' . $img['filename'];
        if (is_file($thumbPath)) $file = $thumbPath;
    }
    if (!is_file($file)) { http_response_code(404); exit('Not found'); }

    $mime = $img['mime'] ?: mime_content_type($file);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: private, max-age=86400');
    readfile($file);
    exit;
} catch (Exception $e) {
    error_log('serve_image_feedback error: ' . $e->getMessage());
    http_response_code(500); exit('Server error');
}
