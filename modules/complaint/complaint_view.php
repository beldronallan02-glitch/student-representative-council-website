<?php
// complaint_view.php - view a single complaint, show images and audit log

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

// Parse structured key-value pairs embedded in description (from complaint_create.php)
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
  $parts = preg_split("/\R{2,}/", $text, 2); // header lines then blank line then details
  $header = $parts[0] ?? '';
  $details = $parts[1] ?? '';
  $lines = preg_split('/\R/', (string)$header);
  foreach ($lines as $ln) {
    $ln = trim((string)$ln);
    if ($ln === '' || strpos($ln, ':') === false) continue;
    // Try to match known labels
    if (preg_match('/^subject\s*:\s*(.*)$/i', $ln, $m)) {
      $result['subject'] = trim($m[1]); continue;
    }
    if (preg_match('/^incident\s*date\s*:\s*(.*)$/i', $ln, $m)) {
      $result['incident_date'] = trim($m[1]); continue;
    }
    if (preg_match('/^incident\s*time\s*:\s*(.*)$/i', $ln, $m)) {
      $result['incident_time'] = trim($m[1]); continue;
    }
    if (preg_match('/^location\s*:\s*(.*)$/i', $ln, $m)) {
      $result['location'] = trim($m[1]); continue;
    }
    if (preg_match('/^parties\s*involved\s*:\s*(.*)$/i', $ln, $m)) {
      $result['parties'] = trim($m[1]); continue;
    }
    if (preg_match('/^witnesses\s*:\s*(.*)$/i', $ln, $m)) {
      $result['witnesses'] = trim($m[1]); continue;
    }
    if (preg_match('/^(preferred\s*resolution|desired\s*action)\s*:\s*(.*)$/i', $ln, $m)) {
      $result['preferred_resolution'] = trim($m[2]); continue;
    }
    if (preg_match('/^consent\s*to\s*share\s*with\s*relevant\s*department\s*:\s*(.*)$/i', $ln, $m)) {
      $result['consent_share'] = trim($m[1]); continue;
    }
  }
  $result['details'] = trim((string)$details);
  return $result;
}

$user = current_user();
if (!$user) {
    header('Location: ' . root_url('login.php')); exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: complaint_list.php'); exit; }

try {
    // Do not reference deleted_at if the column may not exist in this schema
    $stmt = $pdo->prepare("SELECT c.*, u.name AS owner_name FROM complaints c LEFT JOIN users u ON u.id=c.user_id WHERE c.id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $complaint = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$complaint) { throw new Exception('Not found'); }

    // permission: student only view own (unless admin/mpp)
    $is_admin = in_array($user['role'] ?? '', ['mpp','admin'], true);
    if (!$is_admin && $complaint['user_id'] != $user['id']) {
        header('HTTP/1.1 403 Forbidden'); echo 'Forbidden'; exit;
    }

    // images
    $stmt2 = $pdo->prepare("SELECT id, filename, original_name, mime FROM complaint_images WHERE complaint_id = :cid ORDER BY id ASC");
    $stmt2->execute([':cid'=>$id]);
    $images = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    // audit
    $ast = $pdo->prepare("SELECT a.*, u.name as actor FROM complaint_audit a LEFT JOIN users u ON u.id=a.user_id WHERE a.complaint_id = :cid ORDER BY a.created_at ASC");
    $ast->execute([':cid'=>$id]);
    $audit = $ast->fetchAll(PDO::FETCH_ASSOC);

    // Determine primary image (first image if available)
    $primaryImageId = !empty($images) ? (int)$images[0]['id'] : 0;

    // Parse description into structured fields
    $parsed = parse_complaint_description($complaint['description'] ?? '');
    $displaySubject = $parsed['subject'] !== '' ? $parsed['subject'] : 'Complaint';

} catch (Exception $e) {
    error_log('complaint_view error: ' . $e->getMessage());
    header('Location: complaint_list.php'); exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Complaint — <?= htmlspecialchars($displaySubject) ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
  <style>
    /* Ensure global gradient stays fixed while scrolling */
    html, body { min-height: 100%; margin: 0; padding: 0; }
    body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

    /* Layout (match admin view) */
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
    .timeline-item { background:#fafafa; border:1px solid #eef1f4; border-radius:10px; padding:10px; }
    @media (max-width: 980px) { .grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
      .nav { position: static !important; inset: auto !important; background: transparent !important; backdrop-filter: none !important; transform: none !important; z-index: auto !important; }
      .nav.active { position: fixed !important; inset: 0 !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px) !important; z-index: 999 !important; }
    }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a href="complaint_list.php" class="btn subtle">Back</a>
      <?php if ($is_admin): ?>
        <a class="btn primary" href="admin_complaint_view.php?id=<?= (int)$id ?>">Admin actions</a>
      <?php else: ?>
        <?php if ($complaint['status'] === 'new'): ?>
          <a class="btn subtle" href="complaint_edit.php?id=<?= (int)$id ?>">Edit</a>
          <a class="btn subtle" href="complaint_delete.php?id=<?= (int)$id ?>">Withdraw</a>
        <?php endif; ?>
      <?php endif; ?>
      <a class="btn subtle" href="<?= htmlspecialchars(root_url('index.php')) ?>">Home</a>
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
          <?php $sev = strtolower($complaint['severity'] ?? ''); $sevClass = $sev? 'pill sev-' . $sev : ''; $st = strtolower($complaint['status'] ?? 'open'); $stClass = 'status-pill status-' . preg_replace('/\s+/', '', $st); ?>
          <table class="info-table">
            <tr><th>Ticket No</th><td><?= htmlspecialchars($complaint['ticket_no'] ?? (string)$complaint['id']) ?></td></tr>
            <tr><th>Status</th><td><span class="<?= htmlspecialchars($stClass) ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td></tr>
            <tr><th>Severity</th><td><?= $sev ? '<span class="'.htmlspecialchars($sevClass).'">'.htmlspecialchars(ucfirst($sev)).'</span>' : '-' ?></td></tr>
            <tr><th>Category</th><td><?= htmlspecialchars($complaint['category'] ?? '') ?></td></tr>
            <tr><th>Created</th><td><?= htmlspecialchars(date('M j, Y H:i', strtotime($complaint['created_at']))) ?></td></tr>
          </table>

          <h3 class="section-title" style="margin-top:18px;">Reporter Details</h3>
          <table class="info-table">
            <tr><th>Reporter</th><td><?= htmlspecialchars($complaint['owner_name'] ?? 'Student') ?></td></tr>
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
          <div style="white-space:pre-line; font-size:14px; color:#111827;"><?= htmlspecialchars($parsed['details'] !== '' ? $parsed['details'] : ($complaint['description'] ?? '')) ?></div>

          <?php if (!empty($images) && count($images) > 1): ?>
            <h3 class="section-title" style="margin-top:18px;">Additional Images</h3>
            <div class="thumbs">
              <?php foreach ($images as $img): ?>
                <?php if (!empty($primaryImageId) && (int)$img['id'] === (int)$primaryImageId) continue; ?>
                <a href="serve_image.php?img=<?= (int)$img['id'] ?>" target="_blank"><img src="serve_image.php?img=<?= (int)$img['id'] ?>&thumb=1" alt="<?= htmlspecialchars($img['original_name']) ?>"></a>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <aside class="card">
          <h3 class="section-title">Activity / Timeline</h3>
          <?php if (empty($audit)): ?>
            <p class="micro">No activity yet.</p>
          <?php else: foreach ($audit as $a): ?>
            <div style="margin-top:8px;" class="timeline-item">
              <div class="micro"><?= htmlspecialchars($a['actor'] ?? 'System') ?> · <?= htmlspecialchars(date('M j, Y H:i', strtotime($a['created_at']))) ?></div>
              <div><?= htmlspecialchars($a['action']) ?> <?= !empty($a['note']) ? ' — ' . htmlspecialchars($a['note']) : '' ?></div>
            </div>
          <?php endforeach; endif; ?>
        </aside>
      </div>
    </div>
  </main>
</body>
</html>
