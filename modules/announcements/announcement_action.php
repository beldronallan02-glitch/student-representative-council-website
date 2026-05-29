<?php
// modules/announcements/announcement_action.php
// Handles archive, unarchive, and hard delete actions for announcements

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';

// Build web-root path
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';
function root_url($path = '') { global $webRoot; return ($webRoot ?: '') . '/' . ltrim($path, '/'); }

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('Location: ' . root_url('login.php')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_announcements.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { header('Location: admin_announcements.php'); exit; }

$id = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');
if ($id <= 0 || $action === '') { header('Location: admin_announcements.php'); exit; }

try {
    // Load announcement for delete/image handling
    $stmt = $pdo->prepare('SELECT id, image FROM announcements WHERE id=? LIMIT 1');
    $stmt->execute([$id]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ann) { header('Location: admin_announcements.php'); exit; }

    if ($action === 'archive') {
        $up = $pdo->prepare("UPDATE announcements SET status='archived', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
        header('Location: admin_announcements.php'); exit;
    }

    if ($action === 'unarchive') {
        $up = $pdo->prepare("UPDATE announcements SET status='published', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
        header('Location: admin_announcements.php?archived=1'); exit;
    }

    if ($action === 'delete') {
        // Delete DB row
        $del = $pdo->prepare('DELETE FROM announcements WHERE id=?');
        $del->execute([$id]);

        // Remove image file if exists
        if (!empty($ann['image'])) {
            $imgPath = $PROJECT_ROOT . '/uploads/announcements/' . $ann['image'];
            if (is_file($imgPath)) { @unlink($imgPath); }
        }

        header('Location: admin_announcements.php?archived=1'); exit;
    }

    // Unknown action
    header('Location: admin_announcements.php');
} catch (Throwable $e) {
    error_log('announcement_action error: ' . $e->getMessage());
    header('Location: admin_announcements.php');
}
?>