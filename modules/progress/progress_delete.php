<?php
// modules/progress/progress_delete.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: progress_manage.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF'); }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: progress_manage.php'); exit; }

try {
    // check owner unless admin
    $s = $pdo->prepare("SELECT user_id FROM progress_entries WHERE id=? LIMIT 1");
    $s->execute([$id]); $owner = $s->fetchColumn();
    if (!$owner) { header('Location: progress_manage.php'); exit; }
    if ($user['role'] !== 'admin' && (int)$owner !== (int)$user['id']) { header('Location: progress_manage.php'); exit; }

    // delete images physically
    $imgS = $pdo->prepare("SELECT filename FROM progress_images WHERE progress_id=?");
    $imgS->execute([$id]);
    $files = $imgS->fetchAll(PDO::FETCH_COLUMN);
    foreach ($files as $f) {
        $p = __DIR__ . '/../../uploads/progress/' . $f;
        if (is_file($p)) @unlink($p);
    }

    // delete entries (images will be cascade deleted)
    $d = $pdo->prepare("DELETE FROM progress_entries WHERE id=?");
    $d->execute([$id]);

    header('Location: progress_manage.php');
    exit;
} catch (Exception $e) {
    error_log("progress_delete error: ".$e->getMessage());
    header('Location: progress_manage.php?error=server');
    exit;
}
