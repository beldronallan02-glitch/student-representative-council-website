<?php
// modules/feedback/admin_feedback.php
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('Location: /MPPCONNECT/login.php'); exit; }

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;

// Fetch feedbacks with event and user info
try {
  if ($eventId > 0) {
    $stmt = $pdo->prepare(
      "SELECT 
         f.id, f.event_id, f.user_id, f.rating, f.comment, f.anonymous, f.created_at,
         e.title AS event_title, e.location, e.start_at, e.end_at, e.description, e.excerpt,
         u.name AS user_name
       FROM feedbacks f
       LEFT JOIN events e ON e.id = f.event_id
       LEFT JOIN users u ON u.id = f.user_id
       WHERE f.deleted_at IS NULL AND f.event_id = :eid
       ORDER BY f.created_at DESC LIMIT 500"
    );
    $stmt->execute([':eid' => $eventId]);
  } else {
    $stmt = $pdo->prepare(
      "SELECT 
         f.id, f.event_id, f.user_id, f.rating, f.comment, f.anonymous, f.created_at,
         e.title AS event_title, e.location, e.start_at, e.end_at, e.description, e.excerpt,
         u.name AS user_name
       FROM feedbacks f
       LEFT JOIN events e ON e.id = f.event_id
       LEFT JOIN users u ON u.id = f.user_id
       WHERE f.deleted_at IS NULL
       ORDER BY f.created_at DESC LIMIT 500"
    );
    $stmt->execute();
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { error_log('admin_feedback error: '.$e->getMessage()); $rows = []; }

// Fetch images for all feedbacks in one go
$imagesByFeedback = [];
if (!empty($rows)) {
  try {
    $ids = array_column($rows, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $q = $pdo->prepare("SELECT id, feedback_id, filename FROM feedback_images WHERE feedback_id IN ($placeholders) ORDER BY id");
    $q->execute($ids);
    while ($img = $q->fetch(PDO::FETCH_ASSOC)) {
      $fid = (int)$img['feedback_id'];
      if (!isset($imagesByFeedback[$fid])) $imagesByFeedback[$fid] = [];
      $imagesByFeedback[$fid][] = $img;
    }
  } catch (Exception $e) { error_log('admin_feedback images error: '.$e->getMessage()); }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <link rel="stylesheet" href="/MPPCONNECT/css/style.css">
  <title>Manage Feedback</title>
  <style>
    .table-card { background:#fff; border:1px solid #e5e7eb; border-radius:16px; box-shadow:0 12px 28px rgba(0,0,0,0.08); overflow:hidden; }
    .table { width:100%; border-collapse:collapse; }
    .table th, .table td { padding:12px 14px; border-top:1px solid #eef1f4; vertical-align:top; }
    .table th { background:#f9fafb; text-align:left; font-weight:700; font-size:13px; color:#374151; }
    .table-row:hover { background:#f8fafc; }
    .chip { display:inline-block; background:#eef2ff; color:#1e3a8a; border:1px solid #c7d2fe; padding:4px 8px; border-radius:999px; font-size:12px; }
    .thumbs { display:flex; gap:8px; flex-wrap:wrap; }
    .thumbs a img { width:64px; height:64px; object-fit:cover; border-radius:8px; border:1px solid #e5e7eb; }
    .meta { font-size:12px; color:#6b7280; }
  </style>
</head>
<body>
<header class="topbar">
  <nav class="nav">
    <a href="/MPPCONNECT/index.php" class="btn subtle">Home</a>
    <a href="feedback_list.php" class="btn subtle">Back</a>
    <a href="admin_feedback_export.php" class="btn subtle">Export CSV</a>
  </nav>
  <?php if ($eventId > 0): ?>
    <div style="padding:8px 16px;" class="meta">Filtered by event ID: <?= (int)$eventId ?></div>
  <?php endif; ?>
</header>

<main class="container" style="padding:24px;">
  <div class="table-card">
    <table class="table">
      <thead>
        <tr>
          <th>Event</th>
          <th>Dates · Location</th>
          <th>Student</th>
          <th>Rating</th>
          <th>Comment</th>
          <th>Images</th>
          <th>Submitted</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rows)): ?>
          <tr><td colspan="8" style="padding:18px;" class="meta">No feedback yet.</td></tr>
        <?php else: foreach ($rows as $r): 
          $start = (!empty($r['start_at']) && $r['start_at'] !== '0000-00-00 00:00:00') ? strtotime($r['start_at']) : null;
          $end   = (!empty($r['end_at']) && $r['end_at'] !== '0000-00-00 00:00:00') ? strtotime($r['end_at']) : null;
          $dates = '';
          if ($start) { $dates = date('M j, Y', $start); }
          if ($end) { $dates .= ($dates ? ' — ' : '') . date('M j, Y', $end); }
          $loc = !empty($r['location']) ? ' · '.htmlspecialchars($r['location']) : '';
          $starsFull = (int)floor((float)($r['rating'] ?? 0));
          $stars = str_repeat('★', $starsFull) . str_repeat('☆', max(0, 5 - $starsFull));
          $imgs = $imagesByFeedback[$r['id']] ?? [];
        ?>
        <tr class="table-row">
          <td>
            <div style="font-weight:700;"><?= htmlspecialchars($r['event_title'] ?? 'Event') ?></div>
            <?php if (!empty($r['description']) || !empty($r['excerpt'])): ?>
              <div class="meta" style="margin-top:4px;">
                <?= htmlspecialchars(mb_strimwidth($r['description'] ?: $r['excerpt'], 0, 120, '...')) ?>
              </div>
            <?php endif; ?>
          </td>
          <td>
            <div><?= htmlspecialchars($dates) ?><?= $loc ?></div>
          </td>
          <td><?= ($r['anonymous'] ? 'Anonymous' : htmlspecialchars($r['user_name'] ?? 'Student')) ?></td>
          <td><strong><?= $stars ?></strong> · <?= (int)$r['rating'] ?>/5</td>
          <td><?= htmlspecialchars($r['comment'] ?? '') ?></td>
          <td>
            <?php if (!empty($imgs)): ?>
              <div class="thumbs">
                <?php foreach ($imgs as $im): ?>
                  <a href="serve_image_feedback.php?img=<?= (int)$im['id'] ?>" target="_blank" rel="noopener">
                    <img src="serve_image_feedback.php?img=<?= (int)$im['id'] ?>&thumb=1" alt="Feedback image">
                  </a>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <span class="meta">No images</span>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars(date('M j, Y · g:i A', strtotime($r['created_at']))) ?></td>
          <td>
            <a class="btn subtle" href="admin_feedback_view.php?id=<?= (int)$r['id'] ?>">Open</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</main>
</body>
</html>
