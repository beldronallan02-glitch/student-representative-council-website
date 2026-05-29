<?php
// modules/progress/progress_public.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();
$mppFilterId = isset($_GET['mpp']) ? (int)$_GET['mpp'] : null;
$searchQuery = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

/* ===============================
   FETCH PUBLISHED PROGRESS
================================ */
try {
    $stmt = $pdo->prepare("
        SELECT 
            p.id,
            p.title,
            p.description,
            p.created_at,
            u.name AS author,
            (
                SELECT filename 
                FROM progress_images 
                WHERE progress_id = p.id 
                ORDER BY id ASC 
                LIMIT 1
            ) AS thumb
        FROM progress_entries p
        LEFT JOIN users u ON u.id = p.user_id
        WHERE p.status = 'published'
        ORDER BY p.created_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('progress_public error: '.$e->getMessage());
  $rows = [];
}

// Build MPP galleries or filter view
$activeMpp = null;
$mppSections = [];
try {
  if ($mppFilterId) {
    // Filter by specific MPP
    $stmtUser = $pdo->prepare("SELECT id, name, profile_image FROM users WHERE id = :id AND role = 'mpp'");
    $stmtUser->execute([':id' => $mppFilterId]);
    $activeMpp = $stmtUser->fetch(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare(
      "SELECT 
        p.id, p.title, p.description, p.created_at,
        (SELECT filename FROM progress_images WHERE progress_id = p.id ORDER BY id ASC LIMIT 1) AS thumb
       FROM progress_entries p
       WHERE p.status = 'published' AND p.user_id = :uid
       ORDER BY p.created_at DESC"
    );
    $stmt->execute([':uid' => $mppFilterId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } else {
    // Collect galleries per MPP
      if ($searchQuery !== '') {
      // Search specific MPP(s) by name
      $stmtMpps = $pdo->prepare("SELECT id, name, profile_image FROM users WHERE role = 'mpp' AND is_active = 1 AND name LIKE :q ORDER BY name ASC");
      $stmtMpps->execute([':q' => '%' . $searchQuery . '%']);
    } else {
      $stmtMpps = $pdo->prepare("SELECT id, name, profile_image FROM users WHERE role = 'mpp' AND is_active = 1 ORDER BY name ASC");
      $stmtMpps->execute();
    }
    $mpps = $stmtMpps->fetchAll(PDO::FETCH_ASSOC);

    $q = $pdo->prepare(
      "SELECT 
        p.id, p.title, p.description, p.created_at,
        (SELECT filename FROM progress_images WHERE progress_id = p.id ORDER BY id ASC LIMIT 1) AS thumb
       FROM progress_entries p
       WHERE p.status = 'published' AND p.user_id = :uid
       ORDER BY p.created_at DESC
       LIMIT 8"
    );
    foreach ($mpps as $mpp) {
      $q->execute([':uid' => (int)$mpp['id']]);
      $items = $q->fetchAll(PDO::FETCH_ASSOC);
      // Always include a section for each MPP, even if no items yet
      $mppSections[] = [ 'mpp' => $mpp, 'items' => $items ];
    }
  }
} catch (Throwable $e) {
  error_log('progress_public galleries error: '.$e->getMessage());
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Progress & Impact — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="../../css/style.css">

<style>
.progress-gallery {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.progress-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 22px;
}

/* CARD */
.progress-card {
  background: rgba(255,255,255,0.9);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: 0 14px 34px rgba(0,0,0,0.12);
  transition: transform .2s ease, box-shadow .2s ease;
}

.progress-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 22px 48px rgba(0,0,0,0.18);
}

.progress-thumb {
  width: 100%;
  height: 200px;
  object-fit: cover;
  background: #e5e7eb;
}

.progress-meta {
  padding: 16px 18px;
}

.progress-title {
  font-size: 18px;
  font-weight: 800;
  margin: 0 0 6px;
}

.progress-info {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 10px;
}

.progress-excerpt {
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
}

/* EMPTY */
.empty {
  background: #ffffff;
  border-radius: 18px;
  padding: 26px;
  text-align: center;
  box-shadow: 0 14px 34px rgba(0,0,0,0.12);
}
</style>
</head>

<body>

<header class="topbar">
  <div class="brand">
    <div class="logo-blob"></div>
    <div class="brand-text">
      <h1>MPP<span class="accent">Connect</span></h1>
    </div>
  </div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
    <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
      <a class="btn primary" href="progress_manage.php">Manage</a>
    <?php else: ?>
      <a class="btn subtle" href="../../login.php">Sign In</a>
    <?php endif; ?>
  </nav>
</header>

<main class="progress-gallery">

  <h2 style="text-align:center;margin:0 0 16px;">Progress & Impact — MPP Galleries</h2>

  <?php
  // Build list of MPPs that have progress records
  $mppsWithProgress = [];
  try {
    $stmt = $pdo->query("SELECT DISTINCT u.id, u.name, u.profile_image FROM mpp_progress mp JOIN users u ON u.id = mp.userid WHERE u.role = 'mpp' AND u.is_active = 1 ORDER BY u.name ASC");
    $mppsWithProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) { error_log('progress_public mppsWithProgress error: '.$e->getMessage()); }
  ?>
  
  <form action="progress_public.php" method="get" style="max-width:800px;margin:0 auto 20px;display:flex;gap:10px;align-items:center">
    <input type="text" name="q" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search MPP by name" style="flex:1;padding:10px 12px;border:1px solid #d1d5db;border-radius:10px" aria-label="Search MPP by name">
    <button type="submit" class="btn primary">Search</button>
    <?php if ($searchQuery !== ''): ?>
      <a class="btn subtle" href="progress_public.php">Clear</a>
    <?php endif; ?>
  </form>

  <section style="max-width:1000px;margin:0 auto 24px;">
    <?php if (empty($mppsWithProgress)): ?>
      <div class="empty"><p class="lead">No MPP progress records yet.</p></div>
    <?php else: ?>
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:12px;">
        <?php foreach ($mppsWithProgress as $mpp): ?>
          <?php
            $avatar = (!empty($mpp['profile_image'])) ? '../../uploads/profiles/' . $mpp['profile_image'] : '../../uploads/profiles/placeholder.png';
          ?>
          <div class="progress-card" style="padding:18px;display:flex;align-items:center;justify-content:space-between;">
            <div style="display:flex;align-items:center;gap:14px">
              <img src="<?= htmlspecialchars($avatar) ?>" alt="<?= htmlspecialchars($mpp['name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
              <div style="font-weight:800;"><?= htmlspecialchars($mpp['name']) ?></div>
            </div>
            <a class="btn primary" href="mpp_logs.php?mpp=<?= (int)$mpp['id'] ?>">View</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <?php if ($mppFilterId && !empty($activeMpp)): ?>
    <?php $activeAvatar = (!empty($activeMpp['profile_image'])) ? '../../uploads/profiles/' . $activeMpp['profile_image'] : '../../uploads/profiles/placeholder.png'; ?>
    <div style="text-align:center;margin-bottom:8px;display:flex;align-items:center;justify-content:center;gap:12px;flex-wrap:wrap;">
      <img src="<?= htmlspecialchars($activeAvatar) ?>" alt="<?= htmlspecialchars($activeMpp['name']) ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover;">
      <h2 style="margin:0">Updates by <?= htmlspecialchars($activeMpp['name']) ?></h2>
    </div>
    <div style="text-align:center;margin-bottom:22px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">
      <a class="btn subtle" href="progress_public.php">← All MPP Galleries</a>
      <a class="btn primary" href="mpp_logs.php?mpp=<?= (int)$mppFilterId ?>">View Logs</a>
    </div>

    <?php if (empty($rows)): ?>
      <div class="empty"><p class="lead">No published updates from this MPP yet.</p></div>
    <?php else: ?>
      <div class="progress-grid">
        <?php foreach ($rows as $r):
          $thumb = !empty($r['thumb']) ? '../../uploads/progress/' . $r['thumb'] : '../../uploads/progress/placeholder.png';
        ?>
          <article class="progress-card">
            <a href="progress_view.php?id=<?= (int)$r['id'] ?>" style="text-decoration:none;color:inherit">
              <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($r['title']) ?>" class="progress-thumb">
              <div class="progress-meta">
                <div class="progress-title"><?= htmlspecialchars($r['title']) ?></div>
                <div class="progress-info"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></div>
                <div class="progress-excerpt"><?= htmlspecialchars(mb_strimwidth(strip_tags($r['description'] ?? ''), 0, 140, '…')) ?></div>
              </div>
            </a>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  <?php else: ?>
    <?php if (empty($mppSections)): ?>
      <?php if ($searchQuery !== ''): ?>
        <div class="empty">
          <p class="lead">No MPP found matching "<?= htmlspecialchars($searchQuery) ?>".</p>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <?php foreach ($mppSections as $section): $mpp = $section['mpp']; $items = $section['items']; ?>
        <section style="margin-bottom:32px">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px">
            <?php $mppAvatar = (!empty($mpp['profile_image'])) ? '../../uploads/profiles/' . $mpp['profile_image'] : '../../uploads/profiles/placeholder.png'; ?>
            <div style="display:flex;align-items:center;gap:12px">
                <img src="<?= htmlspecialchars($mppAvatar) ?>" alt="<?= htmlspecialchars($mpp['name']) ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;">
                <h3 style="margin:0">Gallery — <?= htmlspecialchars($mpp['name']) ?></h3>
              </div>
            <div style="display:flex;gap:8px;">
              <a class="btn subtle" href="progress_public.php?mpp=<?= (int)$mpp['id'] ?>">View all</a>
              <a class="btn primary" href="mpp_logs.php?mpp=<?= (int)$mpp['id'] ?>">View Logs</a>
            </div>
          </div>
          <div class="progress-grid">
            <?php foreach ($items as $r):
              $thumb = !empty($r['thumb']) ? '../../uploads/progress/' . $r['thumb'] : '../../uploads/progress/placeholder.png';
            ?>
              <article class="progress-card">
                <a href="progress_view.php?id=<?= (int)$r['id'] ?>" style="text-decoration:none;color:inherit">
                  <img src="<?= htmlspecialchars($thumb) ?>" alt="<?= htmlspecialchars($r['title']) ?>" class="progress-thumb">
                  <div class="progress-meta">
                    <div class="progress-title"><?= htmlspecialchars($r['title']) ?></div>
                    <div class="progress-info"><?= htmlspecialchars(date('M j, Y', strtotime($r['created_at']))) ?></div>
                    <div class="progress-excerpt"><?= htmlspecialchars(mb_strimwidth(strip_tags($r['description'] ?? ''), 0, 140, '…')) ?></div>
                  </div>
                </a>
              </article>
            <?php endforeach; ?>
            <?php if (empty($items)): ?>
              <div class="empty" style="grid-column: 1 / -1;">
                <p class="lead">No updates yet.</p>
              </div>
            <?php endif; ?>
          </div>
        </section>
      <?php endforeach; ?>
    <?php endif; ?>
  <?php endif; ?>

</main>

</body>
</html>
