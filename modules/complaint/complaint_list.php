<?php
// complaint_list.php - list complaints for logged-in student (or admin sees link to admin area)

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\','/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
function root_url($path = '') {
    global $webRoot;
    $path = ltrim($path, '/');
    return ($webRoot ?: '') . ($path ? "/{$path}" : '');
}

// Extract "Subject: ..." from the composed description (first matching line)
function extract_subject_from_description($desc) {
  if (!is_string($desc) || $desc === '') return '';
  if (preg_match('/^\s*Subject\s*:\s*(.+)$/mi', $desc, $m)) {
    return trim($m[1]);
  }
  return '';
}

$user = current_user();
if (!$user) {
    header('Location: ' . root_url('login.php')); exit;
}

try {
    if (in_array($user['role'] ?? '', ['mpp','admin'], true)) {
      // Admin/MPP: show all complaints (no deleted filter) to ensure visibility
      $stmt = $pdo->prepare("SELECT c.*, u.name AS owner,
        (SELECT COUNT(*) FROM complaint_audit a WHERE a.complaint_id = c.id) AS responses_count,
        (SELECT ci.id FROM complaint_images ci WHERE ci.complaint_id = c.id ORDER BY ci.id ASC LIMIT 1) AS image_id
        FROM complaints c LEFT JOIN users u ON u.id=c.user_id
        ORDER BY c.created_at DESC LIMIT 50");
      $stmt->execute();
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
      // Student: only own complaints
      $stmt = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM complaint_audit a WHERE a.complaint_id = c.id) AS responses_count,
        (SELECT ci.id FROM complaint_images ci WHERE ci.complaint_id = c.id ORDER BY ci.id ASC LIMIT 1) AS image_id
        FROM complaints c WHERE c.user_id = :uid ORDER BY c.created_at DESC");
      $stmt->execute([':uid'=>$user['id']]);
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    error_log('complaint_list error: ' . $e->getMessage());
    $rows = [];
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>My Complaints — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
</head>
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a href="<?= htmlspecialchars(root_url('index.php')) ?>" class="btn subtle">Home</a>
      <?php if ($user && in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
        <a class="btn primary" href="<?= htmlspecialchars(root_url('modules/complaint/admin_complaints.php')) ?>">Manage complaints</a>
      <?php else: ?>
        <a class="btn primary" href="<?= htmlspecialchars(root_url('modules/complaint/complaint_create.php')) ?>">Create complaint</a>
      <?php endif; ?>
      
    </nav>
  </header>

  <main class="container">
    <div class="card">
      <h2><?= in_array($user['role'] ?? '', ['mpp','admin'], true) ? 'All Complaints' : 'My Complaints' ?></h2>

      <?php if (empty($rows)): ?>
        <p class="lead">No complaints found.</p>
      <?php else: ?>
        <style>
          .complaint-list { display:flex; flex-direction:column; gap:14px; }
          .complaint-card { display:flex; gap:14px; background:#fff; border-radius:14px; box-shadow:0 10px 28px rgba(0,0,0,.08); overflow:hidden; border:1px solid #eef1f4; }
          .complaint-thumb { width:220px; height:160px; object-fit:cover; background:#e5e7eb; flex-shrink:0; }
          .complaint-body { padding:12px 14px; display:flex; flex-direction:column; gap:6px; flex:1; }
          .title-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
          .title { font-weight:800; font-size:20px; }
          .pill { display:inline-block; padding:4px 10px; border-radius:999px; font-size:12px; font-weight:700; }
          .pill.sev-low { background:#e0f2fe; color:#075985; }
          .pill.sev-medium { background:#fef3c7; color:#92400e; }
          .pill.sev-high { background:#fee2e2; color:#b91c1c; }
          .pill.sev-urgent { background:#fecaca; color:#7f1d1d; }
          .status-pill { padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px; display:inline-block; }
          .status-open { background:#fef3c7; color:#92400e; }
          .status-assigned { background:#e0f2fe; color:#075985; }
          .status-investigating { background:#ddd6fe; color:#5b21b6; }
          .status-resolved { background:#dcfce7; color:#166534; }
          .status-closed { background:#f3f4f6; color:#374151; }
          .micro-row { color:#6b7280; font-size:12px; }
          .desc { color:#374151; font-size:14px; line-height:1.5; max-height:6.4em; overflow:hidden; }
          .actions { margin-top:auto; display:flex; gap:8px; }
        </style>
        <div class="complaint-list">
          <?php foreach ($rows as $r): ?>
            <?php
              $status = strtolower(trim($r['status'] ?? 'open'));
              $statusClass = 'status-pill status-' . preg_replace('/\s+/', '', $status);
              $created = !empty($r['created_at']) ? date('j F Y', strtotime($r['created_at'])) : '';
              $sev = strtolower($r['severity'] ?? 'medium');
              $sevClass = 'pill sev-' . $sev;
              $imgId = (int)($r['image_id'] ?? 0);
              $subject = extract_subject_from_description($r['description'] ?? '');
              $displayTitle = $subject !== '' ? $subject : ('Ticket #' . htmlspecialchars($r['ticket_no'] ?? (string)$r['id'], ENT_QUOTES, 'UTF-8'));
              // Prefer secure server route; fallback to filesystem if DB image missing
              $imgSrc = $imgId > 0 ? 'serve_image.php?img=' . $imgId . '&thumb=1' : '';
              if ($imgId === 0) {
                $dirFs = __DIR__ . '/uploads/complaints/complaint_' . (int)$r['id'];
                if (is_dir($dirFs)) {
                  $entries = @scandir($dirFs);
                  $allowedExt = ['jpg','jpeg','png','webp','gif'];
                  $firstImage = '';
                  $thumbImage = '';
                  if (is_array($entries)) {
                    foreach ($entries as $en) {
                      if ($en === '.' || $en === '..') continue;
                      $lower = strtolower($en);
                      $ext = pathinfo($lower, PATHINFO_EXTENSION);
                      if (!in_array($ext, $allowedExt, true)) continue;
                      if (strpos($lower, 'thumb_') === 0 && $thumbImage === '') {
                        $thumbImage = $en; // prefer thumbnail if available
                      }
                      if ($firstImage === '') $firstImage = $en;
                    }
                  }
                  $chosen = $thumbImage !== '' ? $thumbImage : $firstImage;
                  if ($chosen !== '') {
                    $imgSrc = root_url('modules/complaint/uploads/complaints/complaint_' . (int)$r['id'] . '/' . $chosen);
                  }
                }
              }
              $owner = $r['owner'] ?? 'Student';
              $respCount = (int)($r['responses_count'] ?? 0);
            ?>
            <article class="complaint-card">
              <?php if ($imgSrc): ?>
                <img class="complaint-thumb" src="<?= htmlspecialchars($imgSrc) ?>" alt="Complaint image">
              <?php else: ?>
                <div class="complaint-thumb" style="display:flex;align-items:center;justify-content:center;color:#9ca3af;">No Image</div>
              <?php endif; ?>
              <div class="complaint-body">
                <div class="title-row">
                  <div class="title"><?= $displayTitle ?></div>
                  <span class="<?= htmlspecialchars($sevClass) ?>"><?= htmlspecialchars(ucfirst($sev)) ?></span>
                  <span class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <div class="micro-row">
                  <?php if (in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
                    <strong><?= htmlspecialchars($owner) ?></strong> ·
                  <?php endif; ?>
                  <?= htmlspecialchars($created) ?> · <?= (int)$respCount ?> responses
                </div>
                <div class="desc"><?= nl2br(htmlspecialchars(trim($r['description'] ?? ''))) ?></div>
                <div class="actions">
                  <?php if (in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
                    <a class="btn subtle" href="admin_complaint_view.php?id=<?= (int)$r['id'] ?>">View / Respond</a>
                    <a class="btn" href="complaint_delete.php?id=<?= (int)$r['id'] ?>&redirect=admin" onclick="return confirm('Delete this complaint? This action cannot be undone.');" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</a>
                  <?php else: ?>
                    <a class="btn subtle" href="complaint_view.php?id=<?= (int)$r['id'] ?>">View</a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>
</body>
</html>
