<?php
// modules/progress/mpp_logs.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();
$mppId = isset($_GET['mpp']) ? (int)$_GET['mpp'] : 0;
if ($mppId <= 0) { header('Location: progress_public.php'); exit; }

// Fetch MPP user info
try {
  $stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = :id AND role = 'mpp' AND is_active = 1");
  $stmt->execute([':id' => $mppId]);
  $mpp = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$mpp) { header('Location: progress_public.php'); exit; }
} catch (Throwable $e) {
  error_log('mpp_logs mpp fetch error: '.$e->getMessage());
  header('Location: progress_public.php'); exit;
}

// Fetch logs for all progress rows of this MPP
$logs = [];
try {
  $stmt = $pdo->prepare(
    "SELECT l.*, mp.start_date, mp.end_date
     FROM progress_log l
     JOIN mpp_progress mp ON mp.mppprogressid = l.mppprogressid
     WHERE mp.userid = :uid
     ORDER BY l.logged_at DESC"
  );
  $stmt->execute([':uid' => $mppId]);
  $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) { error_log('mpp_logs logs error: '.$e->getMessage()); $logs = []; }

// Fetch images for logs
$imagesByLog = [];
if (!empty($logs)) {
  try {
    $ids = array_column($logs, 'logid');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT imageid, logid, imgpath FROM progress_log_images WHERE logid IN ($placeholders) ORDER BY imageid ASC");
    $q->execute($ids);
    while ($row = $q->fetch(PDO::FETCH_ASSOC)) {
      $lid = (int)$row['logid'];
      if (!isset($imagesByLog[$lid])) $imagesByLog[$lid] = [];
      $imagesByLog[$lid][] = $row;
    }
  } catch (Throwable $e) { error_log('mpp_logs images error: '.$e->getMessage()); }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="../../css/style.css">
  <title>MPP Progress — <?= htmlspecialchars($mpp['name']) ?></title>
  <style>
    /* Ensure global gradient stays fixed while scrolling */
    html, body { min-height: 100%; margin: 0; padding: 0; }
    body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

    .chip { display:inline-block; background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe; padding:3px 8px; border-radius:999px; font-size:12px; }
    .meta { font-size:12px; color:#6b7280; }

    /* Card layout for logs */
    .logs-grid { display:grid; grid-template-columns:1fr; gap:16px; }
    @media (min-width: 900px) { .logs-grid { grid-template-columns: 1fr 1fr; } }
    .log-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,0.08); overflow:hidden; display:flex; flex-direction:column; }
    .log-media { position:relative; width:100%; background:#f1f5f9; }
    .log-media::before { content:''; display:block; padding-top:48%; }
    .log-media > a, .log-media > img { position:absolute; inset:0; display:block; width:100%; height:100%; }
    .log-media > a img { width:100%; height:100%; object-fit:cover; }
    .log-body { padding:14px 16px; display:flex; flex-direction:column; gap:8px; }
    .log-title { font-size:20px; font-weight:800; color:#0b1c33; }
    .log-row { display:flex; align-items:center; justify-content:space-between; gap:10px; }
    .thumbs { display:flex; gap:8px; flex-wrap:wrap; }
    .thumbs img { width:90px; height:90px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb; }
  </style>
</head>
<body>
<header class="topbar">
  <div class="brand">
    <div class="logo-blob"></div>
    <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
  </div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
    <a class="btn subtle" href="progress_public.php">Back to Progress</a>
  </nav>
</header>

<main class="container" style="padding:24px; max-width:1200px;">
  <h2 style="margin:0 0 16px;">Progress Logs — <?= htmlspecialchars($mpp['name']) ?></h2>
  <?php if (empty($logs)): ?>
    <div class="card"><p class="lead">No progress logs yet.</p></div>
  <?php else: ?>
  <div class="logs-grid">
    <?php foreach ($logs as $log): 
      $imgs = $imagesByLog[$log['logid']] ?? [];
      $featured = $imgs[0] ?? null;
      $featuredSrc = $featured ? ('../../' . ltrim($featured['imgpath'], '/')) : '';
    ?>
      <article class="log-card">
        <?php if ($featuredSrc): ?>
          <div class="log-media">
            <a href="<?= htmlspecialchars($featuredSrc) ?>" target="_blank" rel="noopener" aria-label="Open featured image">
              <img src="<?= htmlspecialchars($featuredSrc) ?>" alt="Featured image for <?= htmlspecialchars($log['title'] ?? 'log') ?>">
            </a>
          </div>
        <?php endif; ?>
        <div class="log-body">
          <div class="log-row">
            <div class="log-title"><?= htmlspecialchars($log['title'] ?? '') ?></div>
            <span class="chip"><?= htmlspecialchars($log['status'] ?? '-') ?></span>
          </div>
          <div class="meta">Logged <?= htmlspecialchars(date('M j, Y · g:i A', strtotime($log['logged_at']))) ?></div>
          <?php if (!empty($log['description'])): ?>
            <div><?= nl2br(htmlspecialchars($log['description'])) ?></div>
          <?php endif; ?>
          <?php if (!empty($log['remarks'])): ?>
            <div class="meta">Remarks: <?= htmlspecialchars($log['remarks']) ?></div>
          <?php endif; ?>
          <?php if (!empty($imgs)): ?>
            <div class="thumbs" style="margin-top:8px;">
              <?php foreach (array_slice($imgs, 1) as $im): 
                $src = '../../' . ltrim($im['imgpath'], '/');
              ?>
                <a href="<?= htmlspecialchars($src) ?>" target="_blank" rel="noopener">
                  <img src="<?= htmlspecialchars($src) ?>" alt="Log image">
                </a>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <span class="meta">No images</span>
          <?php endif; ?>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</main>
</body>
</html>
