<?php
// modules/progress/progress_save.php (new schema)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: progress_manage.php');
    exit;
}

if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    http_response_code(400);
    die('Invalid CSRF token');
}

// Inputs
$logid       = (int)($_GET['logid'] ?? 0);
$title       = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$statusIn    = trim($_POST['status'] ?? 'planned');

// Map legacy/form values to new status enum
switch ($statusIn) {
    case 'draft': $status = 'planned'; break;
    case 'published': $status = 'completed'; break;
    case 'planned':
    case 'in_progress':
    case 'completed':
    case 'delayed':
        $status = $statusIn; break;
    default: $status = 'planned';
}

if ($title === '') {
    header('Location: progress_manage.php?error=title_required');
    exit;
}

try {
    // Ensure mpp_progress exists for this user
    $stmt = $pdo->prepare("SELECT mppprogressid FROM mpp_progress WHERE userid = ? ORDER BY mppprogressid DESC LIMIT 1");
    $stmt->execute([$user['id']]);
    $mppprogressid = (int)$stmt->fetchColumn();
    if ($mppprogressid <= 0) {
        $insMp = $pdo->prepare("INSERT INTO mpp_progress (userid, start_date, created_at) VALUES (?, CURDATE(), NOW())");
        $insMp->execute([$user['id']]);
        $mppprogressid = (int)$pdo->lastInsertId();
    }

    // Create or update progress_log
    if ($logid > 0) {
        // Verify ownership for non-admin
        $chk = $pdo->prepare("SELECT mp.userid FROM progress_log l JOIN mpp_progress mp ON mp.mppprogressid = l.mppprogressid WHERE l.logid = ? LIMIT 1");
        $chk->execute([$logid]);
        $ownerId = (int)$chk->fetchColumn();
        if (!$ownerId || ($user['role'] !== 'admin' && $ownerId !== (int)$user['id'])) {
            header('Location: progress_manage.php');
            exit;
        }

        $upd = $pdo->prepare("UPDATE progress_log SET title = ?, description = ?, status = ?, logged_at = NOW() WHERE logid = ?");
        $upd->execute([$title, $description, $status, $logid]);
    } else {
        $ins = $pdo->prepare("INSERT INTO progress_log (mppprogressid, title, description, status, remarks, logged_at) VALUES (?, ?, ?, ?, NULL, NOW())");
        $ins->execute([$mppprogressid, $title, $description, $status]);
        $logid = (int)$pdo->lastInsertId();
    }

    // Handle image uploads into uploads/progress/log_{logid}/
    if (!empty($_FILES['images']['tmp_name']) && is_array($_FILES['images']['tmp_name'])) {
        $folder = __DIR__ . '/../../uploads/progress/log_' . $logid . '/';
        if (!is_dir($folder)) { @mkdir($folder, 0755, true); }

        $allowedMime = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];
        $maxSize = 5 * 1024 * 1024; // 5MB

        foreach ($_FILES['images']['tmp_name'] as $i => $tmp) {
            if (!$tmp || !is_uploaded_file($tmp)) { continue; }
            if (($_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { continue; }
            if (($_FILES['images']['size'][$i] ?? 0) > $maxSize) { continue; }

            $mime = @mime_content_type($tmp);
            if (!isset($allowedMime[$mime])) { continue; }

            $filename = sprintf('%d_%s.%s', time(), bin2hex(random_bytes(6)), $allowedMime[$mime]);
            $dest = $folder . $filename;
            if (!@move_uploaded_file($tmp, $dest)) { continue; }

            // Store relative path for serving
            $rel = 'uploads/progress/log_' . $logid . '/' . $filename;
            $imgIns = $pdo->prepare("INSERT INTO progress_log_images (logid, imgpath, uploaded_at) VALUES (?, ?, NOW())");
            $imgIns->execute([$logid, $rel]);
        }
    }

    header('Location: progress_manage.php?saved=1');
    exit;

} catch (Throwable $e) {
    error_log('progress_save new schema error: ' . $e->getMessage());
    header('Location: progress_manage.php?error=server');
    exit;
}
