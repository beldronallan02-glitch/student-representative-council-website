<?php
// complaint_update.php - a lightweight endpoint you can use for AJAX or shared updates
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

header('Content-Type: application/json; charset=utf-8');

$user = current_user();
if (!$user) { http_response_code(401); echo json_encode(['error'=>'Unauthorized']); exit; }

$action = $_POST['action'] ?? '';
$id = intval($_POST['id'] ?? 0);
if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); exit; }

try {
    if ($action === 'change_status' && in_array($user['role'] ?? '', ['mpp','admin'], true)) {
        $new = $_POST['new_status'] ?? '';
        $allowed = ['new','in_review','assigned','in_progress','resolved','closed','archived'];
        if (!in_array($new, $allowed, true)) throw new Exception('Invalid status');
        $u = $pdo->prepare("UPDATE complaints SET status=:st, updated_at = NOW() WHERE id=:id");
        $u->execute([':st'=>$new, ':id'=>$id]);
        $note = $_POST['note'] ?? '';
        $ins = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'status_changed',:note)");
        $ins->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>$note]);
        echo json_encode(['ok'=>true]);
        exit;
    } elseif ($action === 'assign' && in_array($user['role'] ?? '', ['mpp','admin'], true)) {
        $ass = intval($_POST['assigned_to'] ?? 0);
        $u = $pdo->prepare("UPDATE complaints SET assigned_to = :ass, status='assigned', updated_at = NOW() WHERE id=:id");
        $u->execute([':ass'=>$ass, ':id'=>$id]);
        $ins = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'assigned',:note)");
        $ins->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>'Assigned via api']);
        echo json_encode(['ok'=>true]);
        exit;
    } else {
        throw new Exception('Unknown action or insufficient permission');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error'=>$e->getMessage()]);
    exit;
}
