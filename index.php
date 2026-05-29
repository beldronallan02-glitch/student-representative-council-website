<?php
// index.php - protected homepage, requires login

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/assets/inc/authenticate.php';

require_login('login.php');

// ROOT
$ROOT = '/MPPCONNECT';

$user = current_user();

// Profile image
  $profileImageFs = __DIR__ . '/uploads/profiles/' . ($user['profile_image'] ?? '');
  if (empty($user['profile_image']) || !is_file($profileImageFs)) {
    $profileImage = $ROOT . '/assets/img/default-avatar.png';
  } else {
    $profileImage = $ROOT . '/uploads/profiles/' . $user['profile_image'];
  }

/* ===============================
   DASHBOARD STATISTICS
================================ */

// Announcements
$totalAnnouncements = (int)$pdo
  ->query("SELECT COUNT(*) FROM announcements WHERE status='published'")
  ->fetchColumn();

// Events
$totalEvents = (int)$pdo
  ->query("SELECT COUNT(*) FROM events WHERE status='published' AND start_at >= NOW()")
  ->fetchColumn();

// Progress (new schema: progress_log)
try {
  $totalProgress = (int)$pdo
    ->query("SELECT COUNT(*) FROM progress_log")
    ->fetchColumn();
} catch (Throwable $e) {
  $totalProgress = 0;
}

// Admin/MPP analytics
$totalBookings = 0; $pendingBookings = 0; $approvedBookings = 0; $newComplaints = 0; $totalRegistrations = 0;
$latestPendingBookings = []; $latestComplaints = []; $latestRegistrations = [];
if (can_manage($user)) {
  try { $totalBookings = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings")->fetchColumn(); } catch (Throwable $e) { $totalBookings = 0; }
  try { $pendingBookings = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status='pending'")->fetchColumn(); } catch (Throwable $e) { $pendingBookings = 0; }
  try { $approvedBookings = (int)$pdo->query("SELECT COUNT(*) FROM facility_bookings WHERE status='approved'")->fetchColumn(); } catch (Throwable $e) { $approvedBookings = 0; }
  // complaints: count open/new
  try { $newComplaints = (int)$pdo->query("SELECT COUNT(*) FROM complaints WHERE status IN ('new','open')")->fetchColumn(); } catch (Throwable $e) { $newComplaints = 0; }
  // registrations: registered
  try { $totalRegistrations = (int)$pdo->query("SELECT COUNT(*) FROM event_registrations WHERE status='registered'")->fetchColumn(); } catch (Throwable $e) { $totalRegistrations = 0; }
  // latest items
  try {
    $stmt = $pdo->query("SELECT b.id, b.facility_id, b.start_at, b.end_at, f.name AS facility_name, u.name AS requester FROM facility_bookings b LEFT JOIN facilities f ON b.facility_id=f.id LEFT JOIN users u ON b.user_id=u.id WHERE b.status='pending' ORDER BY b.created_at DESC, b.id DESC LIMIT 5");
    $latestPendingBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $latestPendingBookings = []; }
  try {
    $stmt = $pdo->query("SELECT c.id, c.ticket_no, c.status, c.severity, c.created_at, u.name AS owner FROM complaints c LEFT JOIN users u ON u.id=c.user_id ORDER BY c.created_at DESC, c.id DESC LIMIT 5");
    $latestComplaints = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $latestComplaints = []; }
  try {
    $stmt = $pdo->query("SELECT r.id, r.event_id, r.participant_name, r.participant_email, e.title AS event_title FROM event_registrations r LEFT JOIN events e ON e.id=r.event_id WHERE r.status='registered' ORDER BY r.id DESC LIMIT 5");
    $latestRegistrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { $latestRegistrations = []; }
}

// Latest announcements
$stmt = $pdo->prepare("
  SELECT title, excerpt
  FROM announcements
  WHERE status='published'
  ORDER BY created_at DESC
  LIMIT 5
");
$stmt->execute();
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Role helper
function can_manage($user) {
  return in_array($user['role'] ?? '', ['mpp','admin'], true);
}

// Complaint analytics by category (all roles)
$complaintsByCategory = [];
$complaintsTotal = 0;
try {
  $rows = $pdo->query("SELECT category FROM complaints")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  foreach ($rows as $r) {
    $catStr = (string)($r['category'] ?? '');
    if ($catStr === '') continue;
    $parts = array_filter(array_map(function($s){ return trim($s); }, explode(',', $catStr)));
    foreach ($parts as $c) {
      $key = $c; // case-sensitive keep labels as entered
      if ($key === '') continue;
      $complaintsByCategory[$key] = ($complaintsByCategory[$key] ?? 0) + 1;
      $complaintsTotal++;
    }
  }
  // sort by count desc while preserving keys
  arsort($complaintsByCategory, SORT_NUMERIC);
} catch (Throwable $e) {
  $complaintsByCategory = [];
  $complaintsTotal = 0;
}

// Paths
$ANN_PUBLIC = "$ROOT/modules/announcements/announcements_public.php";
$ANN_ADMIN  = "$ROOT/modules/announcements/admin_announcements.php";

$EVENTS_PUB = "$ROOT/modules/events/events_public.php";
$EVENTS_ADM = "$ROOT/modules/events/admin_events.php";

$FEEDBACK   = can_manage($user)
  ? "$ROOT/modules/feedback/feedback_list.php"
  : "$ROOT/modules/feedback/feedback_list2.php";
$COMPLAINT  = "$ROOT/modules/complaint/complaint_list.php";

$PROGRESS_PUB = "$ROOT/modules/progress/progress_public.php";
$PROGRESS_ADM = "$ROOT/modules/progress/progress_manage.php";

$FAC_PUB = "$ROOT/modules/facilities/facilities_public.php";
$FAC_ADM = "$ROOT/modules/facilities/facility_manage.php";

// Admin tools
$USERS_MPP = "$ROOT/modules/users/assign_mpp.php";

$PROFILE = "$ROOT/modules/profile/profile.php";
$LOGOUT  = "$ROOT/logout.php";

// --- Additional read-only analytics used for dashboard charts ---
// Complaints by status
try {
  $stmt = $pdo->query("SELECT status, COUNT(*) AS cnt FROM complaints GROUP BY status");
  $complaintsByStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $complaintsByStatus = []; }

// Complaints by severity
try {
  $stmt = $pdo->query("SELECT severity, COUNT(*) AS cnt FROM complaints GROUP BY severity");
  $complaintsBySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Throwable $e) { $complaintsBySeverity = []; }

// Event registrations over the last 6 months (month => count)
try {
  $stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COUNT(*) AS cnt FROM event_registrations WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY ym ORDER BY ym ASC");
  $stmt->execute();
  $regs = $stmt->fetchAll(PDO::FETCH_ASSOC);
  $registrationsOverTime = [];
  foreach ($regs as $r) { $registrationsOverTime[$r['ym']] = (int)$r['cnt']; }
} catch (Throwable $e) { $registrationsOverTime = []; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>MPPConnect — Home</title>

<link rel="stylesheet" href="css/style.css">

<style>
.dashboard, body { font-size:16px; }
.dashboard {
  max-width:1400px; margin:28px auto; display:grid; gap:24px; padding:0 18px;
}
.kpi-grid{ display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:20px; }
.kpi { background:#fff; border-radius:12px; padding:16px; box-shadow:0 10px 22px rgba(0,0,0,0.07); display:flex; flex-direction:column; gap:8px; min-height:100px; }
.kpi .kpi-number{ font-size:32px; font-weight:800; color:#0b3a66; }
.kpi .kpi-label{ font-size:14px; color:#374151; }
.kpi .kpi-meta{ font-size:13px; color:#475569; }

.charts-grid{ display:grid; grid-template-columns: 1fr 0.95fr 1fr; gap:24px; align-items:start; grid-auto-rows: min-content; }
.charts-grid > div { display:flex; flex-direction:column; gap:24px; }
.panel { background:#fff; border-radius:12px; padding:18px; box-shadow:0 10px 28px rgba(0,0,0,0.08); min-height:0; }
.panel h3{ margin:0 0 12px; font-size:20px; font-weight:700; color:#0b2440; }
.panel-tall{ min-height:360px; }

.quick-actions { display:flex; flex-direction:column; gap:10px; }
.quick-actions .btn { width:100%; padding:10px 12px; }

.mini { font-size:16px; color:#111827; }

.overview { display:flex; gap:18px; align-items:center; justify-content:space-between; }
.overview .welcome { flex:1; }
.overview .avatar-sm { width:92px; height:92px; }

.mobile-hide { display:block; }
@media(max-width:1100px){ .charts-grid{ grid-template-columns:1fr; } .mini-hide{ display:none; } }

.stats-grid{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(200px,1fr));
  gap:16px;
  margin:20px 0;
}
.stat-card{
  background:#fff;
  border-radius:12px;
  padding:18px;
  text-align:center;
  box-shadow:0 12px 28px rgba(0,0,0,.10);
}
.stat-number{
  font-size:34px;
  font-weight:900;
  color:#4f46e5;
}
.stat-label{
  margin-top:6px;
  font-size:14px;
  color:#6b7280;
}

.modules{position:relative}
.modules-menu{
  position:absolute;
  top:110%;
  right:0;
  min-width:240px;
  background:rgba(20,20,30,.95);
  border-radius:12px;
  padding:8px;
  display:none;
  z-index:999;
  box-shadow:0 14px 34px rgba(0,0,0,.25);
}
.modules-menu a{
  display:block;
  padding:10px 12px;
  border-radius:8px;
  color:#fff;
  text-decoration:none;
  font-size:14px;
}
.modules-menu a:hover{
  background:rgba(255,255,255,.1);
}
.modules.open .modules-menu{display:block}

/* Fixed rounded avatar */
.avatar-sm{
  width:72px;
  height:72px;
  border-radius:50%;
  object-fit:cover;
  border:2px solid #ffffff;
  box-shadow:0 4px 10px rgba(0,0,0,.12);
  flex-shrink:0;
}
</style>
<!-- Defensive override to ensure mobile nav doesn't overlay content -->
<style>
@media (max-width: 768px) {
  .nav { position: static !important; inset: auto !important; background: transparent !important; backdrop-filter: none !important; transform: none !important; z-index: auto !important; }
  .nav.active { position: fixed !important; inset: 0 !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px) !important; z-index: 999 !important; }
}
</style>
</head>

<body>

<header class="topbar">
  <div class="brand">
    <img src="<?= $ROOT ?>/assets/img/umslogo.png" class="brand-logo">
    <h1>MPP<span class="accent">Connect</span></h1>
  </div>

  <nav class="nav">
    <a href="<?= $ROOT ?>/index.php" class="btn subtle">Home</a>

    <!-- GLOBAL SEARCH -->
    <form
      action="<?= $ROOT ?>/modules/search/search.php"
      method="get"
      style="margin-right:8px"
      onsubmit="if(!this.q.value.trim()) return false;"
    >
      <input
        type="search"
        name="q"
        placeholder="Search MPPConnect…"
        style="padding:10px 14px;border-radius:12px;border:1px solid #e5e7eb;width:220px"
      >
    </form>

    <!-- MENU DROPDOWN -->
    <div class="modules">
      <button class="btn subtle" id="menuToggle" type="button">Menu</button>
      <div class="modules-menu">
        <a href="<?= can_manage($user)?$ANN_ADMIN:$ANN_PUBLIC ?>">Announcements</a>
        <a href="<?= can_manage($user)?$EVENTS_ADM:$EVENTS_PUB ?>">Events</a>
        <a href="<?= $FEEDBACK ?>">Ratings & Feedback</a>
        <a href="<?= $COMPLAINT ?>">Complaints</a>
        <a href="<?= can_manage($user)?$PROGRESS_ADM:$PROGRESS_PUB ?>">MPP Progress</a>
        <a href="<?= can_manage($user)?$FAC_ADM:$FAC_PUB ?>">Facility Booking</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="<?= $USERS_MPP ?>">Assign MPP Role</a>
        <?php endif; ?>
      </div>
    </div>

    <a href="<?= $PROFILE ?>" class="btn subtle">Profile</a>
    <a href="<?= $LOGOUT ?>" class="btn primary">Sign Out</a>
  </nav>
</header>

<main class="container dashboard">

<?php if (can_manage($user)): ?>
  <div class="overview">
    <div class="welcome">
      <h2>Welcome back, <?= htmlspecialchars($user['name']) ?>.</h2>
      <p class="mini">Executive Analytics — decision support and operational health at a glance.</p>
    </div>
    <div class="mini"> <img src="<?= $profileImage ?>" class="avatar-sm" alt="avatar"> </div>
  </div>

  <section class="kpi-grid" style="margin-top:14px;">
    <div class="kpi">
      <div class="kpi-number"><?= $pendingBookings ?></div>
      <div class="kpi-label">Pending Approvals</div>
      <div class="kpi-meta"><?= $pendingBookings > 0 ? 'Needs attention' : 'Stable' ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-number"><?= $newComplaints ?></div>
      <div class="kpi-label">New Complaints</div>
      <div class="kpi-meta"><?= $newComplaints > 5 ? 'High volume' : ($newComplaints > 0 ? 'Monitor' : 'Low') ?></div>
    </div>
    <div class="kpi">
      <div class="kpi-number"><?= $totalRegistrations ?></div>
      <div class="kpi-label">Event Registrations</div>
      <div class="kpi-meta">Recent activity</div>
    </div>
    <div class="kpi">
      <div class="kpi-number"><?= $totalAnnouncements ?></div>
      <div class="kpi-label">Active Announcements</div>
      <div class="kpi-meta">Governance & Communication</div>
    </div>
  </section>

  <div class="charts-grid">
    <div>
      <div class="panel">
        <h3>Complaints by Category</h3>
          <div style="display:grid;gap:6px;">
            <?php if (empty($complaintsByCategory)): ?>
              <div class="mini">No complaint categories recorded.</div>
            <?php else: $__i=1; foreach($complaintsByCategory as $__cat => $__cnt): ?>
              <div class="mini"><?= $__i ?>. <?= htmlspecialchars($__cat) ?> — <strong><?= (int)$__cnt ?></strong></div>
            <?php $__i++; endforeach; endif; ?>
          </div>
      </div>

      <div class="panel">
        <h3>Recent Activity</h3>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:12px;">
          <div>
            <h4 class="mini">Pending Bookings</h4>
            <ul>
              <?php foreach(array_slice($latestPendingBookings,0,5) as $b): ?>
                <li class="mini"><?= htmlspecialchars($b['facility_name'] ?? 'Facility') ?> — <?= htmlspecialchars($b['requester'] ?? '') ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h4 class="mini">Recent Complaints</h4>
            <ul>
              <?php foreach(array_slice($latestComplaints,0,5) as $c): ?>
                <li class="mini">#<?= htmlspecialchars($c['ticket_no'] ?? (string)$c['id']) ?> — <?= htmlspecialchars($c['severity'] ?? '') ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div>
            <h4 class="mini">Latest Registrations</h4>
            <ul>
              <?php foreach(array_slice($latestRegistrations,0,5) as $r): ?>
                <li class="mini"><?= htmlspecialchars($r['event_title'] ?? '') ?> — <?= htmlspecialchars($r['participant_name'] ?? '') ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>

    <div>
      <div class="panel panel-tall">
        <h3>Event Registrations (last 6 months)</h3>
          <div style="display:grid;gap:6px;">
            <?php if (empty($registrationsOverTime)): ?>
              <div class="mini">No recent registrations.</div>
            <?php else: $__i=1; foreach($registrationsOverTime as $__ym => $__cnt): ?>
              <div class="mini"><?= $__i ?>. <?= htmlspecialchars($__ym) ?> — <strong><?= (int)$__cnt ?></strong></div>
            <?php $__i++; endforeach; endif; ?>
          </div>
      </div>
    </div>

    <div>
      <div class="panel">
        <h3>Booking Status</h3>
        <?php $otherBookings = max(0, $totalBookings - $pendingBookings - $approvedBookings); ?>
        <div style="display:grid;gap:8px;">
          <div class="mini">1. Pending — <strong><?= (int)$pendingBookings ?></strong></div>
          <div class="mini">2. Approved — <strong><?= (int)$approvedBookings ?></strong></div>
          <div class="mini">3. Other — <strong><?= (int)$otherBookings ?></strong></div>
        </div>
      </div>

      <div class="panel">
        <h3>Complaints by Severity</h3>
        <div style="display:grid;gap:6px;">
          <?php if (empty($complaintsBySeverity)): ?>
            <div class="mini">No complaints.</div>
          <?php else: $__i=1; foreach($complaintsBySeverity as $__sev => $__cnt): ?>
            <div class="mini"><?= $__i ?>. <?= htmlspecialchars($__sev) ?> — <strong><?= (int)$__cnt ?></strong></div>
          <?php $__i++; endforeach; endif; ?>
        </div>
      </div>

      <div class="panel">
        <h3>Quick Actions</h3>
        <div class="quick-actions">
          <a class="btn primary" href="<?= $ROOT ?>/modules/facilities/admin_bookings.php">Approve Bookings</a>
          <a class="btn subtle" href="<?= $ROOT ?>/modules/complaint/admin_complaints.php">Review Complaints</a>
          <a class="btn subtle" href="<?= $ROOT ?>/modules/announcements/admin_announcements.php">Publish Announcement</a>
        </div>
      </div>
    </div>
  </div>

  

<?php else: ?>
  <!-- Student view: simplified insights -->
  <div class="overview">
    <div class="welcome">
      <h2>Welcome, <?= htmlspecialchars($user['name']) ?>.</h2>
      <p class="mini">Your recent activity and upcoming items.</p>
    </div>
    <div class="mini"> <img src="<?= $profileImage ?>" class="avatar-sm" alt="avatar"> </div>
  </div>

  <section class="kpi-grid" style="margin-top:14px;">
    <div class="kpi"><div class="kpi-number"><?= $totalEvents ?></div><div class="kpi-label">Upcoming Events</div></div>
    <div class="kpi"><div class="kpi-number"><?= $totalProgress ?></div><div class="kpi-label">Progress Updates</div></div>
    <div class="kpi"><div class="kpi-number"><?= $totalAnnouncements ?></div><div class="kpi-label">Announcements</div></div>
  </section>

  <section class="panel" style="margin-top:12px;">
    <h3>Latest Announcements</h3>
    <?php if (empty($announcements)): ?><p class="mini">No announcements.</p><?php else: foreach($announcements as $a): ?>
      <article><strong><?= htmlspecialchars($a['title']) ?></strong><p class="mini"><?= htmlspecialchars($a['excerpt']) ?></p></article>
    <?php endforeach; endif; ?>
  </section>

<?php endif; ?>

</main>

<footer class="footer">
  <p>&copy; <?= date('Y') ?> MPPConnect</p>
</footer>

<!-- Charts removed: dashboard now shows numeric summaries instead of graphical charts -->

<script>
const menuToggle = document.getElementById('menuToggle');
const modules = document.querySelector('.modules');
const navEl = document.querySelector('.nav');

menuToggle.addEventListener('click', (e) => {
  e.stopPropagation();
  modules.classList.toggle('open');
});

document.addEventListener('click', () => {
  modules.classList.remove('open');
});

// Guard: ensure mobile nav overlay is never active by default
function ensureNavNotOverlay() {
  if (navEl) navEl.classList.remove('active');
}
ensureNavNotOverlay();
window.addEventListener('resize', ensureNavNotOverlay);
</script>

</body>
</html>
