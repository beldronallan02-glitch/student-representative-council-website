<?php
// modules/facilities/facility_manage.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
  header('Location: /MPPCONNECT/login.php'); exit;
}

// Helpers
function facilities_archive_supported(PDO $pdo): bool {
  try {
    $cols = $pdo->query("SHOW COLUMNS FROM facilities")->fetchAll(PDO::FETCH_COLUMN, 0);
    if (!is_array($cols)) return false;
    return in_array('status', $cols, true) || in_array('is_archived', $cols, true);
  } catch (Throwable $e) { return false; }
}

function facility_first_image_webpath($id) {
  $baseDir = __DIR__ . '/../../uploads/facilities/facility_' . (int)$id;
  $baseWeb = '../../uploads/facilities/facility_' . (int)$id;
  if (is_dir($baseDir)) {
  $patterns = ['*.jpg','*.jpeg','*.png','*.gif','*.webp'];
  foreach ($patterns as $p) {
    $matches = glob($baseDir . '/' . $p, GLOB_NOSORT);
    if (!empty($matches)) {
    return $baseWeb . '/' . basename($matches[0]);
    }
  }
  }
  return '../../uploads/progress/placeholder.png';
}

$err = null;
$archivable = facilities_archive_supported($pdo);
$archivedView = $archivable && isset($_GET['archived']) && $_GET['archived'] === '1';
// Preload booking counts per facility (best-effort)
$bookingCounts = [];
try {
  $q = $pdo->query("SELECT facility_id, COUNT(*) AS cnt FROM facility_bookings GROUP BY facility_id");
  foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $bookingCounts[(int)$row['facility_id']] = (int)$row['cnt'];
  }
} catch (Throwable $e) { /* table may not exist; ignore */ }
try {
  // Prefer status field if available
  if ($archivedView) {
    try {
      $stmt = $pdo->query("SELECT * FROM facilities WHERE status='archived' ORDER BY name ASC");
      $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e1) {
      $stmt = $pdo->query("SELECT * FROM facilities WHERE is_archived=1 ORDER BY name ASC");
      $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
  } else {
    try {
      $stmt = $pdo->query("SELECT * FROM facilities WHERE status IS NULL OR status='active' ORDER BY name ASC");
      $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e2) {
      // Fallback: boolean is_archived
      try {
        $stmt = $pdo->query("SELECT * FROM facilities WHERE is_archived=0 OR is_archived IS NULL ORDER BY name ASC");
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
      } catch (Throwable $e3) {
        // Last resort: show all
        $stmt = $pdo->query("SELECT * FROM facilities ORDER BY name ASC");
        $facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
      }
    }
  }
} catch (Exception $e) {
  $facilities = [];
  $err = $e->getMessage();
}
$csrf = csrf_token();
?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Facilities — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="../../css/style.css">
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

  .page { max-width: 1200px; margin: 20px auto; }
.toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; }
.grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:18px; margin-top:16px; }
.cardx { background:#fff; border-radius:16px; overflow:hidden; box-shadow:0 14px 34px rgba(0,0,0,.08); display:flex; flex-direction:column; }
.cardx:hover { transform: translateY(-4px); box-shadow:0 22px 48px rgba(0,0,0,0.15); transition: transform .18s ease, box-shadow .18s ease; }
.thumb { width:100%; height:180px; object-fit:cover; background:#e5e7eb; }
.meta { padding:14px; }
.title { font-weight:800; font-size:17px; }
.info { color:#6b7280; font-size:13px; margin:6px 0 10px; }
.desc { color:#374151; font-size:14px; line-height:1.6; margin-top:8px; white-space:pre-line; word-break:break-word; max-height: 5.0em; overflow:hidden; }
.pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
.pill.archived { background:#fee2e2; color:#b91c1c; }
.pill.active { background:#dcfce7; color:#166534; }
.actions { display:flex; gap:8px; align-items:center; margin-top:auto; padding:12px 14px; border-top:1px solid #eef1f4; }
.notice { background:#e6fff0; border-left:4px solid #37b24d; padding:10px 12px; border-radius:10px; margin-top:10px; color:#135f1a; }
.error { background:#ffe6e6; border-left:4px solid #ff6b6b; padding:10px 12px; border-radius:10px; margin-top:10px; color:#b00020; }
</style>
</head><body>
<header class="topbar"><div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
<nav class="nav"><a class="btn subtle" href="../../index.php">Home</a></header>

<main class="container page">
  <div class="card">
    <div class="toolbar">
      <h2 style="margin:0">Facilities</h2>
      <div style="display:flex;gap:8px;align-items:center">
        <?php if ($archivable): ?>
          <?php if ($archivedView): ?>
            <a class="btn subtle" href="facility_manage.php">Show Active</a>
          <?php else: ?>
            <a class="btn subtle" href="facility_manage.php?archived=1">Show Archived</a>
          <?php endif; ?>
        <?php endif; ?>
        <a class="btn primary" href="facility_manage.php?action=create">+ Add Facility</a>
      </div>
    </div>

    <?php if ($err): ?><div class="error"><?= htmlspecialchars($err) ?></div><?php endif; ?>
    <?php if (!empty($_GET['msg'])): ?>
      <div class="notice"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <?php if (!empty($_GET['action']) && $_GET['action'] === 'create'): ?>
      <h3>Create facility</h3>
      <?php $facility = null; $action = 'facility_save.php'; include __DIR__ . '/facility_form.php'; ?>
    <?php elseif (!empty($_GET['id']) && ($_GET['action'] ?? '') === 'edit'):
        $fid = (int)$_GET['id'];
        $s = $pdo->prepare("SELECT * FROM facilities WHERE id=? LIMIT 1");
        $s->execute([$fid]);
        $facility = $s->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$facility) echo '<div class="micro">Facility not found.</div>';
        else { $action = 'facility_save.php?id=' . $fid; $csrf = csrf_token(); include __DIR__ . '/facility_form.php'; }
    else: ?>

      <?php if (empty($facilities)): ?>
        <p class="micro">No facilities found.</p>
      <?php else: ?>
        <div class="grid">
          <?php foreach($facilities as $f):
            $img = facility_first_image_webpath($f['id']);
            $status = $archivable && ((($f['status'] ?? '') === 'archived') || (!empty($f['is_archived']))) ? 'archived' : 'active';
            $cnt = $bookingCounts[(int)$f['id']] ?? 0;
          ?>
            <article class="cardx">
              <img class="thumb" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($f['name']) ?>">
              <div class="meta">
                <div class="title"><?= htmlspecialchars($f['name']) ?></div>
                <div class="info"><?= htmlspecialchars($f['location']) ?> · capacity <?= htmlspecialchars($f['capacity'] ?: '—') ?></div>
                <div class="desc"><?= htmlspecialchars(mb_strimwidth(trim($f['description'] ?? ''), 0, 140, '…')) ?></div>
                <?php if ($archivable): ?>
                  <div style="margin-top:8px">
                    <span class="pill <?= $status==='archived' ? 'archived':'active' ?>"><?= $status==='archived' ? 'Archived' : 'Active' ?></span>
                  </div>
                <?php endif; ?>
              </div>
              <div class="actions">
                <a class="btn subtle" href="facility_manage.php?action=edit&id=<?= (int)$f['id'] ?>">Edit</a>
                <a class="btn subtle" href="admin_bookings.php?facility_id=<?= (int)$f['id'] ?>">Bookings (<?= (int)$cnt ?>)</a>
                <?php if ($archivable): ?>
                  <?php if ($status==='archived'): ?>
                    <form method="post" action="facility_action.php" onsubmit="return confirm('Unarchive this facility?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                      <input type="hidden" name="action" value="unarchive">
                      <button class="btn ghost" type="submit">Unarchive</button>
                    </form>
                    <form method="post" action="facility_action.php" onsubmit="return confirm('Delete facility? This removes bookings and images.');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                      <input type="hidden" name="action" value="delete">
                      <button class="btn danger" type="submit">Delete</button>
                    </form>
                  <?php else: ?>
                    <form method="post" action="facility_action.php" onsubmit="return confirm('Archive this facility?');">
                      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                      <input type="hidden" name="action" value="archive">
                      <button class="btn ghost" type="submit">Archive</button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <form method="post" action="facility_action.php" onsubmit="return confirm('Delete facility? This removes bookings and images.');">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
                    <input type="hidden" name="action" value="delete">
                    <button class="btn danger" type="submit">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>
</main>
</body></html>
