<?php
// modules/feedback/feedback_delete.php
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$user = current_user();
if (!$user) { header('Location: /MPPCONNECT/login.php'); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT * FROM feedbacks WHERE id=:id LIMIT 1");
$stmt->execute([':id'=>$id]);
$fb = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$fb) { header('Location: feedback_list.php'); exit; }
if ($fb['user_id'] != $user['id'] && !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('HTTP/1.1 403 Forbidden'); echo 'Forbidden'; exit; }

$upd = $pdo->prepare("UPDATE feedbacks SET deleted_at = NOW() WHERE id=:id");
$upd->execute([':id'=>$id]);
header('Location: feedback_list.php'); exit;
