<?php
// admin_complaints.php - admin overview (same style)

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
function root_url($path = '') { global $webRoot; $path = ltrim($path, '/'); return ($webRoot ?: '') . ($path ? "/{$path}" : ''); }

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: ' . root_url('login.php')); exit;
}

$qStatus = $_GET['status'] ?? '';
$params = [];
// Show all complaints by default (include deleted/archived), allow status filter
$whereParts = [];
if ($qStatus !== '') {
  $whereParts[] = "c.status = :status";
  $params[':status'] = $qStatus;
}
$where = !empty($whereParts) ? (' WHERE ' . implode(' AND ', $whereParts)) : '';

try {
  $stmt = $pdo->prepare("SELECT c.*, u.name AS owner,
    (SELECT COUNT(*) FROM complaint_audit a WHERE a.complaint_id=c.id) AS responses_count,
    (SELECT ci.id FROM complaint_images ci WHERE ci.complaint_id=c.id ORDER BY ci.id ASC LIMIT 1) AS image_id
    FROM complaints c LEFT JOIN users u ON u.id=c.user_id {$where}
    ORDER BY c.created_at DESC LIMIT 200");
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  error_log('admin_complaints error: '.$e->getMessage());
  $rows = [];
}

?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<style>
.filter-row { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
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
.table-card { background:#fff; border-radius:14px; box-shadow:0 10px 28px rgba(0,0,0,.08); overflow:hidden; }
.complaints-table { width:100%; border-collapse:separate; border-spacing:0; }
.complaints-table th, .complaints-table td { padding:12px 14px; border-top:1px solid #eef1f4; text-align:left; vertical-align:top; }
.complaints-table thead th { background:#f8fafc; color:#374151; font-weight:700; border-top:none; font-size:13px; }
.complaints-table tbody tr:hover { background:#f9fbff; }
.thumb { width:100px; height:70px; object-fit:cover; border-radius:8px; background:#e5e7eb; }
.actions { display:flex; gap:8px; justify-content:flex-end; align-items:center; }

/* Defensive: avoid mobile overlay blocking scroll */
@media (max-width: 768px) {
  .nav { position: static !important; inset: auto !important; background: transparent !important; backdrop-filter: none !important; transform: none !important; z-index: auto !important; }
  .nav.active { position: fixed !important; inset: 0 !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px) !important; z-index: 999 !important; }
}
</style>
</head>
<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a href="<?= htmlspecialchars(root_url('index.php')) ?>" class="btn subtle">Home</a>
      <a href="complaint_list.php" class="btn subtle">User view</a>
     
    </nav>
  </header>

  <main class="container">
    <div class="card">
      <h2>Manage Complaints</h2>

      <form method="get" class="form filter-row">
        <label>Filter status
          <select name="status">
            <option value="">Any</option>
            <option value="open" <?= $qStatus==='open' ? 'selected' : '' ?>>Open</option>
            <option value="assigned" <?= $qStatus==='assigned' ? 'selected' : '' ?>>Assigned</option>
            <option value="investigating" <?= $qStatus==='investigating' ? 'selected' : '' ?>>Investigating</option>
            <option value="resolved" <?= $qStatus==='resolved' ? 'selected' : '' ?>>Resolved</option>
            <option value="closed" <?= $qStatus==='closed' ? 'selected' : '' ?>>Closed</option>
          </select>
        </label>
        <button class="btn primary" type="submit">Filter</button>
      </form>

      <?php if (empty($rows)): ?>
        <p class="lead">No complaints found.</p>
      <?php else: ?>
        <div class="table-card">
          <table class="complaints-table">
            <thead>
              <tr>
                <th>Image</th>
                <th>Ticket</th>
                <th>Owner</th>
                <th>Status</th>
                <th>Severity</th>
                <th>Created</th>
                <th>Responses</th>
                <th>Excerpt</th>
                <th style="text-align:right">Action</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
              <?php
                $status = strtolower(trim($r['status'] ?? 'open'));
                $statusClass = 'status-pill status-' . preg_replace('/\s+/', '', $status);
                $created = !empty($r['created_at']) ? date('j F Y', strtotime($r['created_at'])) : '';
                $sev = strtolower($r['severity'] ?? 'medium');
                $sevClass = 'pill sev-' . $sev;
                $imgId = (int)($r['image_id'] ?? 0);
                $imgSrc = $imgId > 0 ? root_url('modules/complaint/serve_image.php') . '?img=' . $imgId . '&thumb=1' : '';
                $owner = $r['owner'] ?? 'Student';
                $respCount = (int)($r['responses_count'] ?? 0);
                $excerpt = mb_strimwidth(trim($r['description'] ?? ''), 0, 120, '…');
              ?>
              <tr>
                <td>
                  <?php if ($imgSrc): ?>
                    <img class="thumb" src="<?= htmlspecialchars($imgSrc) ?>" alt="Image">
                  <?php else: ?>
                    <span class="micro">—</span>
                  <?php endif; ?>
                </td>
                <td>#<?= htmlspecialchars($r['ticket_no'] ?? (string)$r['id']) ?></td>
                <td><?= htmlspecialchars($owner) ?></td>
                <td><span class="<?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></td>
                <td><span class="<?= htmlspecialchars($sevClass) ?>"><?= htmlspecialchars(ucfirst($sev)) ?></span></td>
                <td><?= htmlspecialchars($created) ?></td>
                <td><?= (int)$respCount ?></td>
                <td><?= htmlspecialchars($excerpt) ?></td>
                <td>
                  <div class="actions">
                    <a class="btn subtle" href="admin_complaint_view.php?id=<?= (int)$r['id'] ?>">Open</a>
                    <a class="btn" href="complaint_delete.php?id=<?= (int)$r['id'] ?>&redirect=admin" onclick="return confirm('Delete this complaint? This will archive it and cannot be undone.');" style="background:#ef4444;border-color:#dc2626;color:#fff">Delete</a>
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
</body>
</html>
