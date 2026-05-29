<?php
// modules/facilities/facility_view.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { header('Location: facilities_public.php'); exit; }

try {
  $stmt = $pdo->prepare("SELECT * FROM facilities WHERE id = :id");
  $stmt->execute([':id' => $id]);
  $facility = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $facility = null;
}

if (!$facility) { header('Location: facilities_public.php'); exit; }

function facility_images($id) {
  $dir = __DIR__ . '/../../uploads/facilities/facility_' . (int)$id;
  $web = '../../uploads/facilities/facility_' . (int)$id;
  $out = [];
  if (is_dir($dir)) {
    $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp'];
    foreach ($patterns as $p) {
      foreach (glob($dir . '/' . $p, GLOB_NOSORT) as $file) {
        $out[] = $web . '/' . basename($file);
      }
    }
  }
  if (empty($out)) { $out[] = '../../uploads/progress/placeholder.png'; }
  return $out;
}

$images = facility_images($id);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title><?= htmlspecialchars($facility['name']) ?> — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
  <link rel="stylesheet" href="../../css/style.css">
  <style>
    .facility-view { max-width: 1100px; margin: 0 auto; padding: 30px 20px 60px; }
    .hero { display:flex; gap:18px; align-items:flex-start; flex-wrap:wrap; }
    .hero img { width: 100%; max-width: 560px; height: 320px; object-fit: cover; border-radius: 16px; box-shadow: 0 10px 24px rgba(0,0,0,0.12); }
    .hero .meta { flex:1; }
    .info { display:flex; gap:8px; align-items:center; flex-wrap:wrap; margin:6px 0 10px; font-size:14px; color:#475569; font-weight:400; }
    .info .tag { background:#eef2ff; color:#1e40af; padding:5px 10px; border-radius:999px; font-weight:400; border:1px solid #e2e8f0; box-shadow: 0 1px 2px rgba(0,0,0,0.06); user-select:none; cursor:default; font-size:13px; }
    .thumbs { display:flex; gap:8px; flex-wrap:wrap; margin-top: 12px; }
    .thumbs img { width: 120px; height: 80px; object-fit: cover; border-radius: 10px; cursor: pointer; }
    .desc { background:#fff; border-radius:16px; padding:18px; box-shadow:0 10px 24px rgba(0,0,0,0.08); margin-top:20px; }
    .top-actions { display:flex; gap:10px; align-items:center; margin-bottom:16px; }
  </style>
</head>
<body>

<header class="topbar">
  <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
    <a class="btn subtle" href="facilities_public.php">Back</a>
    <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
      <a class="btn primary" href="facility_manage.php">Manage</a>
    <?php else: ?>
      
    <?php endif; ?>
  </nav>
</header>

<main class="facility-view">
  <div class="top-actions">
    <a class="btn primary" href="booking_request.php?facility_id=<?= (int)$id ?>">Book this facility</a>
  </div>

  <div class="hero">
    <img src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($facility['name']) ?>">
    <div class="meta">
      <h2 style="margin:0 0 8px;"><?= htmlspecialchars($facility['name']) ?></h2>
      <div class="info">
        <span class="tag"><?= htmlspecialchars($facility['location']) ?></span>
        <span class="tag">Capacity <?= htmlspecialchars($facility['capacity'] ?: '—') ?></span>
      </div>
      <?php if (count($images) > 1): ?>
        <div class="thumbs">
          <?php foreach (array_slice($images, 1) as $src): ?>
            <img src="<?= htmlspecialchars($src) ?>" alt="Thumbnail" onclick="document.querySelector('.hero img').src='<?= htmlspecialchars($src) ?>'">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <section class="desc">
    <h3>Description</h3>
    <p><?= nl2br(htmlspecialchars(trim($facility['description'] ?? ''))) ?></p>
  </section>
</main>

</body>
</html>