<?php
// modules/facilities/admin_bookings.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) { header('Location: /MPPCONNECT/login.php'); exit; }

$facility_id = (int)($_GET['facility_id'] ?? 0);
$params = [];
$sql = "SELECT b.*, f.name AS facility_name, u.name AS requester FROM facility_bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN users u ON b.user_id=u.id";
if ($facility_id > 0) {
    $sql .= " WHERE b.facility_id = ?";
    $params[] = $facility_id;
}
$stmt = $pdo->prepare($sql . " ORDER BY b.start_at DESC");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$blockedByFacility = [];
// Precompute blocked dates (approved) per facility present in list
try {
  $facilityIds = [];
  foreach ($rows as $r) { $fidRow = (int)($r['facility_id'] ?? 0); if ($fidRow > 0) $facilityIds[$fidRow] = true; }
  $ids = array_keys($facilityIds);
  if (!empty($ids)) {
    $in = implode(',', array_fill(0, count($ids), '?'));
    $ap = $pdo->prepare("SELECT facility_id, start_at, end_at FROM facility_bookings WHERE status='approved' AND facility_id IN ($in)");
    $ap->execute($ids);
    $approvedRows = $ap->fetchAll(PDO::FETCH_ASSOC);
    foreach ($approvedRows as $ar) {
      $fidA = (int)($ar['facility_id'] ?? 0);
      if ($fidA <= 0) continue;
      if (!isset($blockedByFacility[$fidA])) $blockedByFacility[$fidA] = [];
      try {
        $sd = new DateTime($ar['start_at']); $ed = new DateTime($ar['end_at']);
        $sd->setTime(0,0,0); $ed->setTime(0,0,0);
        $cur = clone $sd; while ($cur <= $ed) { $blockedByFacility[$fidA][$cur->format('Y-m-d')] = true; $cur->modify('+1 day'); }
      } catch (Exception $e) { /* ignore */ }
    }
  }
} catch (Throwable $e) { /* ignore */ }

$facility = null;
$summary = ['total'=>0,'pending'=>0,'approved'=>0,'rejected'=>0,'cancelled'=>0];
if ($facility_id > 0) {
  try {
    $fs = $pdo->prepare("SELECT * FROM facilities WHERE id=? LIMIT 1");
    $fs->execute([$facility_id]);
    $facility = $fs->fetch(PDO::FETCH_ASSOC) ?: null;
  } catch (Throwable $e) { $facility = null; }
  try {
    $cs = $pdo->prepare("SELECT status, COUNT(*) c FROM facility_bookings WHERE facility_id=? GROUP BY status");
    $cs->execute([$facility_id]);
    foreach ($cs->fetchAll(PDO::FETCH_ASSOC) as $r) {
      $status = $r['status'] ?? '';
      $count = (int)($r['c'] ?? 0);
      $summary['total'] += $count;
      if (isset($summary[$status])) $summary[$status] = $count;
    }
  } catch (Throwable $e2) { /* ignore */ }
}

$csrf = csrf_token();
// Build availability data for selected facility (single month with navigation)
$blockedDatesSet = [];
$pendingByDate = [];
$calDays = []; $startOffset = 0; $monthTitle = '';
$nowMonth = new DateTime('first day of this month');
$minMonth = (clone $nowMonth)->modify('-12 months');
$maxMonth = (clone $nowMonth)->modify('+12 months');
$ymParam = isset($_GET['ym']) ? trim($_GET['ym']) : '';
$targetMonth = clone $nowMonth;
if ($ymParam && preg_match('/^\d{4}-\d{2}$/', $ymParam)) {
  $try = DateTime::createFromFormat('Y-m-d', $ymParam.'-01');
  if ($try instanceof DateTime) { $targetMonth = $try; }
}
if ($targetMonth < $minMonth) { $targetMonth = clone $minMonth; }
if ($targetMonth > $maxMonth) { $targetMonth = clone $maxMonth; }
// Prev/Next controls
$prevMonth = (clone $targetMonth)->modify('-1 month');
$nextMonth = (clone $targetMonth)->modify('+1 month');
$prevYm = $prevMonth->format('Y-m');
$nextYm = $nextMonth->format('Y-m');
$prevAllowed = $prevMonth >= $minMonth;
$nextAllowed = $nextMonth <= $maxMonth;
if ($facility_id > 0) {
  try {
    $rs = $pdo->prepare("SELECT start_at, end_at, status, u.name AS requester FROM facility_bookings b LEFT JOIN users u ON b.user_id=u.id WHERE b.facility_id=?");
    $rs->execute([$facility_id]);
    $rowsAll = $rs->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rowsAll as $row) {
      try {
        $startD = new \DateTime($row['start_at']);
        $endD = new \DateTime($row['end_at']);
        $startD->setTime(0,0,0); $endD->setTime(0,0,0);
        $cur = clone $startD;
        while ($cur <= $endD) {
          $dkey = $cur->format('Y-m-d');
          if (($row['status'] ?? '') === 'approved') {
            $blockedDatesSet[$dkey] = true;
          } else if (($row['status'] ?? '') === 'pending') {
            $nm = trim($row['requester'] ?? '');
            if ($nm !== '') { $pendingByDate[$dkey][] = $nm; }
          }
          $cur->modify('+1 day');
        }
      } catch (\Exception $ie) { /* skip malformed rows */ }
    }
    // Build calendar structure for selected month
    $firstCur = (clone $targetMonth)->modify('first day of this month');
    $lastCur = (clone $targetMonth)->modify('last day of this month');
    $cursor = clone $firstCur;
    while ($cursor <= $lastCur) {
      $d = $cursor->format('Y-m-d');
      $calDays[] = [
        'date' => $d,
        'label' => $cursor->format('j'),
        'blocked' => !empty($blockedDatesSet[$d]),
        'pending' => $pendingByDate[$d] ?? []
      ];
      $cursor->modify('+1 day');
    }
    $startOffset = (int)$firstCur->format('N') - 1;
    $monthTitle = $firstCur->format('F Y');
  } catch (\Throwable $e) { /* ignore calendar build errors */ }
}
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Bookings — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="../../css/style.css">
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

.admin-layout { display:grid; grid-template-columns: 1fr 1.2fr; gap:18px; align-items:start; }
.calendar { background:#ffffff; border-radius:14px; padding:12px; box-shadow:0 10px 24px rgba(0,0,0,0.08); }
.cal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-weight:700; }
.legend { font-weight:400; font-size:12px; color:#475569; display:flex; gap:10px; align-items:center; }
.legend .dot { width:10px; height:10px; border-radius:999px; display:inline-block; }
.legend .green { background:#22c55e; }
.legend .red { background:#ef4444; }
.legend .yellow { background:#f59e0b; }
.weekday { display:grid; grid-template-columns: repeat(7, 1fr); gap:8px; font-size:13px; color:#6b7280; margin-bottom:8px; }
.cal-grid { display:grid; grid-template-columns: repeat(7, 1fr); gap:8px; }
.day { border:1px solid #eef1f4; border-radius:10px; padding:12px; text-align:center; position:relative; min-height:70px; }
.day.available { background:#dcfce7; color:#166534; border-color:#bbf7d0; }
.day.unavailable { background:#fee2e2; color:#b91c1c; border-color:#fecaca; }
.day.pending { background:#fff7ed; color:#9a3412; border-color:#fde68a; }
.day.empty { background:transparent; border:none; }
.num { font-weight:700; font-size:14px; }
/* pending count under date */
.pending-under { display:inline-flex; align-items:center; justify-content:center; margin-top:6px; width:18px; height:18px; border-radius:999px; background:#f59e0b; color:#fff; font-size:11px; font-weight:800; }
/* calendar pager */
.cal-pager { display:flex; align-items:center; gap:8px; }
.cal-pager .btn { padding:4px 8px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px; }
.cal-pager .btn.disabled { opacity:.5; pointer-events:none; }
@media (max-width: 900px) { .admin-layout { grid-template-columns: 1fr; } }
/* Right-side table styling */
.table-card { background:#fff; border-radius:14px; box-shadow:0 10px 28px rgba(0,0,0,.08); overflow:hidden; }
.bookings-table { width:100%; border-collapse:separate; border-spacing:0; }
.bookings-table th, .bookings-table td { padding:12px 14px; border-top:1px solid #eef1f4; }
.bookings-table thead th { background:#f8fafc; color:#374151; font-weight:700; border-top:none; text-align:left; font-size:13px; }
.bookings-table tbody tr:hover { background:#f9fbff; }
.status-pill { padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px; display:inline-block; }
.status-pending { background:#fef3c7; color:#92400e; }
.status-approved { background:#dcfce7; color:#166534; }
.status-rejected { background:#fee2e2; color:#b91c1c; }
.status-cancelled { background:#e5e7eb; color:#374151; }
.actions { display:flex; gap:8px; justify-content:flex-end; align-items:center; }
/* Ensure consistent button sizes */
.actions .btn { min-width: 120px; text-align:center; }
/* Make 'When' column wider for long dates */
.bookings-table .col-when { width: 42%; }
/* Modal popup styles */
.modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.4); z-index:999; display:none; }
.modal { position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); background:#fff; border-radius:12px; box-shadow:0 10px 28px rgba(0,0,0,.2); padding:16px; width:320px; z-index:1000; display:none; }
.modal-title { font-weight:800; margin-bottom:8px; }
.modal-body { font-size:14px; color:#374151; margin-bottom:12px; }
</style>
</head><body>
<header class="topbar"><div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
<nav class="nav"><a class="btn subtle" href="../../index.php">Home</a><a class="btn subtle" href="facility_manage.php">Back</a></nav></header>

<main class="container">
  <div class="card">
    <h2>Manage Bookings<?= $facility ? ' — '.htmlspecialchars($facility['name']) : '' ?></h2>
    <?php if (!empty($_GET['msg'])): ?>
      <div class="notice" style="margin:10px 0;"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>
    <?php if ($facility): ?>
      <div class="micro" style="margin-bottom:10px;">
        <strong>Summary:</strong>
        Total <?= (int)$summary['total'] ?> ·
        Pending <?= (int)$summary['pending'] ?> ·
        Approved <?= (int)$summary['approved'] ?> ·
        Rejected <?= (int)$summary['rejected'] ?> ·
        Cancelled <?= (int)$summary['cancelled'] ?>
      </div>
    <?php endif; ?>
      <?php if (!empty($_GET['msg'])): ?>
        <div id="modalBackdrop" class="modal-backdrop"></div>
        <div id="modalWarn" class="modal">
          <div class="modal-title">Warning</div>
          <div class="modal-body"><?= htmlspecialchars($_GET['msg']) ?></div>
          <div style="text-align:right"><button class="btn primary" id="modalCloseBtn" type="button">OK</button></div>
        </div>
        <script>
          (function(){
            var b = document.getElementById('modalBackdrop');
            var m = document.getElementById('modalWarn');
            var c = document.getElementById('modalCloseBtn');
            if (b && m) { b.style.display = 'block'; m.style.display = 'block'; }
            if (c) { c.addEventListener('click', function(){ if (b) b.style.display='none'; if (m) m.style.display='none'; }); }
          })();
        </script>
      <?php endif; ?>
    <div class="admin-layout">
      <div>
        <?php if ($facility_id > 0): ?>
          <div class="calendar">
            <div class="cal-head">
              <div>Availability</div>
              <div class="legend"><span class="dot green"></span> Available <span class="dot yellow"></span> Pending <span class="dot red"></span> Approved</div>
            </div>
            <?php if (!empty($calDays)): ?>
              <div class="weekday"><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div></div>
              <div style="display:flex; align-items:center; justify-content:space-between; margin:4px 0 6px;">
                <div style="font-weight:700;"><?= htmlspecialchars($monthTitle) ?></div>
                <div class="cal-pager">
                  <?php if ($prevAllowed): ?>
                    <a class="btn" href="admin_bookings.php?facility_id=<?= (int)$facility_id ?>&ym=<?= htmlspecialchars($prevYm) ?>">Prev</a>
                  <?php else: ?>
                    <span class="btn disabled">Prev</span>
                  <?php endif; ?>
                  <?php if ($nextAllowed): ?>
                    <a class="btn" href="admin_bookings.php?facility_id=<?= (int)$facility_id ?>&ym=<?= htmlspecialchars($nextYm) ?>">Next</a>
                  <?php else: ?>
                    <span class="btn disabled">Next</span>
                  <?php endif; ?>
                </div>
              </div>
              <div class="cal-grid">
                <?php for($i=0; $i<$startOffset; $i++): ?><div class="day empty"></div><?php endfor; ?>
                <?php foreach ($calDays as $d): ?>
                  <?php $hasPending = !empty($d['pending']); $pendingCount = is_array($d['pending']) ? count($d['pending']) : 0; $cls = $d['blocked'] ? 'unavailable' : ($hasPending ? 'pending' : 'available'); ?>
                  <div class="day <?= $cls ?>">
                    <div class="num"><?= htmlspecialchars($d['label']) ?></div>
                    <?php if ($pendingCount > 0): ?>
                      <span class="pending-under"><?= (int)$pendingCount ?></span>
                    <?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="cal-pager cal-bottom" style="margin-top:8px; display:flex; justify-content:space-between;">
                <?php if ($prevAllowed): ?>
                  <a class="btn" href="admin_bookings.php?facility_id=<?= (int)$facility_id ?>&ym=<?= htmlspecialchars($prevYm) ?>">Previous</a>
                <?php else: ?>
                  <span class="btn disabled">Previous</span>
                <?php endif; ?>
                <?php if ($nextAllowed): ?>
                  <a class="btn" href="admin_bookings.php?facility_id=<?= (int)$facility_id ?>&ym=<?= htmlspecialchars($nextYm) ?>">Next</a>
                <?php else: ?>
                  <span class="btn disabled">Next</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="notice">Select a facility to view its availability calendar.</div>
        <?php endif; ?>
      </div>

      <div>
    <?php if (empty($rows)): ?><p class="lead">No bookings.</p><?php else: ?>
      <div class="table-card">
        <div style="padding:16px 16px 0;">
          <h3 style="margin:0;">Bookings List</h3>
        </div>
        <table class="bookings-table">
        <thead><tr><th>Facility</th><th class="col-when">When</th><th>Requester</th><th>Attendees</th><th>Status</th><th style="text-align:right">Actions</th></tr></thead>
        <tbody>
        <?php foreach($rows as $r): 
          // Format date/time clearly with split lines for readability
          $startDT = null; $endDT = null; $dateMain = htmlspecialchars(($r['start_at'] ?? '') . ' — ' . ($r['end_at'] ?? ''));
          $timeLine1 = ''; $timeLine2 = '';
          $sameDay = false;
          try { $startDT = new DateTime($r['start_at']); $endDT = new DateTime($r['end_at']); $sameDay = $startDT->format('Y-m-d') === $endDT->format('Y-m-d'); } catch (Exception $e) { $startDT = null; $endDT = null; }
          if ($startDT && $endDT) {
            if ($sameDay) {
              $dateMain = $startDT->format('j F Y');
              $timeLine1 = $startDT->format('g:i A') . ' — ' . $endDT->format('g:i A');
            } else {
              $dateMain = $startDT->format('j F Y') . ' — ' . $endDT->format('j F Y');
              $timeLine1 = 'Start: ' . $startDT->format('j F Y, g:i A');
              $timeLine2 = 'End: ' . $endDT->format('j F Y, g:i A');
            }
          }
          $status = strtolower(trim($r['status'] ?? ''));
          $statusClass = 'status-pill status-' . ($status ?: 'pending');
        ?>
          <tr>
            <td><?= htmlspecialchars($r['facility_name']) ?></td>
            <td class="col-when">
              <div><?= htmlspecialchars($dateMain) ?></div>
              <?php if ($timeLine1): ?><div class="micro"><?= htmlspecialchars($timeLine1) ?></div><?php endif; ?>
              <?php if ($timeLine2): ?><div class="micro"><?= htmlspecialchars($timeLine2) ?></div><?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['requester'] ?? $r['name']) ?><div class="micro"><?= htmlspecialchars($r['email']) ?></div></td>
            <td><?= htmlspecialchars($r['attendees'] ?: '—') ?></td>
            <td><span class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
            <td style="text-align:right">
              <a class="btn subtle" href="booking_view.php?id=<?= (int)$r['id'] ?>">View</a>
              <?php if ($r['status'] === 'pending'):
                // Determine date-level conflict: any day in range blocked for this facility
                $conflict = false;
                try {
                  $sd = new DateTime($r['start_at']); $ed = new DateTime($r['end_at']);
                  $sd->setTime(0,0,0); $ed->setTime(0,0,0);
                  $cur = clone $sd; $fidRow = (int)$r['facility_id'];
                  while ($cur <= $ed) {
                    $dkey = $cur->format('Y-m-d');
                    if (!empty($blockedByFacility[$fidRow][$dkey])) { $conflict = true; break; }
                    $cur->modify('+1 day');
                  }
                } catch (Exception $e) { $conflict = false; }
              ?>
                <form method="post" action="booking_action.php" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="approve">
                  <button class="btn primary" type="submit"<?= $conflict ? ' style="background:#ef4444;border-color:#dc2626;color:#fff" title="Cannot approve: double booking on same date"' : '' ?>>Approve</button>
                </form>
                <form method="post" action="booking_action.php" style="display:inline;margin-left:6px">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="reject">
                  <button class="btn ghost" type="submit">Reject</button>
                </form>
                <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
                </form>
              <?php elseif ($r['status'] === 'approved'): ?>
                <form method="post" action="booking_action.php" style="display:inline" onsubmit="return confirm('Cancel booking?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="cancel">
                  <button class="btn ghost" type="submit">Cancel</button>
                </form>
                <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
                </form>
               <?php elseif ($r['status'] === 'rejected' || $r['status'] === 'cancelled'): ?>
                 <form method="post" action="booking_action.php" style="display:inline" onsubmit="return confirm('Approve this booking again?');">
                   <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                   <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                   <input type="hidden" name="action" value="approve">
                   <button class="btn primary" type="submit">Approve Again</button>
                 </form>
                  <form method="post" action="booking_action.php" style="display:inline;margin-left:6px" onsubmit="return confirm('Delete this booking? This cannot be undone.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn" type="submit" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</button>
                  </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        </table>
      </div>
    <?php endif; ?>
      </div>
    </div>
  </div>
</main>
</body></html>
