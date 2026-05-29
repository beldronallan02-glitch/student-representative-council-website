<?php
// modules/feedback/feedback_list.php

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';

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

$is_manager = in_array($user['role'] ?? '', ['mpp','admin'], true);

$open_prompts = get_open_prompts_for_user($pdo, $user['id']);
$my_feedbacks = get_feedbacks_for_user($pdo, $user['id']);

/* ===============================
   EVENTS JOINED BY CURRENT USER
=============================== */
$joined_events = [];
try {
  $stmt = $pdo->prepare(
    "SELECT DISTINCT
      e.id,
      e.title,
      e.location,
      e.start_at,
      e.end_at,
      e.image,
      e.description,
      e.excerpt,
      (SELECT COUNT(*) FROM feedbacks f WHERE f.event_id = e.id AND f.user_id = :uid AND f.deleted_at IS NULL) AS has_feedback,
      (SELECT id FROM feedbacks f2 WHERE f2.event_id = e.id AND f2.user_id = :uid AND f2.deleted_at IS NULL ORDER BY f2.created_at DESC LIMIT 1) AS my_feedback_id
     FROM event_registrations r
     JOIN events e ON e.id = r.event_id
     WHERE (
        r.user_id = :uid
        OR (
          r.participant_email IS NOT NULL AND r.participant_email <> ''
          AND TRIM(LOWER(r.participant_email)) = TRIM(LOWER(:email))
        )
      )
      AND (r.status IS NULL OR LOWER(r.status) IN ('registered','checked_in','waitlist'))
     ORDER BY COALESCE(e.end_at, e.start_at) DESC"
  );
  $stmt->execute([':uid' => $user['id'], ':email' => ($user['email'] ?? '')]);
  $joined_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('[feedback_list] joined events error: ' . $e->getMessage());
  $joined_events = [];
}

/* Fallback: also include registrations from JSON if present */
try {
  $regFile = $PROJECT_ROOT . '/modules/events/registrations.json';
  if (is_readable($regFile)) {
    $raw = file_get_contents($regFile);
    $regs = json_decode($raw, true);
    if (is_array($regs)) {
      $emailKey = strtolower(trim($user['email'] ?? ''));
      $existingIds = array_map(function($e){ return (int)($e['id'] ?? 0); }, $joined_events);
      $wantedIds = [];
      foreach ($regs as $row) {
        $eid = (int)($row['event_id'] ?? 0);
        $eml = strtolower(trim($row['email'] ?? $row['participant_email'] ?? ''));
        if ($eid > 0 && $emailKey !== '' && $eml === $emailKey) {
          $wantedIds[$eid] = true;
        }
      }
      foreach (array_keys($wantedIds) as $eid) {
        if (!in_array($eid, $existingIds, true)) {
          $stEv = $pdo->prepare("SELECT id, title, location, start_at, end_at, image, description, excerpt FROM events WHERE id = ?");
          $stEv->execute([$eid]);
          $evRow = $stEv->fetch(PDO::FETCH_ASSOC);
          if ($evRow) {
            $stHF = $pdo->prepare("SELECT COUNT(*) FROM feedbacks WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL");
            $stHF->execute([$eid, $user['id']]);
            $evRow['has_feedback'] = (int)$stHF->fetchColumn();
            $stId = $pdo->prepare("SELECT id FROM feedbacks WHERE event_id = ? AND user_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 1");
            $stId->execute([$eid, $user['id']]);
            $evRow['my_feedback_id'] = (int)($stId->fetchColumn() ?: 0);
            $joined_events[] = $evRow;
          }
        }
      }
    }
  }
} catch (Throwable $e) {
  error_log('[feedback_list] json registrations merge error: ' . $e->getMessage());
}

// Ensure consistent ordering (most recent end/start first)
if (!empty($joined_events)) {
  usort($joined_events, function($a, $b) {
    $ta = null; $tb = null;
    if (!empty($a['end_at']) && $a['end_at'] !== '0000-00-00 00:00:00') { $ta = strtotime($a['end_at']); }
    if (!$ta && !empty($a['start_at'])) { $ta = strtotime($a['start_at']); }
    if (!empty($b['end_at']) && $b['end_at'] !== '0000-00-00 00:00:00') { $tb = strtotime($b['end_at']); }
    if (!$tb && !empty($b['start_at'])) { $tb = strtotime($b['start_at']); }
    $ta = $ta ?: 0; $tb = $tb ?: 0;
    return $tb <=> $ta;
  });
}

/* ===============================
   ADMIN: ALL EVENTS WITH FEEDBACK SUMMARY
=============================== */
$events_with_stats = [];
if (in_array($user['role'] ?? '', ['mpp','admin'], true)) {
  try {
    $stmt = $pdo->prepare(
      "SELECT 
         e.id,
         e.title,
         e.location,
         e.start_at,
         e.end_at,
         COALESCE(AVG(f.rating), 0) AS avg_rating,
         COUNT(f.id) AS feedback_count
       FROM events e
       LEFT JOIN feedbacks f ON f.event_id = e.id AND f.deleted_at IS NULL
       GROUP BY e.id, e.title, e.location, e.start_at, e.end_at
       ORDER BY COALESCE(e.end_at, e.start_at) DESC"
    );
    $stmt->execute();
    $events_with_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    error_log('[feedback_list] events_with_stats error: ' . $e->getMessage());
    $events_with_stats = [];
  }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Feedback — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">

<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }
 
.feedback-page {
  max-width: 1100px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.section {
  background: #ffffff;
  border-radius: 20px;
  padding: 26px 28px;
  box-shadow: 0 14px 34px rgba(0,0,0,0.10);
  margin-bottom: 26px;
}

.section-header {
  margin-bottom: 18px;
}

.section-title {
  font-size: 20px;
  font-weight: 800;
}

.section-subtitle {
  font-size: 14px;
  color: #6b7280;
}

.list {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* Cards grid for events */
.cards {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.event-card {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  background: #fff;
  box-shadow: 0 10px 26px rgba(0,0,0,0.06);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.event-media {
  width: 100%;
  height: 160px;
  object-fit: cover;
  display: block;
}

.event-body {
  padding: 14px 16px 12px;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.row-between { display:flex; align-items:center; justify-content:space-between; gap:10px; }
.meta { font-size: 13px; color:#6b7280; }
.stats { font-size: 14px; color:#374151; }
.feedback-list { padding: 12px 16px 14px; border-top:1px solid #eef1f4; }
.feedback-item { padding-top:10px; margin-top:10px; border-top:1px solid #eef1f4; }
.pill-soft { display:inline-block; padding:2px 8px; border-radius:999px; background:#f3f4f6; color:#374151; font-size:12px; }

.item {
  border: 1px solid #e5e7eb;
  border-radius: 14px;
  padding: 16px 18px;
  display: flex;
  justify-content: space-between;
  gap: 16px;
  transition: background .2s ease, border .2s ease;
  position: relative;
}

.item:hover {
  background: #f9fafb;
  border-color: #c7d2fe;
}

.item-title {
  font-weight: 700;
  margin-bottom: 4px;
}

.item-meta {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 6px;
}

.item-text {
  font-size: 14px;
  color: #374151;
}

.item-action {
  display: flex;
  align-items: center;
}

.item-action a {
  padding: 10px 16px;
  border-radius: 10px;
  background: #6c7cff;
  color: #fff;
  font-weight: 600;
  font-size: 14px;
  text-decoration: none;
  white-space: nowrap;
}

.item-action a.secondary {
  background: #f3f4f6;
  color: #111827;
}

.empty {
  padding: 18px;
  border-radius: 14px;
  background: #f9fafb;
  color: #6b7280;
  font-size: 14px;
}

/* Different blue shades for event cards */
.card-blue-1 { background: #f0f9ff; border-color: #cfe8ff; }
.card-blue-2 { background: #e0f2fe; border-color: #bfe3fd; }
.card-blue-3 { background: #dbeafe; border-color: #b7d4fb; }
.card-blue-4 { background: #e0e7ff; border-color: #c7d2fe; }
.card-blue-5 { background: #eef2ff; border-color: #d9e0ff; }
.card-blue-6 { background: #f5f9ff; border-color: #dbeafe; }

/* Big rating badge at top edge */
.rating-badge {
  position: absolute;
  top: -12px;
  right: 16px;
  background: #1e3a8a;
  color: #ffffff;
  padding: 6px 10px;
  border-radius: 10px;
  box-shadow: 0 6px 16px rgba(0,0,0,0.12);
  font-weight: 800;
}

@media (max-width: 640px) {
  .item {
    flex-direction: column;
  }
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
    <a href="<?= root_url('index.php') ?>" class="btn subtle">Home</a>
    <?php if (in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
      <a href="admin_feedback.php" class="btn subtle">Manage</a>
    <?php endif; ?>
  </nav>
</header>

<main class="feedback-page">

  <?php if (in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
  <!-- ADMIN: EVENTS WITH FEEDBACK -->
  <section class="section">
    <div class="section-header">
      <div class="section-title">Event Feedback & Ratings</div>
      <div class="section-subtitle">All events with average rating and recent comments</div>
    </div>

    <?php if (empty($events_with_stats)): ?>
      <div class="empty">No events or feedback available yet.</div>
    <?php else: ?>
      <div class="list">
        <?php $i = 0; foreach ($events_with_stats as $ev): 
          $i++;
          $shadeClass = 'card-blue-' . ((($i - 1) % 6) + 1);
          $avg = round((float)($ev['avg_rating'] ?? 0), 1);
          $count = (int)($ev['feedback_count'] ?? 0);
          $starsFull = (int)floor($avg);
          $stars = str_repeat('★', $starsFull) . str_repeat('☆', max(0, 5 - $starsFull));
          $start = !empty($ev['start_at']) ? strtotime($ev['start_at']) : null;
          $end   = (!empty($ev['end_at']) && $ev['end_at'] !== '0000-00-00 00:00:00') ? strtotime($ev['end_at']) : null;
          $imgSrc = '';
          if (!empty($ev['image'])) { $imgSrc = root_url('uploads/events/' . ltrim($ev['image'],'/')); }
        ?>
          <div class="item <?= htmlspecialchars($shadeClass) ?>" style="flex-direction: column;">
            <div class="rating-badge"><?= number_format($avg, 1) ?>/5</div>
            <div style="display:flex; gap:16px; align-items:flex-start;">
              <?php if ($imgSrc): ?>
                <img src="<?= htmlspecialchars($imgSrc) ?>" alt="Poster" style="width:140px; height:100px; object-fit:cover; border-radius:10px; border:1px solid #e5e7eb; flex-shrink:0;"/>
              <?php endif; ?>
              <div style="flex:1;">
                <div class="item-title"><?= htmlspecialchars($ev['title']) ?></div>
                <div class="item-meta">
                  <?php if ($start): ?>
                    <?= date('M j, Y · g:i A', $start) ?>
                  <?php endif; ?>
                  <?php if ($end): ?>
                    — <?= date('M j, Y · g:i A', $end) ?>
                  <?php endif; ?>
                  <?php if (!empty($ev['location'])): ?>
                    · <?= htmlspecialchars($ev['location']) ?>
                  <?php endif; ?>
                </div>
                <?php if (!empty($ev['description'])): ?>
                  <div class="item-text" style="margin-top:6px;"><?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 220, '...')) ?></div>
                <?php elseif (!empty($ev['excerpt'])): ?>
                  <div class="item-text" style="margin-top:6px;"><?= htmlspecialchars(mb_strimwidth($ev['excerpt'], 0, 220, '...')) ?></div>
                <?php endif; ?>
                <div class="item-text" style="margin-top:8px;">
                  <strong><?= $stars ?></strong> · Avg <?= number_format($avg, 1) ?>/5
                  <?php if ($count > 0): ?>
                    · <?= $count ?> feedback<?= $count === 1 ? '' : 's' ?>
                  <?php else: ?>
                    · No feedback yet
                  <?php endif; ?>
                </div>
              </div>
              <div class="item-action" style="align-self:flex-start;">
                <a class="secondary" href="admin_feedback.php?event_id=<?= (int)$ev['id'] ?>">View More</a>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>
  <!-- FEEDBACK HISTORY (students only submit, managers hidden) -->
  <?php if (!$is_manager): ?>
  <section class="section">
    <div class="section-header">
      <div class="section-title">My Feedback History</div>
      <div class="section-subtitle">Previously submitted feedback</div>
    </div>

    <?php if (empty($my_feedbacks)): ?>
      <div class="empty">You haven’t submitted any feedback yet.</div>
    <?php else: ?>
      <div class="list">
        <?php foreach ($my_feedbacks as $f): ?>
          <div class="item">
            <div>
              <div class="item-title">
                <?= htmlspecialchars($f['event_title'] ?? $f['prompt_title'] ?? 'Feedback') ?>
              </div>
              <div class="item-meta">
                <?= date('M j, Y', strtotime($f['created_at'])) ?> · Rating <?= (int)$f['rating'] ?>/5
              </div>
              <div class="item-text">
                <?= htmlspecialchars(mb_strimwidth($f['comment'] ?? '', 0, 160, '...')) ?>
              </div>
            </div>
            <div class="item-action">
              <a class="secondary" href="feedback_view.php?id=<?= (int)$f['id'] ?>">View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
  <?php endif; ?>

</main>

</body>
</html>
