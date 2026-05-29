<?php
// modules/facilities/facilities_public.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

try {
    $stmt = $pdo->query("SELECT * FROM facilities ORDER BY name ASC");
    $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $facilities = [];
}
$user = current_user();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Facilities — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
<link rel="stylesheet" href="../../css/style.css">
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

.facility-gallery { max-width: 1200px; margin: 0 auto; padding: 40px 20px 80px; }
.facility-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 22px; }
.facility-card { background: rgba(255,255,255,0.9); border-radius: 18px; overflow: hidden; box-shadow: 0 14px 34px rgba(0,0,0,0.12); transition: transform .18s ease, box-shadow .18s ease; }
.facility-card:hover { transform: translateY(-4px); box-shadow: 0 22px 48px rgba(0,0,0,0.18); }
.facility-thumb { width: 100%; height: 200px; object-fit: cover; background: #e5e7eb; }
.facility-meta { padding: 16px 18px; display:flex; flex-direction:column; }
.facility-title { font-size: 18px; font-weight: 800; margin: 0 0 6px; }
.facility-info { font-size: 14px; color: #475569; margin-bottom: 12px; font-weight: 400; display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.facility-info .tag { background:#eef2ff; color:#1e40af; padding:5px 10px; border-radius:999px; font-weight:400; border:1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.06); user-select:none; cursor:default; font-size: 13px; }
.facility-actions { display:flex; gap:8px; align-items:center; margin-top:auto; }
.empty { background: #ffffff; border-radius: 18px; padding: 26px; text-align: center; box-shadow: 0 14px 34px rgba(0,0,0,0.12); }
/* No description on cards to keep uniform size */
</style>
</head><body>
<header class="topbar"><div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
<nav class="nav">
  <a href="../../index.php" class="btn subtle">Home</a>
  <?php if ($user && ($user['role'] ?? '') === 'student'): ?>
    <a class="btn subtle" href="student_bookings.php">My bookings</a>
  <?php endif; ?>
  <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
    <a class="btn primary" href="facility_manage.php">Manage</a>
  <?php else: ?>
    
  <?php endif; ?>
</nav></header>

<?php
// Helper: find first facility image
function facility_first_image_webpath($id) {
  $baseDir = __DIR__ . '/../../uploads/facilities/facility_' . (int)$id;
  $baseWeb = '../../uploads/facilities/facility_' . (int)$id;
  if (is_dir($baseDir)) {
    $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp'];
    foreach ($patterns as $p) {
      $matches = glob($baseDir . '/' . $p, GLOB_NOSORT);
      if (!empty($matches)) {
        $file = basename($matches[0]);
        return $baseWeb . '/' . $file;
      }
    }
  }
  // Fallback to a generic placeholder used elsewhere
  return '../../uploads/progress/placeholder.png';
}
?>

<main class="facility-gallery">
  <h2 style="text-align:center;margin-bottom:20px">Facilities</h2>
  <?php if (empty($facilities)): ?>
    <div class="empty"><p class="lead">No facilities available.</p></div>
  <?php else: ?>
    <div class="facility-grid">
      <?php foreach($facilities as $f): 
        $img = facility_first_image_webpath($f['id']);
      ?>
        <article class="facility-card">
          <img class="facility-thumb" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($f['name']) ?>">
          <div class="facility-meta">
            <div class="facility-title"><?= htmlspecialchars($f['name']) ?></div>
            <div class="facility-info">
              <span class="tag"><?= htmlspecialchars($f['location']) ?></span>
              <span class="tag">Capacity <?= htmlspecialchars($f['capacity'] ?: '—') ?></span>
            </div>
            <div class="facility-actions">
              <a class="btn subtle" href="facility_view.php?id=<?= (int)$f['id'] ?>">View more</a>
              <a class="btn subtle" href="booking_request.php?facility_id=<?= (int)$f['id'] ?>">Request booking</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<!-- Descriptions are shown on the facility_view page to keep cards uniform -->

</body></html>
