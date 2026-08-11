<?php
// modules/events/event_action.php — handle archive/unarchive/delete for events
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: /login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_events.php');
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$token = $_POST['csrf_token'] ?? '';

if ($id <= 0 || !verify_csrf($token)) {
    header('Location: admin_events.php');
    exit;
}

try {
    if ($action === 'archive') {
        $stmt = $pdo->prepare("UPDATE events SET status = 'archived' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin_events.php');
        exit;
    } elseif ($action === 'unarchive') {
        // Restore to a non-archived status; using 'draft' to re-list under active
        $stmt = $pdo->prepare("UPDATE events SET status = 'draft' WHERE id = ?");
        $stmt->execute([$id]);
        header('Location: admin_events.php?archived=1');
        exit;
    } elseif ($action === 'delete') {
        // Delete registrations first, then the event
        $pdo->beginTransaction();
        $delRegs = $pdo->prepare("DELETE FROM event_registrations WHERE event_id = ?");
        $delRegs->execute([$id]);
        $delEvent = $pdo->prepare("DELETE FROM events WHERE id = ?");
        $delEvent->execute([$id]);
        $pdo->commit();
        header('Location: admin_events.php?archived=1');
        exit;
    }
} catch (Throwable $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

header('Location: admin_events.php');
exit;
?>