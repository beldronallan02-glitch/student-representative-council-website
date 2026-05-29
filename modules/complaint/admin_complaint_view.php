<?php
// admin_complaint_view.php - admin detail view and change status / assign

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

// Parse structured key-value pairs embedded in description (matches complaint_create.php composition)
function parse_complaint_description($text) {
  $result = [
    'subject' => '',
    'incident_date' => '',
    'incident_time' => '',
    'location' => '',
    'parties' => '',
    'witnesses' => '',
    'preferred_resolution' => '',
    'consent_share' => '',
    'details' => ''
  ];
  if (!is_string($text) || $text === '') return $result;
  $parts = preg_split("/\R{2,}/", $text, 2); // header block, blank line, then details
  $header = $parts[0] ?? '';
  $details = $parts[1] ?? '';
  $lines = preg_split('/\R/', (string)$header);
  foreach ($lines as $ln) {
    $ln = trim((string)$ln);
    if ($ln === '' || strpos($ln, ':') === false) continue;
    if (preg_match('/^subject\s*:\s*(.*)$/i', $ln, $m)) { $result['subject'] = trim($m[1]); continue; }
    if (preg_match('/^incident\s*date\s*:\s*(.*)$/i', $ln, $m)) { $result['incident_date'] = trim($m[1]); continue; }
    if (preg_match('/^incident\s*time\s*:\s*(.*)$/i', $ln, $m)) { $result['incident_time'] = trim($m[1]); continue; }
    if (preg_match('/^location\s*:\s*(.*)$/i', $ln, $m)) { $result['location'] = trim($m[1]); continue; }
    if (preg_match('/^parties\s*involved\s*:\s*(.*)$/i', $ln, $m)) { $result['parties'] = trim($m[1]); continue; }
    if (preg_match('/^witnesses\s*:\s*(.*)$/i', $ln, $m)) { $result['witnesses'] = trim($m[1]); continue; }
    if (preg_match('/^(preferred\s*resolution|desired\s*action)\s*:\s*(.*)$/i', $ln, $m)) { $result['preferred_resolution'] = trim($m[2]); continue; }
    if (preg_match('/^consent\s*to\s*share\s*with\s*relevant\s*department\s*:\s*(.*)$/i', $ln, $m)) { $result['consent_share'] = trim($m[1]); continue; }
  }
  $result['details'] = trim((string)$details);
  return $result;
}

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('Location: ' . root_url('login.php')); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: admin_complaints.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT c.*, u.name AS owner_name FROM complaints c LEFT JOIN users u ON u.id=c.user_id WHERE c.id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) throw new Exception('Not found');

    $imgs = $pdo->prepare("SELECT * FROM complaint_images WHERE complaint_id = :cid");
    $imgs->execute([':cid'=>$id]);
    $images = $imgs->fetchAll(PDO::FETCH_ASSOC);

    $audit = $pdo->prepare("SELECT a.*, uu.name as actor FROM complaint_audit a LEFT JOIN users uu ON uu.id=a.user_id WHERE a.complaint_id = :cid ORDER BY a.created_at ASC");
    $audit->execute([':cid'=>$id]);
    $log = $audit->fetchAll(PDO::FETCH_ASSOC);

    // list officers (users with role mpp)
    $off = $pdo->prepare("SELECT id, name FROM users WHERE role IN ('mpp','admin') ORDER BY name ASC");
    $off->execute();
    $officers = $off->fetchAll(PDO::FETCH_ASSOC);

    // Determine primary image (first image if available)
    $primaryImageId = !empty($images) ? (int)$images[0]['id'] : 0;
    // Parse description to extract subject and inline fields
    $parsed = parse_complaint_description($c['description'] ?? '');
    $displaySubject = $parsed['subject'] !== '' ? $parsed['subject'] : ('Complaint #' . ($c['ticket_no'] ?? (string)$c['id']));
    $reporterLabel = (isset($c['anonymous_token']) && $c['anonymous_token'] && empty($c['user_id'])) ? 'Anonymous' : ($c['owner_name'] ?? 'Student');

} catch (Exception $e) {
    error_log('admin_complaint_view error: '.$e->getMessage());
    header('Location: admin_complaints.php'); exit;
}

// handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'assign' && isset($_POST['assigned_to'])) {
        $assigned_to = intval($_POST['assigned_to']);
        $u = $pdo->prepare("UPDATE complaints SET assigned_to = :ass, status='assigned', updated_at = NOW() WHERE id = :id");
        $u->execute([':ass'=>$assigned_to, ':id'=>$id]);
        $ins = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'assigned',:note)");
        $ins->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>'Assigned to user #' . $assigned_to]);
        header("Location: admin_complaint_view.php?id={$id}"); exit;
    } elseif ($action === 'status' && isset($_POST['new_status'])) {
      $new = $_POST['new_status'];
      // Align with DB enum: open, assigned, investigating, resolved, closed
      $allowed = ['open','assigned','investigating','resolved','closed'];
        if (in_array($new, $allowed, true)) {
            $u = $pdo->prepare("UPDATE complaints SET status = :st, updated_at = NOW() WHERE id = :id");
            $u->execute([':st'=>$new, ':id'=>$id]);
            $note = trim($_POST['note'] ?? '');
        $ins = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'status_changed',:note)");
            $ins->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>$note ?: 'Status changed to ' . $new]);
            header("Location: admin_complaint_view.php?id={$id}"); exit;
        }
    } elseif ($action === 'add_note' && !empty($_POST['note'])) {
        $note = trim($_POST['note']);
        $ins = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'note',:note)");
        $ins->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>$note]);
        header("Location: admin_complaint_view.php?id={$id}"); exit;
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

/* Layout */
.page-wrap { max-width: 1200px; margin: 0 auto; padding: 28px 20px 60px; }
.hero-title { font-size: 28px; font-weight: 800; margin: 0 0 12px; color:#0b1c33; }
.hero-image { background:#fff; border-radius: 16px; box-shadow:0 16px 40px rgba(0,0,0,0.10); border:1px solid #eef1f4; overflow:hidden; }
.hero-image img { width:100%; height:auto; display:block; }
.grid { display:grid; grid-template-columns: 2fr 1fr; gap:18px; align-items:start; margin-top:18px; }
.card { background:#fff; border-radius:16px; box-shadow:0 12px 32px rgba(0,0,0,0.08); border:1px solid #eef1f4; padding:18px 20px; }
.section-title { font-size:18px; font-weight:800; margin:0 0 10px; color:#0b1c33; }
.info-table { width:100%; border-collapse:collapse; }
.info-table th, .info-table td { text-align:left; padding:10px 12px; border-bottom:1px solid #eef1f4; vertical-align:top; }
.info-table th { width:260px; color:#6b7280; font-weight:700; background:#f9fafb; }
.info-table td { color:#111827; background:#ffffff; }
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
.thumbs { display:flex; flex-wrap:wrap; gap:10px; }
.thumbs img { width:150px; height:100px; object-fit:cover; border-radius:10px; box-shadow:0 6px 16px rgba(0,0,0,0.08); }
.two-forms { display:grid; grid-template-columns: 1fr; gap:12px; }
.form-field { display:flex; flex-direction:column; gap:6px; }
.form-field select, .form-field input[type="text"], .form-field textarea { width:100%; padding:10px 12px; border:1px solid #d6d9e0; border-radius:10px; }
.timeline-item { background:#fafafa; border:1px solid #eef1f4; border-radius:10px; padding:10px; }
@media (max-width: 980px) { .grid { grid-template-columns: 1fr; } }
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
      <a href="<?= htmlspecialchars(root_url('modules/complaint/complaint_list.php')) ?>" class="btn subtle">Back</a>
      <a href="<?= htmlspecialchars(root_url('index.php')) ?>" class="btn subtle">Home</a>

    </nav>
  </header>

  <main class="container">
    <div class="page-wrap">
      <h2 class="hero-title"><?= htmlspecialchars($displaySubject) ?></h2>
      <?php if (!empty($primaryImageId)): ?>
        <div class="hero-image"><img src="serve_image.php?img=<?= (int)$primaryImageId ?>" alt="Primary image"></div>
      <?php endif; ?>

      <div class="grid">
        <section class="card">
          <h3 class="section-title">Complaint Information</h3>
          <?php $sev = strtolower($c['severity'] ?? ''); $sevClass = $sev? 'pill sev-' . $sev : ''; $st = strtolower($c['status'] ?? 'open'); $stClass = 'status-pill status-' . preg_replace('/\s+/', '', $st); ?>
          <table class="info-table">
            <tr><th>Ticket No</th><td><?= htmlspecialchars($c['ticket_no'] ?? (string)$c['id']) ?></td></tr>
            <tr><th>Status</th><td><span class="<?= htmlspecialchars($stClass) ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td></tr>
            <tr><th>Severity</th><td><?= $sev ? '<span class="'.htmlspecialchars($sevClass).'">'.htmlspecialchars(ucfirst($sev)).'</span>' : '-' ?></td></tr>
            <tr><th>Category</th><td><?= htmlspecialchars($c['category'] ?? '') ?></td></tr>
            <tr><th>Created</th><td><?= htmlspecialchars(date('M j, Y H:i', strtotime($c['created_at']))) ?></td></tr>
            <tr><th>Assigned To</th><td><?= isset($c['assigned_to']) && $c['assigned_to'] ? 'User #'.(int)$c['assigned_to'] : '—' ?></td></tr>
          </table>

          <h3 class="section-title" style="margin-top:18px;">Reporter Details</h3>
          <table class="info-table">
            <tr><th>Reporter</th><td><?= htmlspecialchars($reporterLabel) ?></td></tr>
          </table>

          <h3 class="section-title" style="margin-top:18px;">Incident Details</h3>
          <table class="info-table">
            <tr><th>Incident Date</th><td><?= htmlspecialchars($parsed['incident_date'] ?: '-') ?></td></tr>
            <tr><th>Incident Time</th><td><?= htmlspecialchars($parsed['incident_time'] ?: '-') ?></td></tr>
            <tr><th>Location</th><td><?= htmlspecialchars($parsed['location'] ?: '-') ?></td></tr>
            <tr><th>Parties Involved</th><td><?= htmlspecialchars($parsed['parties'] ?: '-') ?></td></tr>
            <tr><th>Witnesses</th><td><?= htmlspecialchars($parsed['witnesses'] ?: '-') ?></td></tr>
            <tr><th>Preferred Resolution</th><td><?= htmlspecialchars($parsed['preferred_resolution'] ?: '-') ?></td></tr>
            <tr><th>Consent to share</th><td><?= htmlspecialchars($parsed['consent_share'] ?: '-') ?></td></tr>
          </table>

          <h3 class="section-title" style="margin-top:18px;">Details</h3>
          <div style="white-space:pre-line; font-size:14px; color:#111827;"><?= htmlspecialchars($parsed['details'] !== '' ? $parsed['details'] : ($c['description'] ?? '')) ?></div>

          <?php if (!empty($images) && count($images) > 1): ?>
            <h3 class="section-title" style="margin-top:18px;">Additional Images</h3>
            <div class="thumbs">
              <?php foreach ($images as $img): ?>
                <?php if (!empty($primaryImageId) && (int)$img['id'] === (int)$primaryImageId) continue; ?>
                <a href="serve_image.php?img=<?= (int)$img['id'] ?>" target="_blank"><img src="serve_image.php?img=<?= (int)$img['id'] ?>&thumb=1" alt="Image"></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <aside class="card">
          <h3 class="section-title">Admin Actions / Status</h3>
          <div class="two-forms">
            <form method="post" class="form-field">
              <label style="font-weight:700;">Assign to</label>
              <select name="assigned_to">
                <option value="">-- choose officer --</option>
                <?php foreach ($officers as $of): ?>
                  <option value="<?= (int)$of['id'] ?>" <?= ($c['assigned_to'] == $of['id']) ? 'selected' : '' ?>><?= htmlspecialchars($of['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <input type="hidden" name="action" value="assign">
              <button class="btn subtle" type="submit" style="margin-top:6px;">Assign</button>
            </form>

            <form method="post" class="form-field">
              <label style="font-weight:700;">Change status</label>
              <select name="new_status">
                <option value="open" <?= ($c['status']==='open'?'selected':'') ?>>Open</option>
                <option value="assigned" <?= ($c['status']==='assigned'?'selected':'') ?>>Assigned</option>
                <option value="investigating" <?= ($c['status']==='investigating'?'selected':'') ?>>Investigating</option>
                <option value="resolved" <?= ($c['status']==='resolved'?'selected':'') ?>>Resolved</option>
                <option value="closed" <?= ($c['status']==='closed'?'selected':'') ?>>Closed</option>
              </select>
              <input type="hidden" name="action" value="status">
              <label style="font-weight:700; margin-top:8px;">Note (optional)</label>
              <input type="text" name="note" placeholder="Add a short note">
              <button class="btn primary" type="submit" style="margin-top:6px;">Update status</button>
            </form>
          </div>

          <div style="margin-top:16px;">
            <h3 class="section-title">Activity / Notes</h3>
            <form method="post" class="form-field" style="margin-bottom:10px">
              <input type="hidden" name="action" value="add_note">
              <label style="font-weight:700;">Add note</label>
              <textarea name="note" rows="3" required></textarea>
              <button class="btn subtle" type="submit" style="margin-top:6px;">Add note</button>
            </form>
            <?php if (!empty($log)): foreach ($log as $l): ?>
              <div style="margin-top:8px;" class="timeline-item">
                <div class="micro"><?= htmlspecialchars($l['actor'] ?? 'System') ?> · <?= htmlspecialchars(date('M j, Y H:i', strtotime($l['created_at']))) ?></div>
                <div><?= htmlspecialchars($l['action']) ?> <?= !empty($l['note']) ? ' — ' . htmlspecialchars($l['note']) : '' ?></div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </aside>
      </div>
    </div>
  </main>
</body>
</html>
