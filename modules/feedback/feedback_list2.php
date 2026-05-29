<?php
// modules/feedback/feedback_list2.php

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

/* resolve web root */
$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
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

// Require login
$user = current_user();
if (!$user) { header('Location: ' . root_url('login.php')); exit; }

// Fetch events the user has registered for (by account or email)
$events = [];
try {
    $stmt = $pdo->prepare(
        "SELECT DISTINCT e.id, e.title, e.start_at, e.end_at, e.location, e.image
         FROM event_registrations r
         JOIN events e ON e.id = r.event_id
         WHERE (
           r.user_id = :uid
           OR (
             r.user_id IS NULL
             AND r.participant_email IS NOT NULL AND r.participant_email <> ''
             AND TRIM(LOWER(r.participant_email)) = TRIM(LOWER(:email))
           )
         )
         AND (r.status IS NULL OR LOWER(r.status) IN ('registered','confirmed'))
         ORDER BY e.start_at DESC"
    );
    $stmt->execute([
        ':uid' => (int)$user['id'],
        ':email' => ($user['email'] ?? '')
    ]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $events = [];
}

// Map of event_id => my feedback id (latest), for the logged-in user
$myFeedback = [];
try {
  if (!empty($events)) {
    $eventIds = array_values(array_map(fn($r) => (int)$r['id'], $events));
    if (!empty($eventIds)) {
      $ph = [];
      $params = [ ':uid' => (int)$user['id'] ];
      for ($i = 0; $i < count($eventIds); $i++) { $ph[] = ":e{$i}"; $params[":e{$i}"] = $eventIds[$i]; }
      $sql = "SELECT id, event_id FROM feedbacks WHERE deleted_at IS NULL AND user_id = :uid AND event_id IN (" . implode(',', $ph) . ") ORDER BY created_at DESC, id DESC";
      $stf = $pdo->prepare($sql);
      $stf->execute($params);
      while ($row = $stf->fetch(PDO::FETCH_ASSOC)) {
        $eid = (int)$row['event_id'];
        if (!isset($myFeedback[$eid])) { $myFeedback[$eid] = (int)$row['id']; }
      }
    }
  }
} catch (Throwable $e) {
  // ignore mapping errors; page should still render
}

function fmt_dt($dt) {
  if (!$dt || $dt === '0000-00-00 00:00:00') return '';
  $ts = strtotime($dt);
  return $ts ? date('M d, Y H:i', $ts) : '';
}

?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Event Feedback — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

.page-wrap { max-width:980px; margin:0 auto; padding:32px 16px 80px; }
.grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:16px; }
.card { border:1px solid #e5e7eb; border-radius:16px; padding:14px; background:#fff; }
.card .title { font-size:16px; font-weight:600; margin:4px 0 8px; }
.meta { font-size:12px; color:#6b7280; }
.badge { display:inline-block; font-size:11px; padding:4px 8px; border-radius:999px; background:#ecfdf5; color:#065f46; border:1px solid #a7f3d0; margin-top:6px; }
.actions { display:flex; gap:8px; margin-top:12px; }
.headerpad { padding: 8px 16px; }
.imgcov { width:100%; aspect-ratio:16/9; object-fit:cover; border-radius:12px; background:#f3f4f6; }
.empty { background:#f9fafb; border:1px solid #e5e7eb; padding:18px; border-radius:14px; color:#374151; }
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
    <a href="<?= root_url('index.php') ?>" class="btn subtle">Home</a>
  </nav>
</header>

<main class="page-wrap">
  <div class="headerpad">
    <h2>Events You Registered</h2>
    <div class="meta">Submit feedback for events you've joined. Feedback opens after an event ends.</div>
  </div>

  <?php if (empty($events)): ?>
    <div class="empty">
      <h3>No registrations found</h3>
      <p>We couldn't find any events linked to your account or email. Try registering for an event first.</p>
    </div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($events as $ev): ?>
        <div class="card">
          <?php if (!empty($ev['image'])): ?>
            <img class="imgcov" src="<?= htmlspecialchars(root_url('uploads/events/' . $ev['image'])) ?>" alt="Poster">
          <?php endif; ?>

          <div class="title"><?= htmlspecialchars($ev['title']) ?></div>
          <div class="meta">
            <?php $start = fmt_dt($ev['start_at']); $end = fmt_dt($ev['end_at']); ?>
            <?= htmlspecialchars($start) ?><?= ($end !== '' ? ' — ' . htmlspecialchars($end) : '') ?><br>
            <?= htmlspecialchars($ev['location'] ?? '') ?>
          </div>

          <?php $fid = $myFeedback[(int)$ev['id']] ?? null; ?>
          <?php if ($fid): ?>
            <div class="badge">Already submitted</div>
            <div class="actions">
              <a class="btn subtle" href="feedback_view.php?id=<?= (int)$fid ?>">View Feedback</a>
            </div>
          <?php else: ?>
            <div class="actions">
              <a class="btn primary" href="feedback_create.php?event=<?= (int)$ev['id'] ?>">Give Feedback</a>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

</body>
</html>
