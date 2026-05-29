<?php
// modules/facilities/booking_request.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user) { header('Location: /MPPCONNECT/login.php'); exit; } // require login to request

$fid = (int)($_GET['facility_id'] ?? 0);
if ($fid <= 0) { header('Location: facilities_public.php'); exit; }

// load facility
$stmt = $pdo->prepare("SELECT * FROM facilities WHERE id=? LIMIT 1");
$stmt->execute([$fid]);
$facility = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$facility) { header('Location: facilities_public.php'); exit; }

$blockedDatesSet = [];
$pendingByDate = [];
try {
  $rs = $pdo->prepare("SELECT b.start_at, b.end_at, b.status, u.name AS requester FROM facility_bookings b LEFT JOIN users u ON u.id=b.user_id WHERE b.facility_id=?");
  $rs->execute([$fid]);
  $rows = $rs->fetchAll(PDO::FETCH_ASSOC);
  foreach ($rows as $row) {
    try {
      $startD = new \DateTime($row['start_at']);
      $endD = new \DateTime($row['end_at']);
      // Normalize to date boundaries
      $startD->setTime(0,0,0);
      $endD->setTime(0,0,0);
      $cur = clone $startD;
      while ($cur <= $endD) {
        $dkey = $cur->format('Y-m-d');
        if (($row['status'] ?? '') === 'approved') {
          $blockedDatesSet[$dkey] = true;
        } elseif (($row['status'] ?? '') === 'pending') {
          $nm = trim($row['requester'] ?? '');
          if ($nm !== '') { $pendingByDate[$dkey][] = $nm; }
        }
        $cur->modify('+1 day');
      }
    } catch (\Exception $ie) { /* skip malformed rows */ }
  }
} catch (\Throwable $e) { /* ignore availability if query fails */ }

// Build availability calendar for a selected month
$calDays = []; $startOffset = 0; $monthTitle = '';
// Determine target month via ?ym=YYYY-MM with bounds (current +/- 12 months)
$nowMonth = new \DateTime('first day of this month');
$minMonth = (clone $nowMonth)->modify('-12 months');
$maxMonth = (clone $nowMonth)->modify('+12 months');
$ymParam = isset($_GET['ym']) ? trim($_GET['ym']) : '';
$targetMonth = clone $nowMonth;
if ($ymParam && preg_match('/^\d{4}-\d{2}$/', $ymParam)) {
  $try = \DateTime::createFromFormat('Y-m-d', $ymParam.'-01');
  if ($try instanceof \DateTime) { $targetMonth = $try; }
}
if ($targetMonth < $minMonth) { $targetMonth = clone $minMonth; }
if ($targetMonth > $maxMonth) { $targetMonth = clone $maxMonth; }
// Prev/Next links
$prevMonth = (clone $targetMonth)->modify('-1 month');
$nextMonth = (clone $targetMonth)->modify('+1 month');
$prevYm = $prevMonth->format('Y-m');
$nextYm = $nextMonth->format('Y-m');
$prevAllowed = $prevMonth >= $minMonth;
$nextAllowed = $nextMonth <= $maxMonth;
try {
  // Selected month
  $firstDay = (clone $targetMonth)->modify('first day of this month');
  $lastDay = (clone $targetMonth)->modify('last day of this month');
  $cursor = clone $firstDay;
  while ($cursor <= $lastDay) {
    $d = $cursor->format('Y-m-d');
    $calDays[] = [
      'date' => $d,
      'label' => $cursor->format('j'),
      'blocked' => !empty($blockedDatesSet[$d]),
      'pending' => $pendingByDate[$d] ?? []
    ];
    $cursor->modify('+1 day');
  }
  $startOffset = (int)$firstDay->format('N') - 1;
  $monthTitle = $firstDay->format('F Y');
} catch (\Exception $e) { /* ignore calendar build errors */ }

$error = null;
// Preserve inputs for UX
$start_at_input = '';
$end_at_input = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) { $error = 'Invalid CSRF'; }
    else {
        $name = trim($_POST['name'] ?? $user['name']);
        $email = trim($_POST['email'] ?? $user['email']);
        $purpose = trim($_POST['purpose'] ?? '');
    $start_at_input = trim($_POST['start_at'] ?? '');
    $end_at_input = trim($_POST['end_at'] ?? '');
        $attendees = ($_POST['attendees'] !== '' ? (int)$_POST['attendees'] : null);

    // Normalize datetime-local (YYYY-MM-DDTHH:MM[:SS]) to SQL format (YYYY-MM-DD HH:MM:SS)
    $start_dt = null; $end_dt = null; $start_at_sql = ''; $end_at_sql = '';
    if ($start_at_input !== '') {
      $start_dt = \DateTime::createFromFormat('Y-m-d\TH:i', $start_at_input) ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $start_at_input);
      if (!$start_dt) { $ts = strtotime($start_at_input); if ($ts) { $start_dt = new \DateTime('@' . $ts); $start_dt->setTimezone(new \DateTimeZone(date_default_timezone_get())); } }
      if ($start_dt) { $start_at_sql = $start_dt->format('Y-m-d H:i:s'); }
    }
    if ($end_at_input !== '') {
      $end_dt = \DateTime::createFromFormat('Y-m-d\TH:i', $end_at_input) ?: \DateTime::createFromFormat('Y-m-d\TH:i:s', $end_at_input);
      if (!$end_dt) { $ts = strtotime($end_at_input); if ($ts) { $end_dt = new \DateTime('@' . $ts); $end_dt->setTimezone(new \DateTimeZone(date_default_timezone_get())); } }
      if ($end_dt) { $end_at_sql = $end_dt->format('Y-m-d H:i:s'); }
    }

    if ($name === '' || $email === '' || $start_at_sql === '' || $end_at_sql === '') {
            $error = 'Please fill all required fields.';
        } else {
            // check times valid
      if ($end_dt->getTimestamp() <= $start_dt->getTimestamp()) {
                $error = 'End must be after start.';
            } else {
          // block dates that are unavailable for the facility (per-day rule)
          $start_day = $start_dt->format('Y-m-d');
          $end_day = $end_dt->format('Y-m-d');
          if (!empty($blockedDatesSet[$start_day]) || !empty($blockedDatesSet[$end_day])) {
            $error = 'Selected date is unavailable for this facility.';
          } else {
                // check overlapping approved bookings for same facility
                $ov = $pdo->prepare("
                  SELECT COUNT(*) FROM facility_bookings
                  WHERE facility_id=? AND status='approved' AND NOT (end_at <= ? OR start_at >= ?)
                ");
        $ov->execute([$fid, $end_at_sql, $start_at_sql]);
                $count = (int)$ov->fetchColumn();
                if ($count > 0) {
                    $error = 'Time slot not available (conflicts with approved booking).';
                } else {
                    $ins = $pdo->prepare("INSERT INTO facility_bookings (facility_id, user_id, name, email, purpose, start_at, end_at, attendees, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())");
          $ins->execute([$fid, $user['id'] ?? null, $name, $email, $purpose, $start_at_sql, $end_at_sql, $attendees]);
                    header('Location: student_bookings.php'); exit;
                }
          }
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Request Booking — <?= htmlspecialchars($facility['name']) ?></title>
<link rel="stylesheet" href="../../css/style.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<style>
.calendar { background:#ffffff; border-radius:14px; padding:12px; box-shadow:0 10px 24px rgba(0,0,0,0.08); margin:14px 0; }
.cal-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; font-weight:700; }
.legend { font-weight:400; font-size:12px; color:#475569; display:flex; gap:10px; align-items:center; }
.legend .dot { width:10px; height:10px; border-radius:999px; display:inline-block; }
.legend .green { background:#22c55e; }
.legend .red { background:#ef4444; }
.legend .yellow { background:#f59e0b; }
.weekday { display:grid; grid-template-columns: repeat(7, 1fr); gap:8px; font-size:13px; color:#6b7280; margin-bottom:8px; }
.cal-grid { display:grid; grid-template-columns: repeat(7, 1fr); gap:8px; }
.day { border:1px solid #eef1f4; border-radius:10px; padding:12px; text-align:center; position:relative; min-height:70px; }
.day.available { background:#dcfce7; color:#166534; border-color:#bbf7d0; cursor:pointer; }
.day.unavailable { background:#fee2e2; color:#b91c1c; border-color:#fecaca; cursor:not-allowed; pointer-events:none; }
.day.pending { background:#fff7ed; color:#9a3412; border-color:#fde68a; cursor:pointer; }
.day.empty { background:transparent; border:none; }
.num { font-weight:700; font-size:14px; }
/* Pending count badge */
.badge-pending { position:absolute; top:4px; right:4px; width:18px; height:18px; border-radius:999px; background:#f59e0b; color:#fff; font-size:11px; font-weight:800; display:flex; align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.15); }
/* Pending count under date number */
.pending-under { display:inline-flex; align-items:center; justify-content:center; margin-top:6px; width:18px; height:18px; border-radius:999px; background:#f59e0b; color:#fff; font-size:11px; font-weight:800; }
/* Calendar pager */
.cal-pager { display:flex; align-items:center; gap:8px; }
.cal-pager .btn { padding:4px 8px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; font-size:12px; }
.cal-pager .btn.disabled { opacity:.5; pointer-events:none; }
/* Two-column layout */
.book-layout { display:grid; grid-template-columns: 1fr 1fr; gap:18px; align-items:start; }
@media (max-width: 800px) { .book-layout { grid-template-columns: 1fr; } }
</style>
</head><body>
<header class="topbar"><div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div></header>

<main class="container">
  <div class="card">
    <h2>Request booking — <?= htmlspecialchars($facility['name']) ?></h2>
    <?php if ($error): ?><div style="color:#b00020"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if (!empty($blockedDatesSet)): ?>
      <div class="notice" style="margin:10px 0;">
        <strong>Unavailable dates:</strong>
        <?php
          $fmt = [];
          foreach (array_keys($blockedDatesSet) as $d) {
            try { $dt = new \DateTime($d); $fmt[] = $dt->format('j F Y'); } catch (\Exception $e) { $fmt[] = htmlspecialchars($d); }
          }
          echo htmlspecialchars(implode(' · ', $fmt));
        ?>
      </div>
    <?php endif; ?>

    <div class="book-layout">
      <div>
          <div class="calendar">
              <div class="cal-head">
              <div>Availability</div>
              <div class="legend"><span class="dot green"></span> Available <span class="dot yellow"></span> Pending <span class="dot red"></span> Approved</div>
            </div>
            <div class="weekday"><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div><div>Sun</div></div>
            <div style="display:flex; align-items:center; justify-content:space-between; margin:4px 0 6px;">
              <div style="font-weight:700;"><?= htmlspecialchars($monthTitle) ?></div>
              <div class="cal-pager">
                <?php if ($prevAllowed): ?>
                  <a class="btn" href="booking_request.php?facility_id=<?= (int)$fid ?>&ym=<?= htmlspecialchars($prevYm) ?>">Prev</a>
                <?php else: ?>
                  <span class="btn disabled">Prev</span>
                <?php endif; ?>
                <?php if ($nextAllowed): ?>
                  <a class="btn" href="booking_request.php?facility_id=<?= (int)$fid ?>&ym=<?= htmlspecialchars($nextYm) ?>">Next</a>
                <?php else: ?>
                  <span class="btn disabled">Next</span>
                <?php endif; ?>
              </div>
            </div>
            <div class="cal-grid">
              <?php for($i=0; $i<$startOffset; $i++): ?><div class="day empty"></div><?php endfor; ?>
              <?php foreach ($calDays as $d): ?>
                <?php $hasPending = !empty($d['pending']); $pendingCount = is_array($d['pending']) ? count($d['pending']) : 0; $cls = $d['blocked'] ? 'unavailable' : ($hasPending ? 'pending' : 'available'); ?>
                <div class="day <?= $cls ?>" data-date="<?= htmlspecialchars($d['date']) ?>">
                  <div class="num"><?= htmlspecialchars($d['label']) ?></div>
                  <?php if ($pendingCount > 0): ?>
                    <span class="pending-under"><?= (int)$pendingCount ?></span>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="cal-pager cal-bottom" style="margin-top:8px; display:flex; justify-content:space-between;">
              <?php if ($prevAllowed): ?>
                <a class="btn" href="booking_request.php?facility_id=<?= (int)$fid ?>&ym=<?= htmlspecialchars($prevYm) ?>">Previous</a>
              <?php else: ?>
                <span class="btn disabled">Previous</span>
              <?php endif; ?>
              <?php if ($nextAllowed): ?>
                <a class="btn" href="booking_request.php?facility_id=<?= (int)$fid ?>&ym=<?= htmlspecialchars($nextYm) ?>">Next</a>
              <?php else: ?>
                <span class="btn disabled">Next</span>
              <?php endif; ?>
            </div>
          </div>
      </div>

      <div>
        <form method="post" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
      <label>Name<br><input type="text" name="name" required value="<?= htmlspecialchars($user['name']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"></label><br><br>
      <label>Email<br><input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"></label><br><br>
      <label>Purpose<br><input type="text" name="purpose" value="" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"></label><br><br>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
        <label>Start date & time<br>
          <input id="start_at" type="text" name="start_at" required value="<?= htmlspecialchars($start_at_input) ?>" placeholder="YYYY-MM-DDTHH:MM" style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
        </label>
        <label>End date & time<br>
          <input id="end_at" type="text" name="end_at" required value="<?= htmlspecialchars($end_at_input) ?>" placeholder="YYYY-MM-DDTHH:MM" style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
        </label>
      </div><br>
      <label>Attendees (optional)<br><input type="number" name="attendees" min="1" style="padding:10px;border-radius:8px;border:1px solid #dfe6ef"></label><br><br>

      <div style="display:flex;gap:10px"><button class="btn primary" type="submit">Request</button><a class="btn subtle" href="facilities_public.php">Back</a></div>
    </form>
      </div>
    </div>
  </div>
</main>
<script>
  (function(){
    const blocked = new Set(<?= json_encode(array_keys($blockedDatesSet)) ?>);
    const pendingMap = <?= json_encode($pendingByDate ?? []) ?>;
    const pendingSet = new Set(Object.keys(pendingMap || {}));
    const blockedArr = Array.from(blocked);
    function checkDateValidity(input) {
      if (!input || !input.value) return;
      const datePart = input.value.slice(0,10);
      if (blocked.has(datePart)) {
        input.setCustomValidity('Selected date is unavailable for this facility.');
        input.reportValidity();
      } else if (pendingSet.has(datePart)) {
        // Inform but do not block
        input.setCustomValidity('');
        try { alert('Heads up: This date already has a pending booking awaiting MPP approval. You can still submit your request.'); } catch(e) {}
      } else {
        input.setCustomValidity('');
      }
    }
    const s = document.getElementById('start_at');
    const e = document.getElementById('end_at');
    if (s) { s.addEventListener('change', function(){ checkDateValidity(s); }); s.addEventListener('input', function(){ checkDateValidity(s); }); }
    if (e) { e.addEventListener('change', function(){ checkDateValidity(e); }); e.addEventListener('input', function(){ checkDateValidity(e); }); }

    // Initialize Flatpickr with disabled dates to block unavailable selections
    function initPickers(){
      if (window.flatpickr && s) {
        window.flatpickr(s, {
          enableTime: true,
          dateFormat: "Y-m-d\\TH:i",
          minDate: "today",
          disable: blockedArr
        });
      }
      if (window.flatpickr && e) {
        window.flatpickr(e, {
          enableTime: true,
          dateFormat: "Y-m-d\\TH:i",
          minDate: "today",
          disable: blockedArr
        });
      }
    }
    initPickers();
    window.addEventListener('load', initPickers);

    // Allow clicking available or pending calendar days to populate date part
    const dayEls = document.querySelectorAll('.day.available[data-date], .day.pending[data-date]');
    dayEls.forEach(function(el){
      el.addEventListener('click', function(){
        const d = el.getAttribute('data-date');
        if (!d) return;
        if (s) {
          const cur = s.value || '';
          const time = (cur.includes('T') ? cur.split('T')[1] : '09:00');
          s.value = d + 'T' + time;
          checkDateValidity(s);
        }
        if (e) {
          const curE = e.value || '';
          const timeE = (curE.includes('T') ? curE.split('T')[1] : '11:00');
          e.value = d + 'T' + timeE;
          checkDateValidity(e);
        }
        if (pendingSet.has(d)) {
          try { alert('Note: This date has a pending booking by another student, awaiting approval. You may still submit.'); } catch(e) {}
        }
      });
    });

    // Intercept form submit to warn about pending dates
    try {
      var form = document.querySelector('form[method="post"]');
      if (form) {
        form.addEventListener('submit', function(ev){
          try {
            var sVal = s && s.value ? s.value.slice(0,10) : '';
            var eVal = e && e.value ? e.value.slice(0,10) : '';
            var warn = (sVal && pendingSet.has(sVal)) || (eVal && pendingSet.has(eVal));
            if (warn) {
              var proceed = confirm('This date has a pending booking awaiting MPP approval. Do you still want to submit your request?');
              if (!proceed) { ev.preventDefault(); return false; }
            }
          } catch(ex) { /* ignore */ }
        });
      }
    } catch(ex) { /* ignore */ }
  })();
</script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</body></html>
