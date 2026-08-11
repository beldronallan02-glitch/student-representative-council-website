<?php
// modules/events/admin_events.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /login.php'); exit;
}

$err = null;

// Helper: event image web path
function event_image_webpath(array $e): string {
    $fn = trim($e['image'] ?? '');
    if ($fn !== '') {
        $fs = __DIR__ . '/../../uploads/events/' . $fn;
        if (is_file($fs)) {
            return '../../uploads/events/' . $fn;
        }
    }
    return '../../uploads/progress/placeholder.png';
}

$search = trim($_GET['q'] ?? '');
$archived = (($_GET['archived'] ?? '') === '1');
try {
  if ($search !== '') {
    $stmt = $pdo->prepare("SELECT e.*, u.name AS author_name, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status='registered') AS attendees FROM events e LEFT JOIN users u ON e.author_id=u.id WHERE e.title LIKE ? OR e.excerpt LIKE ? ORDER BY e.start_at DESC");
    $stmt->execute(['%'.$search.'%','%'.$search.'%']);
  } else {
    $stmt = $pdo->query("SELECT e.*, u.name AS author_name, (SELECT COUNT(*) FROM event_registrations r WHERE r.event_id=e.id AND r.status='registered') AS attendees FROM events e LEFT JOIN users u ON e.author_id=u.id ORDER BY e.start_at DESC");
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  // Filter rows based on archived toggle
  if ($archived) {
    $rows = array_values(array_filter($rows, function($r){ return ($r['status'] ?? '') === 'archived'; }));
  } else {
    $rows = array_values(array_filter($rows, function($r){ return ($r['status'] ?? '') !== 'archived'; }));
  }
} catch (Exception $e) {
    $rows = [];
    $dbErr = $e->getMessage();
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Manage Events — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
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
  .pill.published { background:#dcfce7; color:#166534; }
  .pill.draft { background:#f8fafc; color:#374151; }
  .pill.cancelled { background:#fee2e2; color:#b91c1c; }
  .actions { display:flex; gap:8px; align-items:center; margin-top:auto; padding:12px 14px; border-top:1px solid #eef1f4; }
  .error { background:#ffe6e6; border-left:4px solid #ff6b6b; padding:10px 12px; border-radius:10px; margin-top:10px; color:#b00020; }
  </style>
</head>
<body>
<header class="topbar">
  <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
    
    
  </nav>
</header>

<main class="container page">
  <div class="card">
    <div class="toolbar">
      <h2 style="margin:0"><?= $archived ? 'Archived Events' : 'Events' ?></h2>
      <div style="display:flex;gap:8px;align-items:center">
        <?php if ($archived): ?>
          <a class="btn subtle" href="admin_events.php">Back to Active</a>
        <?php else: ?>
          <a class="btn subtle" href="admin_events.php?archived=1">View Archived</a>
        <?php endif; ?>
        <a class="btn primary" href="event_create.php">+ Create Event</a>
      </div>
    </div>

    <?php if (!empty($dbErr)): ?><div class="error"><?= htmlspecialchars($dbErr) ?></div><?php endif; ?>

    <div style="margin-top:12px">
      <form method="get" style="display:flex;gap:8px">
        <input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Search title or excerpt" style="padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,0.06);width:320px">
        <button class="btn subtle" type="submit">Search</button>
      </form>
    </div>

    <?php if (empty($rows)): ?>
      <p class="micro" style="margin-top:12px">No events found.</p>
    <?php else: ?>
      <div class="grid">
        <?php foreach($rows as $r):
          $img = event_image_webpath($r);
          $status = strtolower($r['status'] ?? 'draft');
          $pillClass = in_array($status, ['published','draft','cancelled'], true) ? $status : 'draft';
          $att = (int)($r['attendees'] ?? 0);
          $when = '';
          try {
            $sd = new DateTime($r['start_at']);
            $when = $sd->format('j F Y, g:i A');
            if (!empty($r['end_at']) && $r['end_at'] !== '0000-00-00 00:00:00') {
              $ed = new DateTime($r['end_at']);
              $when .= ' — ' . $ed->format('j F Y, g:i A');
            }
          } catch (Exception $e) {
            $when = htmlspecialchars($r['start_at'] ?? '');
            if (!empty($r['end_at'])) $when .= ' — ' . htmlspecialchars($r['end_at']);
          }
        ?>
          <article class="cardx">
            <img class="thumb" src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($r['title']) ?>">
            <div class="meta">
              <div class="title"><?= htmlspecialchars($r['title']) ?></div>
              <div class="info"><?= htmlspecialchars($r['location'] ?? '') ?> · <?= $att ?> registered</div>
              <div class="desc"><?= htmlspecialchars(mb_strimwidth(trim($r['excerpt'] ?? ''), 0, 140, '…')) ?></div>
              <div class="micro" style="margin-top:6px"><?= $when ?></div>
              <div style="margin-top:8px"><span class="pill <?= $pillClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span></div>
            </div>
            <div class="actions">
              <?php if ($archived): ?>
                <form method="post" action="event_action.php" onsubmit="return confirm('Unarchive this event?');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="unarchive">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button class="btn subtle" type="submit">Unarchive</button>
                </form>
                <form method="post" action="event_action.php" onsubmit="return confirm('Delete permanently? This cannot be undone.');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button class="btn ghost" type="submit">Delete</button>
                </form>
              <?php else: ?>
                <a class="btn subtle" href="event_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
                <a class="btn subtle" href="registrations_manage.php?event_id=<?= (int)$r['id'] ?>">Registrations</a>
                <form method="post" action="event_action.php" onsubmit="return confirm('Archive this event?');">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="action" value="archive">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                  <button class="btn ghost" type="submit">Archive</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
