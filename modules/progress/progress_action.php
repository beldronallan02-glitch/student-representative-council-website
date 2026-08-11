<?php
// modules/progress/progress_action.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: progress_manage.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(400); die('Invalid CSRF token'); }

$logid = (int)($_POST['logid'] ?? 0);
$action = trim($_POST['action'] ?? '');
if ($logid <= 0 || !in_array($action, ['delete','archive','unarchive'], true)) { header('Location: progress_manage.php'); exit; }

try {
  // Load log + owner
  $s = $pdo->prepare("SELECT l.logid, l.mppprogressid, l.remarks, mp.userid FROM progress_log l JOIN mpp_progress mp ON mp.mppprogressid = l.mppprogressid WHERE l.logid = ? LIMIT 1");
  $s->execute([$logid]);
  $row = $s->fetch(PDO::FETCH_ASSOC);
  if (!$row) { header('Location: progress_manage.php'); exit; }

  // Ownership check for non-admin
  if ($user['role'] !== 'admin' && (int)$row['userid'] !== (int)$user['id']) {
    header('Location: progress_manage.php');
    exit;
  }

  if ($action === 'archive') {
    $pdo->prepare("UPDATE progress_log SET remarks = 'archived' WHERE logid = ?")->execute([$logid]);
    header('Location: progress_manage.php?msg=archived');
    exit;
  }

  if ($action === 'unarchive') {
    $pdo->prepare("UPDATE progress_log SET remarks = NULL WHERE logid = ?")->execute([$logid]);
    header('Location: progress_manage.php?archived=1&msg=unarchived');
    exit;
  }

  // delete action
  if ($action === 'delete') {
    // Remove images from disk
    $imgs = $pdo->prepare("SELECT imgpath FROM progress_log_images WHERE logid = ?");
    $imgs->execute([$logid]);
    $paths = $imgs->fetchAll(PDO::FETCH_COLUMN);
    $base = realpath(__DIR__ . '/../../uploads/progress');
    foreach ($paths as $p) {
      $full = realpath(__DIR__ . '/../../' . $p);
      if ($full && $base && strpos($full, $base) === 0 && is_file($full)) {
        @unlink($full);
      }
    }
    // Remove image rows
    $pdo->prepare("DELETE FROM progress_log_images WHERE logid = ?")->execute([$logid]);
    // Remove log row
    $pdo->prepare("DELETE FROM progress_log WHERE logid = ?")->execute([$logid]);
    // Attempt to remove empty folder
    $dir = __DIR__ . '/../../uploads/progress/log_' . $logid;
    if (is_dir($dir)) { @rmdir($dir); }

    header('Location: progress_manage.php?msg=deleted');
    exit;
  }

  header('Location: progress_manage.php');
  exit;
} catch (Throwable $e) {
  error_log('progress_action error: ' . $e->getMessage());
  header('Location: progress_manage.php?error=server');
  exit;
}
