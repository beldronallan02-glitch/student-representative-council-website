<?php
// modules/facilities/facility_action.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /MPPCONNECT/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: facility_manage.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { http_response_code(400); die('Invalid CSRF token'); }

$id = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');
if ($id <= 0 || !in_array($action, ['archive','unarchive','delete'], true)) { header('Location: facility_manage.php'); exit; }

try {
  // Ensure facility exists
  $s = $pdo->prepare("SELECT * FROM facilities WHERE id=? LIMIT 1");
  $s->execute([$id]);
  $fac = $s->fetch(PDO::FETCH_ASSOC);
  if (!$fac) { header('Location: facility_manage.php?msg=Facility not found'); exit; }

  // Check archiving capability once
  $archivable = false;
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM facilities")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (is_array($cols)) { $archivable = in_array('status', $cols, true) || in_array('is_archived', $cols, true); }
  } catch (Throwable $ie) { $archivable = false; }

  if ($action === 'archive') {
    if (!$archivable) { header('Location: facility_manage.php?msg=Archiving not supported'); exit; }
    // Prefer status column, fallback to is_archived
    try {
      $pdo->prepare("UPDATE facilities SET status='archived' WHERE id=?")->execute([$id]);
    } catch (Throwable $e1) {
      $pdo->prepare("UPDATE facilities SET is_archived=1 WHERE id=?")->execute([$id]);
    }
    header('Location: facility_manage.php?msg=Archived'); exit;
  }

  if ($action === 'unarchive') {
    if (!$archivable) { header('Location: facility_manage.php?msg=Unarchiving not supported'); exit; }
    try {
      $pdo->prepare("UPDATE facilities SET status='active' WHERE id=?")->execute([$id]);
    } catch (Throwable $e2) {
      $pdo->prepare("UPDATE facilities SET is_archived=0 WHERE id=?")->execute([$id]);
    }
    header('Location: facility_manage.php?archived=1&msg=Unarchived'); exit;
  }

  if ($action === 'delete') {
    // Remove images from disk
    $dir = __DIR__ . '/../../uploads/facilities/facility_' . $id;
    if (is_dir($dir)) {
      foreach (glob($dir . '/*') as $f) { if (is_file($f)) { @unlink($f); } }
      @rmdir($dir);
    }
    // Remove bookings (if any)
    try {
      $pdo->prepare("DELETE FROM facility_bookings WHERE facility_id=?")->execute([$id]);
    } catch (Throwable $e3) {
      // ignore if table/constraint handles cascade
    }
    // Delete facility
    $pdo->prepare("DELETE FROM facilities WHERE id=?")->execute([$id]);
    header('Location: facility_manage.php?archived=1&msg=Deleted'); exit;
  }

  header('Location: facility_manage.php'); exit;
} catch (Throwable $e) {
  error_log('[facility_action] error: ' . $e->getMessage());
  header('Location: facility_manage.php?error=server'); exit;
}
