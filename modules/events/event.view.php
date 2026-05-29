<?php
// modules/events/event.view.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: events_public.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT 
        e.*,
        u.name AS author_name,
        (SELECT COUNT(*) 
         FROM event_registrations r 
         WHERE r.event_id = e.id AND r.status = 'registered') AS attendees
    FROM events e
    LEFT JOIN users u ON e.author_id = u.id
    WHERE e.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    header('Location: events_public.php');
    exit;
}

$user = current_user();
$regSuccess = (isset($_GET['registered']) && $_GET['registered'] === '1');

// Check if the current user has already registered for this event
$isRegistered = false;
if ($user) {
  try {
    $chk = $pdo->prepare(
      "SELECT 1 FROM event_registrations 
       WHERE event_id = ? 
         AND (
           user_id = ?
           OR (
             participant_email IS NOT NULL AND participant_email <> ''
             AND TRIM(LOWER(participant_email)) = TRIM(LOWER(?))
           )
         )
         AND (status IS NULL OR LOWER(status) IN ('registered','confirmed'))
       LIMIT 1"
    );
    $chk->execute([$event['id'], $user['id'], $user['email'] ?? '']);
    $isRegistered = (bool)$chk->fetchColumn();
  } catch (Throwable $e) {
    $isRegistered = false;
  }
}

// Determine if event has ended using end_at from events
$hasEnded = false;
if (!empty($event['end_at']) && $event['end_at'] !== '0000-00-00 00:00:00') {
  $hasEnded = (time() >= strtotime($event['end_at']));
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($event['title']) ?> — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="../../css/style.css">

<style>
/* Media frame with enforced 16:9 aspect ratio */
.media-frame {
  position: relative;
  width: 100%;
  max-width: 960px;
  margin: 0 auto 18px auto;
  border-radius: 16px;
  overflow: hidden;
  background: #e5e7eb;
}
.media-frame::before {
  content: '';
  display: block;
  padding-top: 56.25%; /* 16:9 fallback for browsers without aspect-ratio */
}
.media-frame > img {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* Lightbox overlay */
.lightbox-overlay {
  position: fixed;
  inset: 0;
  background: rgba(0,0,0,0.75);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.lightbox-overlay.open { display: flex; }
.lightbox-content {
  max-width: 90vw;
  max-height: 90vh;
}
.lightbox-content img {
  width: 100%;
  height: auto;
  border-radius: 12px;
}
.lightbox-close {
  position: fixed;
  top: 20px;
  right: 20px;
  background: #ffffff;
  color: #111827;
  border: none;
  padding: 10px 14px;
  border-radius: 10px;
  cursor: pointer;
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
    <a class="btn subtle" href="events_public.php">Back</a>
    <a class="btn subtle" href="../../index.php">Home</a>
  </nav>
</header>

<main class="container">
  <div class="card">

    <?php if ($regSuccess): ?>
      <div style="background:#e6fff0;border-left:4px solid #37b24d;padding:12px;border-radius:10px;margin-bottom:16px;color:#135f1a;">
        You’re registered for this event. See you there!
      </div>
    <?php endif; ?>

    <?php if (!empty($event['image'])): ?>
      <div class="media-frame">
        <img
          src="../../uploads/events/<?= htmlspecialchars($event['image']) ?>"
          alt="<?= htmlspecialchars($event['title']) ?>"
          id="eventPoster"
          style="cursor: zoom-in;"
        >
      </div>
    <?php endif; ?>

    <h1><?= htmlspecialchars($event['title']) ?></h1>

    <div class="small" style="margin-bottom:10px">
      <?= htmlspecialchars($event['author_name'] ?? 'MPP') ?>
      · <?= date('M j, Y · g:i A', strtotime($event['start_at'])) ?>
      <?php if (!empty($event['end_at'])): ?>
        — <?= date('g:i A', strtotime($event['end_at'])) ?>
      <?php endif; ?>
      <?php if (!empty($event['location'])): ?>
        · <?= htmlspecialchars($event['location']) ?>
      <?php endif; ?>
    </div>

    <?php if (!empty($event['excerpt'])): ?>
      <p class="lead"><?= htmlspecialchars($event['excerpt']) ?></p>
    <?php endif; ?>

    <div>
      <?= nl2br(htmlspecialchars($event['description'])) ?>
    </div>

    <div style="margin-top:18px">
      <?php if ($event['status'] !== 'published'): ?>
        <div class="micro">This event is not published.</div>
      <?php else: ?>
        <?php if ($isRegistered): ?>
          <button class="btn primary" disabled>Registered</button>
        <?php else: ?>
          <a class="btn primary" href="event_register.php?id=<?= (int)$event['id'] ?>">
            Register for this event
          </a>
        <?php endif; ?>

        <?php if ($user && $isRegistered): ?>
          <?php if ($hasEnded): ?>
            <a class="btn subtle" href="../feedback/feedback_create.php?event=<?= (int)$event['id'] ?>">Give Feedback</a>
          <?php else: ?>
            <button class="btn subtle" disabled title="Available after event ends">Give Feedback</button>
          <?php endif; ?>
        <?php endif; ?>

        <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
          <a class="btn subtle" href="registrations_manage.php?event_id=<?= (int)$event['id'] ?>">
            View registrations (<?= (int)$event['attendees'] ?>)
          </a>
          <a class="btn subtle" href="event_edit.php?id=<?= (int)$event['id'] ?>">
            Edit event
          </a>
        <?php endif; ?>
      <?php endif; ?>
    </div>

  </div>
</main>

<!-- Lightbox Modal -->
<div class="lightbox-overlay" id="lightbox">
  <button class="lightbox-close" id="lightboxClose" aria-label="Close image">Close</button>
  <div class="lightbox-content">
    <img src="" alt="Expanded event image" id="lightboxImg">
  </div>
  <!-- Click backdrop to close -->
</div>

<script>
(function(){
  var poster = document.getElementById('eventPoster');
  var overlay = document.getElementById('lightbox');
  var img = document.getElementById('lightboxImg');
  var closeBtn = document.getElementById('lightboxClose');

  function openLightbox(src) {
    img.src = src;
    overlay.classList.add('open');
  }
  function closeLightbox() {
    overlay.classList.remove('open');
    img.src = '';
  }

  if (poster) {
    poster.addEventListener('click', function(){ openLightbox(poster.src); });
  }
  closeBtn.addEventListener('click', closeLightbox);
  overlay.addEventListener('click', function(e){ if (e.target === overlay) closeLightbox(); });
  document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLightbox(); });
})();
</script>

</body>
</html>
