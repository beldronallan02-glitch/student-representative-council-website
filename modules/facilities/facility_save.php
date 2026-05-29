<?php
// modules/facilities/facility_save.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /MPPCONNECT/login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: facility_manage.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF'); }

try {
    if (!empty($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        $del = $pdo->prepare("DELETE FROM facilities WHERE id=?");
        $del->execute([$id]);
        header('Location: facility_manage.php'); exit;
    }

    // create or update
    $id = (int)($_GET['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = ($_POST['capacity'] !== '' ? (int)$_POST['capacity'] : null);

    if ($name === '') { throw new Exception('Name required'); }

    if ($id > 0) {
        $up = $pdo->prepare("UPDATE facilities SET name=?, description=?, location=?, capacity=?, created_by=?, created_at=created_at WHERE id=?");
        $up->execute([$name,$description,$location,$capacity,$user['id'],$id]);
    } else {
        $ins = $pdo->prepare("INSERT INTO facilities (name, description, location, capacity, created_by) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$name,$description,$location,$capacity,$user['id']]);
    }

    // Determine facility ID for uploads
    $facilityId = $id > 0 ? $id : (int)$pdo->lastInsertId();

    // Handle image uploads (optional)
    if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
        $allowed = ['jpg','jpeg','png','gif','webp'];
        $baseDir = __DIR__ . '/../../uploads/facilities/facility_' . $facilityId;
        if (!is_dir($baseDir)) { @mkdir($baseDir, 0775, true); }

        $count = count($_FILES['images']['name']);
        for ($i = 0; $i < $count; $i++) {
            $error = $_FILES['images']['error'][$i] ?? UPLOAD_ERR_NO_FILE;
            if ($error !== UPLOAD_ERR_OK) { continue; }
            $tmp = $_FILES['images']['tmp_name'][$i] ?? '';
            $orig = $_FILES['images']['name'][$i] ?? '';
            $size = (int)($_FILES['images']['size'][$i] ?? 0);
            if (!$tmp || $size <= 0) { continue; }

            // Basic validation
            $ext = strtolower(pathinfo($orig, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed, true)) { continue; }
            if ($size > 5 * 1024 * 1024) { continue; } // 5MB limit

            $safeBase = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($orig, PATHINFO_FILENAME));
            $dest = $baseDir . '/' . $safeBase . '_' . uniqid('', true) . '.' . $ext;
            @move_uploaded_file($tmp, $dest);
        }
    }

    header('Location: facility_manage.php');
} catch (Exception $e) {
    error_log("[MPPCONNECT] facility_save error: " . $e->getMessage());
    header('Location: facility_manage.php?error=' . urlencode($e->getMessage()));
}
