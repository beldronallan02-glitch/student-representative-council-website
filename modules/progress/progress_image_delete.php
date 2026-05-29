<?php
// modules/progress/progress_image_delete.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();

/* ===============================
   ACCESS CONTROL
================================ */
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /MPPCONNECT/login.php');
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

/* ===============================
   INPUT
================================ */
$image_id = (int)($_POST['image_id'] ?? 0);
if ($image_id <= 0) {
    header('Location: progress_manage.php');
    exit;
}

try {

    /* ===============================
       LOAD IMAGE + OWNER
    ================================ */
    $stmt = $pdo->prepare("
        SELECT 
            i.id,
            i.filename,
            p.id AS progress_id,
            p.user_id
        FROM progress_images i
        JOIN progress_entries p ON p.id = i.progress_id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->execute([$image_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        header('Location: progress_manage.php');
        exit;
    }

    /* ===============================
       PERMISSION CHECK
    ================================ */
    if (
        $user['role'] !== 'admin' &&
        (int)$row['user_id'] !== (int)$user['id']
    ) {
        header('Location: progress_manage.php');
        exit;
    }

    /* ===============================
       DELETE FILE
    ================================ */
    $filePath = realpath(__DIR__ . '/../../uploads/progress/' . $row['filename']);

    if ($filePath && strpos($filePath, realpath(__DIR__ . '/../../uploads/progress')) === 0) {
        if (is_file($filePath)) {
            @unlink($filePath);
        }
    }

    /* ===============================
       DELETE DB RECORD
    ================================ */
    $del = $pdo->prepare("DELETE FROM progress_images WHERE id = ?");
    $del->execute([$image_id]);

    header('Location: progress_form.php?action=edit&id=' . (int)$row['progress_id']);
    exit;

} catch (Throwable $e) {

    error_log('progress_image_delete error: ' . $e->getMessage());

    header('Location: progress_manage.php?error=server');
    exit;
}
