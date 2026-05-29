<?php
// modules/facilities/booking_view.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: student_bookings.php'); exit; }

$stmt = $pdo->prepare("SELECT b.*, f.*, f.name AS facility_name, u.name AS requester_name FROM facility_bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN users u ON b.user_id=u.id WHERE b.id=? LIMIT 1");
$stmt->execute([$id]);
$b = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$b) { header('Location: student_bookings.php'); exit; }

// authorization: student only sees own, admin/mpp see all
if ($user && $user['role']==='student' && (int)$b['user_id'] !== (int)($user['id'] ?? 0)) {
    header('Location: student_bookings.php'); exit;
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Booking — <?= htmlspecialchars($b['facility_name'] ?? '') ?></title>
<link rel="stylesheet" href="../../css/style.css">
<style>
.booking-wrap { max-width: 1100px; margin: 24px auto; }
.booking-header { display:flex; justify-content:flex-end; align-items:center; gap:12px; margin-bottom: 12px; }
.booking-title { font-size: 20px; font-weight: 800; margin: 0; }
.status-pill { padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px; }
.status-pending { background:#fef3c7; color:#92400e; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#b91c1c; }
.status-cancelled { background:#e5e7eb; color:#374151; }
.page-split { display:grid; grid-template-columns: 1.4fr 1fr; gap: 18px; }
.booking-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.block { background:#fff; border-radius:14px; padding:16px; box-shadow:0 10px 28px rgba(0,0,0,.08); }
.label { font-size:12px; color:#6b7280; margin-bottom:6px; }
.value { font-size:16px; color:#111827; font-weight:600; }
.subvalue { font-size:13px; color:#374151; }
.actions { display:flex; gap:8px; align-items:center; margin-top:16px; }
.facility-image { width:100%; height:300px; object-fit:cover; border-radius:12px; }
/* removed pills (place/capacity) per requirement */
</style>
</head><body>
<header class="topbar"><div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div></header>

<?php
$status = strtolower(trim($b['status'] ?? ''));
$statusClass = 'status-pill status-' . ($status ?: 'pending');
try {
  $startDT = new DateTime($b['start_at']);
  $endDT = new DateTime($b['end_at']);
} catch (Exception $e) {
  $startDT = null; $endDT = null;
}
$dateStr = ($startDT ? $startDT->format('D, M j, Y') : htmlspecialchars($b['start_at']));
$timeStr = ($startDT && $endDT) ? $startDT->format('g:i A') . ' — ' . $endDT->format('g:i A') : htmlspecialchars(($b['start_at'] ?? '') . ' — ' . ($b['end_at'] ?? ''));
$durationStr = '';
if ($startDT && $endDT) {
  $diff = $startDT->diff($endDT);
  $h = (int)$diff->h + ($diff->d * 24);
  $m = (int)$diff->i;
  if ($h > 0 && $m > 0) $durationStr = $h . 'h ' . $m . 'm';
  elseif ($h > 0) $durationStr = $h . 'h';
  else $durationStr = $m . 'm';
}
?>

<?php
// Build facility image path (first image if available)
$facilityId = (int)($b['facility_id'] ?? 0);
$imageWeb = null;
if ($facilityId > 0) {
  $dir = __DIR__ . '/../../uploads/facilities/facility_' . $facilityId;
  if (is_dir($dir)) {
    $files = scandir($dir);
    foreach ($files as $fn) {
      if ($fn === '.' || $fn === '..') continue;
      $ext = strtolower(pathinfo($fn, PATHINFO_EXTENSION));
      if (in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) { $imageWeb = '../../uploads/facilities/facility_' . $facilityId . '/' . $fn; break; }
    }
  }
}
// left panel no longer shows place/capacity/description
?>

<main class="container booking-wrap">
  <div class="card">
    <div class="booking-header">
      <span class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
    </div>

    <div class="page-split">
      <div>
        <div class="block" style="overflow:hidden;">
          <h2 class="booking-title" style="margin-bottom:8px;"><?= htmlspecialchars($b['facility_name']) ?></h2>
          <?php if ($imageWeb): ?>
            <img class="facility-image" src="<?= htmlspecialchars($imageWeb) ?>" alt="<?= htmlspecialchars($b['facility_name']) ?>">
          <?php else: ?>
            <div style="height:300px; display:flex; align-items:center; justify-content:center; color:#6b7280;">No image available</div>
          <?php endif; ?>
        </div>
      </div>
      <div>
        <div class="booking-grid">
          <div class="block">
            <div class="label">Date</div>
            <div class="value"><?= htmlspecialchars($dateStr) ?></div>
            <div class="subvalue">Time: <?= htmlspecialchars($timeStr) ?><?= $durationStr ? ' · Duration ' . htmlspecialchars($durationStr) : '' ?></div>
          </div>
          <div class="block">
            <div class="label">Requester</div>
            <div class="value"><?= htmlspecialchars($b['requester_name'] ?? $b['name']) ?></div>
            <div class="subvalue"><?= htmlspecialchars($b['email']) ?></div>
          </div>
          <div class="block">
            <div class="label">Attendees</div>
            <div class="value"><?= htmlspecialchars($b['attendees'] ?: '—') ?></div>
          </div>
          <div class="block">
            <div class="label">Purpose</div>
            <div class="subvalue"><?= nl2br(htmlspecialchars($b['purpose'] ?: '—')) ?></div>
          </div>
        </div>

        <div class="actions">
          <a class="btn subtle" href="<?= ($user && in_array($user['role'], ['mpp','admin'], true)) ? ('admin_bookings.php?facility_id=' . (int)($b['facility_id'] ?? 0)) : 'student_bookings.php' ?>">Back</a>
          <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
            <?php if ($status === 'pending'): ?>
              <form method="post" action="booking_action.php" style="display:inline">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn primary" type="submit">Approve</button>
              </form>
              <form method="post" action="booking_action.php" style="display:inline;margin-left:6px">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="reject">
                <button class="btn ghost" type="submit">Reject</button>
              </form>
              <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
              </form>
            <?php elseif ($status === 'approved'): ?>
              <form method="post" action="booking_action.php" style="display:inline" onsubmit="return confirm('Cancel booking?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="cancel">
                <button class="btn ghost" type="submit">Cancel</button>
              </form>
              <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
              </form>
            <?php elseif ($status === 'rejected' || $status === 'cancelled'): ?>
              <form method="post" action="booking_action.php" style="display:inline" onsubmit="return confirm('Approve this booking again?');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="approve">
                <button class="btn primary" type="submit">Approve Again</button>
              </form>
              <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
              </form>
            <?php endif; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
</body></html>
