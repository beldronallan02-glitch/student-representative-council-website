<?php
// complaint_delete.php - soft delete (withdraw) a complaint (student) or admin archival

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\','/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
function root_url($path = '') { global $webRoot; $path = ltrim($path, '/'); return ($webRoot ?: '') . ($path ? "/{$path}" : ''); }

$user = current_user();
if (!$user) { header('Location: ' . root_url('login.php')); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: complaint_list.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id=:id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) throw new Exception('Not found');

    $is_admin = in_array($user['role'] ?? '', ['mpp','admin'], true);
    if (!$is_admin && $c['user_id'] != $user['id']) { header('HTTP/1.1 403 Forbidden'); echo 'Forbidden'; exit; }

    // hard delete: remove complaint (related rows cascade via FKs)
    $pdo->beginTransaction();
    $del = $pdo->prepare("DELETE FROM complaints WHERE id = :id LIMIT 1");
    $del->execute([':id'=>$id]);
    $pdo->commit();

    $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
    if ($redirect === 'admin') {
        header('Location: ' . root_url('modules/complaint/admin_complaints.php')); exit;
    }
    header('Location: complaint_list.php'); exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log('complaint_delete error: '.$e->getMessage());
    $redirect = isset($_GET['redirect']) ? trim($_GET['redirect']) : '';
    if ($redirect === 'admin') {
        header('Location: ' . root_url('modules/complaint/admin_complaints.php')); exit;
    }
    header('Location: complaint_list.php'); exit;
}
