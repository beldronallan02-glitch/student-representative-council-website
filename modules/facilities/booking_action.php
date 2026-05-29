<?php
// modules/facilities/booking_action.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user) { header('Location: /MPPCONNECT/login.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: admin_bookings.php'); exit; }
if (!verify_csrf($_POST['csrf_token'] ?? '')) { die('Invalid CSRF'); }

$id = (int)($_POST['id'] ?? 0);
$action = $_POST['action'] ?? '';

if ($id <= 0) { header('Location: admin_bookings.php'); exit; }

try {
    // Fetch booking
    $s = $pdo->prepare("SELECT * FROM facility_bookings WHERE id=? LIMIT 1");
    $s->execute([$id]);
    $b = $s->fetch(PDO::FETCH_ASSOC);
    if (!$b) { header('Location: admin_bookings.php'); exit; }

    if ($action === 'approve') {
        // Only mpp/admin can approve
        if (!in_array($user['role'], ['mpp','admin'], true)) { die('Forbidden'); }
        // Date-level conflict: disallow any booking on the same date range
        $startDay = (new DateTime($b['start_at']))->format('Y-m-d');
        $endDay = (new DateTime($b['end_at']))->format('Y-m-d');
        $ovDay = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings WHERE facility_id=? AND status='approved' AND NOT (DATE(end_at) < ? OR DATE(start_at) > ?)");
        $ovDay->execute([(int)$b['facility_id'], $startDay, $endDay]);
        $countDay = (int)$ovDay->fetchColumn();
        if ($countDay > 0) {
            $msg = urlencode('Approval blocked: double booking on same date');
            header('Location: admin_bookings.php?facility_id='.(int)$b['facility_id'].'&msg='.$msg);
            exit;
        }
        // Time-level conflict (defensive): ensure no overlapping approved booking in time
        $ov = $pdo->prepare("SELECT COUNT(*) FROM facility_bookings WHERE facility_id=? AND status='approved' AND NOT (end_at <= ? OR start_at >= ?)");
        $ov->execute([$b['facility_id'], $b['start_at'], $b['end_at']]);
        $count = (int)$ov->fetchColumn();
        if ($count > 0) {
            $msg = urlencode('Approval blocked: conflicts with existing approved slot');
            header('Location: admin_bookings.php?facility_id='.(int)$b['facility_id'].'&msg='.$msg);
            exit;
        }
        $up = $pdo->prepare("UPDATE facility_bookings SET status='approved', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
    } elseif ($action === 'reject') {
        if (!in_array($user['role'], ['mpp','admin'], true)) { die('Forbidden'); }
        $up = $pdo->prepare("UPDATE facility_bookings SET status='rejected', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
    } elseif ($action === 'cancel') {
        // requester (student) or mpp/admin can cancel
        if ($user['role'] === 'student' && (int)$b['user_id'] !== (int)$user['id']) { die('Forbidden'); }
        $up = $pdo->prepare("UPDATE facility_bookings SET status='cancelled', updated_at=NOW() WHERE id=?");
        $up->execute([$id]);
    } elseif ($action === 'delete') {
        // only mpp/admin can delete bookings
        if (!in_array($user['role'], ['mpp','admin'], true)) { die('Forbidden'); }
        $del = $pdo->prepare("DELETE FROM facility_bookings WHERE id=?");
        $del->execute([$id]);
    }

    // redirect back (preserve facility context for calendar)
    if (in_array($user['role'], ['mpp','admin'], true)) {
        $fid = (int)($b['facility_id'] ?? 0);
        if ($fid > 0) {
            header('Location: admin_bookings.php?facility_id=' . $fid);
        } else {
            header('Location: admin_bookings.php');
        }
    }
    else header('Location: student_bookings.php');
    exit;
} catch (Exception $e) {
    error_log("[MPPCONNECT] booking_action error: " . $e->getMessage());
    header('Location: admin_bookings.php');
    exit;
}
