<?php
// modules/events/event_delete.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /login.php'); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_events.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF'); }

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: admin_events.php'); exit; }

$stmt = $pdo->prepare("UPDATE events SET status='cancelled', updated_at=NOW() WHERE id=?");
$stmt->execute([$id]);

header('Location: admin_events.php'); exit;
