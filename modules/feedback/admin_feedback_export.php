<?php
// modules/feedback/admin_feedback_export.php
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('Location: /login.php'); exit; }

$stmt = $pdo->prepare("SELECT f.id, e.title AS event_title, p.title AS prompt_title, u.name AS user_name, f.rating, f.comment, f.anonymous, f.created_at FROM feedbacks f LEFT JOIN events e ON e.id=f.event_id LEFT JOIN feedback_prompts p ON p.id=f.prompt_id LEFT JOIN users u ON u.id=f.user_id WHERE f.deleted_at IS NULL ORDER BY f.created_at DESC");
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=feedback_export_' . date('Ymd_His') . '.csv');

$out = fopen('php://output', 'w');
fputcsv($out, ['id','event_title','prompt_title','user','rating','comment','anonymous','created_at']);
foreach ($rows as $r) {
    fputcsv($out, [$r['id'], $r['event_title'], $r['prompt_title'], $r['user_name'],$r['rating'],$r['comment'], $r['anonymous'], $r['created_at']]);
}
fclose($out);
exit;
