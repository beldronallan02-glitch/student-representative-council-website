<?php
// modules/facilities/student_bookings.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();
if (!$user) { header('Location: /MPPCONNECT/login.php'); exit; }

$email = trim(strtolower($user['email'] ?? ''));
if ($email !== '') {
  $stmt = $pdo->prepare("SELECT b.*, f.name AS facility_name
    FROM facility_bookings b
    LEFT JOIN facilities f ON b.facility_id=f.id
    WHERE (b.user_id = ? OR (b.user_id IS NULL AND TRIM(LOWER(b.email)) = TRIM(LOWER(?))))
    ORDER BY b.created_at DESC");
  $stmt->execute([$user['id'], $email]);
} else {
  $stmt = $pdo->prepare("SELECT b.*, f.name AS facility_name FROM facility_bookings b LEFT JOIN facilities f ON b.facility_id=f.id WHERE b.user_id=? ORDER BY b.created_at DESC");
  $stmt->execute([$user['id']]);
}
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html><html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Your Bookings — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
<link rel="stylesheet" href="../../css/style.css">
<style>
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
/* Horizontal scroll on small screens */
.table-scroll { overflow-x:auto; }
@media (max-width: 768px) {
  .nav { position: static !important; inset: auto !important; background: transparent !important; backdrop-filter: none !important; transform: none !important; z-index: auto !important; }
  .nav.active { position: fixed !important; inset: 0 !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px) !important; z-index: 999 !important; }
}
</style>
</head><body>
<header class="topbar">
  <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
    <a class="btn subtle" href="facilities_public.php">Facilities</a>
  </nav>
</header>

<main class="container">
  <div class="table-card">
    <div style="padding:16px 16px 0;">
      <h2>Your bookings</h2>
    </div>
    <?php if (empty($rows)): ?><p class="lead">You have not requested any bookings yet.</p><?php else: ?>
      <div class="table-scroll">
      <table class="bookings-table">
        <thead>
          <tr>
            <th>Facility</th>
            <th>When</th>
            <th>Attendees</th>
            <th>Status</th>
            <th style="text-align:right">Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <?php
            $status = strtolower(trim($r['status'] ?? ''));
            $statusClass = 'status-pill status-' . ($status ?: 'pending');
            try {
              $startDT = new DateTime($r['start_at']);
              $endDT = new DateTime($r['end_at']);
              $dateStr = $startDT->format('j F Y');
              $timeStr = $startDT->format('g:i A') . ' — ' . $endDT->format('g:i A');
            } catch (Exception $e) {
              $dateStr = htmlspecialchars($r['start_at']);
              $timeStr = htmlspecialchars(($r['start_at'] ?? '') . ' — ' . ($r['end_at'] ?? ''));
            }
          ?>
          <tr>
            <td><?= htmlspecialchars($r['facility_name']) ?></td>
            <td><div><?= htmlspecialchars($dateStr) ?></div><div style="color:#6b7280; font-size:12px;"><?= htmlspecialchars($timeStr) ?></div></td>
            <td><?= htmlspecialchars($r['attendees'] ?: '—') ?></td>
            <td><span class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
            <td>
              <div class="actions">
                <a class="btn subtle" href="booking_view.php?id=<?= (int)$r['id'] ?>">View</a>
                <?php if ($r['status'] === 'pending' || $r['status'] === 'approved'): ?>
                  <form method="post" action="booking_action.php" style="display:inline" onsubmit="return confirm('Cancel booking?');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <input type="hidden" name="action" value="cancel">
                    <button class="btn ghost" type="submit">Cancel</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    <?php endif; ?>
  </div>
</main>
</body></html>
