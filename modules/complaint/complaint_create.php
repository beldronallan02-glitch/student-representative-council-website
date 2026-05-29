<?php
// complaint_create.php — create a new complaint using a continuous, inline form

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/* Resolve web root */
$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\','/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
function root_url($path='') {
    global $webRoot;
    $path = ltrim($path, '/');
    return ($webRoot ?: '') . ($path ? "/{$path}" : '');
}

$user = current_user();
if (!$user) {
    header('Location: ' . root_url('login.php'));
    exit;
}

$errors = [];
$success_id = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request. Please refresh and try again.';
    } else {
        $subject      = trim($_POST['title'] ?? '');
        $severity     = in_array($_POST['severity'] ?? 'medium', ['low','medium','high','urgent']) ? ($_POST['severity'] ?? 'medium') : 'medium';
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;

        // Category: combine selected checkboxes into a comma-separated string
        $categories = isset($_POST['category']) && is_array($_POST['category']) ? array_filter(array_map('trim', $_POST['category'])) : [];
        $categoryStr = implode(', ', $categories);

        // Inline fields collected to embed into description for a printable record
        $incident_date  = trim($_POST['incident_date'] ?? '');
        $incident_time  = trim($_POST['incident_time'] ?? '');
        $incident_place = trim($_POST['incident_place'] ?? '');
        $parties        = trim($_POST['parties'] ?? '');
        $witnesses      = trim($_POST['witnesses'] ?? '');
        $desired_action = trim($_POST['desired_action'] ?? '');
        $consent_share  = isset($_POST['consent_share']) ? 'Yes' : 'No';

        $details        = trim($_POST['details'] ?? '');

        if ($details === '') $errors[] = 'Details/description is required.';

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // Compose description that captures the inline blanks for later viewing/printing
                $composed = (empty($subject) ? '' : ("Subject: {$subject}\n")) .
                            "Incident Date: {$incident_date}\n" .
                            "Incident Time: {$incident_time}\n" .
                            "Location: {$incident_place}\n" .
                            (empty($parties) ? '' : ("Parties Involved: {$parties}\n")) .
                            (empty($witnesses) ? '' : ("Witnesses: {$witnesses}\n")) .
                            (empty($desired_action) ? '' : ("Preferred Resolution: {$desired_action}\n")) .
                            "Consent to share with relevant department: {$consent_share}\n\n" .
                            $details;
                // Generate unique ticket number
                $makeTicket = function() use ($pdo): string {
                    $date = date('Ymd');
                    $rand = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
                    return "CMP-{$date}-{$rand}";
                };
                $ticket = $makeTicket();
                for ($attempt=0; $attempt<5; $attempt++) {
                    $check = $pdo->prepare('SELECT COUNT(*) FROM complaints WHERE ticket_no = :t');
                    $check->execute([':t'=>$ticket]);
                    if ((int)$check->fetchColumn() === 0) break;
                    $ticket = $makeTicket();
                }

                // Anonymous handling: set user_id NULL and generate anonymous_token
                $uidVal = $is_anonymous ? null : (int)$user['id'];
                $anonToken = $is_anonymous ? bin2hex(random_bytes(16)) : null;

                // Insert complaint aligned to schema
                $ins = $pdo->prepare("INSERT INTO complaints (ticket_no, user_id, anonymous_token, category, severity, description, status) VALUES (:ticket, :uid, :anon_token, :category, :severity, :description, 'open')");
                $ins->execute([
                    ':ticket' => $ticket,
                    ':uid' => $uidVal,
                    ':anon_token' => $anonToken,
                    ':category' => $categoryStr,
                    ':severity' => $severity,
                    ':description' => $composed,
                ]);
                $cid = (int)$pdo->lastInsertId();

                // Audit trail
                $meta = json_encode(['category'=>$categoryStr,'severity'=>$severity], JSON_UNESCAPED_SLASHES);
                $ast = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note, meta) VALUES (:cid,:uid,'created',:note,:meta)");
                $ast->execute([':cid'=>$cid, ':uid'=>$uidVal, ':note'=>'Complaint submitted', ':meta'=>$meta]);

                // Handle images (optional)
                define('CMP_UPLOAD_DIR', __DIR__ . '/uploads/complaints');
                if (!is_dir(CMP_UPLOAD_DIR)) mkdir(CMP_UPLOAD_DIR, 0755, true);
                $dir = CMP_UPLOAD_DIR . '/complaint_' . $cid;
                if (!is_dir($dir)) mkdir($dir, 0755, true);

                if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                    $maxFiles = 6;
                    $saved = 0;
                    for ($i=0; $i<count($_FILES['images']['name']); $i++) {
                        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                        if ($saved >= $maxFiles) break;
                        $tmp = $_FILES['images']['tmp_name'][$i];
                        $orig = basename($_FILES['images']['name'][$i]);
                        $size = $_FILES['images']['size'][$i];
                        $info = @getimagesize($tmp);
                        if ($info === false) continue;
                        $mime = $info['mime'];
                        $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                        if (!in_array($mime, $allowed, true)) continue;
                        if ($size > (5*1024*1024)) continue;
                        $ext = image_type_to_extension($info[2], false);
                        $ext = ($ext === 'jpeg') ? 'jpg' : $ext;
                        $filename = uniqid('cimg_', true) . '.' . $ext;
                        $dest = $dir . '/' . $filename;
                        if (move_uploaded_file($tmp, $dest)) {
                            $stmtImg = $pdo->prepare("INSERT INTO complaint_images (complaint_id, filename, original_name, mime, size) VALUES (:cid,:fn,:on,:mime,:size)");
                            $stmtImg->execute([':cid'=>$cid, ':fn'=>$filename, ':on'=>$orig, ':mime'=>$mime, ':size'=>$size]);
                            $saved++;
                        }
                    }
                }

                $pdo->commit();
                header('Location: complaint_view.php?id=' . $cid);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log('complaint_create save error: ' . $e->getMessage());
              $errors[] = 'Failed to submit complaint: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Create Complaint — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
  <style>
    /* Ensure global gradient stays fixed while scrolling */
    html, body { min-height: 100%; margin: 0; padding: 0; }
    body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

    .complaint-wrap { max-width: 1100px; margin: 0 auto; padding: 36px 20px 80px; }
    .card { border-radius: 20px; box-shadow: 0 14px 34px rgba(0,0,0,0.10); padding: 24px 26px; background:#fff; }
    .muted { font-size: 12px; color:#6b7280; }
    .actions { display:flex; gap:10px; margin-top: 16px; }

    /* Table style form */
    .form-table { width:100%; border-collapse: separate; border-spacing: 0; }
    .form-table th, .form-table td { padding: 12px 14px; vertical-align: top; }
    .form-table th { width: 240px; background:#f9fafb; color:#0b1c33; font-weight:700; text-align:left; border-top-left-radius:10px; border-bottom-left-radius:10px; }
    .form-table td { background:#ffffff; border-top-right-radius:10px; border-bottom-right-radius:10px; }
    .form-table tr + tr th, .form-table tr + tr td { border-top: 1px solid #eef1f4; border-radius:0; }
    .form-table input[type="text"], .form-table input[type="date"], .form-table input[type="time"], .form-table select {
      width:100%; padding:10px 12px; border:1px solid #d6d9e0; border-radius:10px; outline:none; background:#fff;
    }
    .form-table textarea { width:100%; border:1px solid #d6d9e0; border-radius:12px; padding:12px; min-height:120px; }
    .inline-grid { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:12px; }
    .checkboxes { display:flex; flex-wrap:wrap; gap:10px; }
    .checkboxes label { display:flex; align-items:center; gap:6px; font-weight:600; background:#f3f4f6; padding:6px 10px; border-radius:10px; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a href="<?= htmlspecialchars(root_url('index.php')) ?>" class="btn subtle">Home</a>
      <a href="complaint_list.php" class="btn subtle">Back</a>
    </nav>
  </header>

  <main class="complaint-wrap">
    <div class="card">
      <h2>Complaint Form</h2>
      <p class="muted">Fill in the blanks and tick relevant boxes. This layout mimics a one-line continuous form for printing.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger"><ul><?php foreach ($errors as $er) echo '<li>'.htmlspecialchars($er).'</li>'; ?></ul></div>
      <?php endif; ?>

      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

        <table class="form-table">
          <tr>
            <th>Subject (optional)</th>
            <td><input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>"></td>
          </tr>
          <tr>
            <th>Severity</th>
            <td>
              <select name="severity">
                <option value="low" <?= (($_POST['severity'] ?? 'medium')==='low')?'selected':'' ?>>Low</option>
                <option value="medium" <?= (($_POST['severity'] ?? 'medium')==='medium')?'selected':'' ?>>Medium</option>
                <option value="high" <?= (($_POST['severity'] ?? '')==='high')?'selected':'' ?>>High</option>
                <option value="urgent" <?= (($_POST['severity'] ?? '')==='urgent')?'selected':'' ?>>Urgent</option>
              </select>
            </td>
          </tr>
          <tr>
            <th>Incident Details</th>
            <td>
              <div class="inline-grid">
                <div><label class="muted">Date</label><input type="date" name="incident_date" value="<?= htmlspecialchars($_POST['incident_date'] ?? '') ?>"></div>
                <div><label class="muted">Time</label><input type="time" name="incident_time" value="<?= htmlspecialchars($_POST['incident_time'] ?? '') ?>"></div>
                <div><label class="muted">Location</label><input type="text" name="incident_place" value="<?= htmlspecialchars($_POST['incident_place'] ?? '') ?>" placeholder="Where did it happen?"></div>
              </div>
            </td>
          </tr>
          <tr>
            <th>Category</th>
            <td>
              <div class="checkboxes">
                <?php $cats = $_POST['category'] ?? []; $chk = function($v,$cats){ return (is_array($cats) && in_array($v,$cats)) ? 'checked' : ''; }; ?>
                <label><input type="checkbox" name="category[]" value="Facilities" <?= $chk('Facilities',$cats) ?>> Facilities</label>
                <label><input type="checkbox" name="category[]" value="Events" <?= $chk('Events',$cats) ?>> Events</label>
                <label><input type="checkbox" name="category[]" value="Academic" <?= $chk('Academic',$cats) ?>> Academic</label>
                <label><input type="checkbox" name="category[]" value="Safety" <?= $chk('Safety',$cats) ?>> Safety</label>
                <label><input type="checkbox" name="category[]" value="Conduct" <?= $chk('Conduct',$cats) ?>> Conduct</label>
                <label><input type="checkbox" name="category[]" value="Other" <?= $chk('Other',$cats) ?>> Other</label>
              </div>
            </td>
          </tr>
          <tr>
            <th>Parties Involved</th>
            <td><input type="text" name="parties" value="<?= htmlspecialchars($_POST['parties'] ?? '') ?>" placeholder="Names or identifiers"></td>
          </tr>
          <tr>
            <th>Witnesses</th>
            <td><input type="text" name="witnesses" value="<?= htmlspecialchars($_POST['witnesses'] ?? '') ?>" placeholder="Witness names (optional)"></td>
          </tr>
          <tr>
            <th>Details</th>
            <td><textarea name="details" rows="6" required><?= htmlspecialchars($_POST['details'] ?? '') ?></textarea></td>
          </tr>
          <tr>
            <th>Preferred Resolution</th>
            <td><input type="text" name="desired_action" value="<?= htmlspecialchars($_POST['desired_action'] ?? '') ?>" placeholder="Action you hope to be taken"></td>
          </tr>
          <tr>
            <th>Attach Images</th>
            <td>
              <input type="file" name="images[]" multiple accept="image/*">
              <div class="muted" style="margin-top:6px;">Optional, up to 6 files, 5MB each</div>
            </td>
          </tr>
          <tr>
            <th>Privacy</th>
            <td>
              <label style="display:block;margin-bottom:8px"><input type="checkbox" name="consent_share" <?= isset($_POST['consent_share']) ? 'checked' : '' ?>> I consent to share details with the relevant department</label>
              <label><input type="checkbox" name="is_anonymous" <?= isset($_POST['is_anonymous']) ? 'checked' : '' ?>> Submit anonymously</label>
            </td>
          </tr>
          <tr>
            <th></th>
            <td>
              <div class="actions">
                <button class="btn primary" type="submit">Submit Complaint</button>
                <a class="btn subtle" href="complaint_list.php">Cancel</a>
              </div>
            </td>
          </tr>
        </table>
      </form>
    </div>
  </main>
</body>
</html>
<?php
// end file
?>

