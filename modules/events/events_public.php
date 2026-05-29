<?php
// modules/events/events_public.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

try {
    $stmt = $pdo->prepare("
        SELECT 
            e.id,
            e.title,
            e.excerpt,
            e.location,
            e.start_at,
            e.end_at,
            e.image,
            u.name AS author
        FROM events e
        LEFT JOIN users u ON e.author_id = u.id
        WHERE e.status = 'published'
        ORDER BY e.start_at DESC
    ");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $rows = [];
}

$user = current_user();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Events — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="../../css/style.css">

<style>
/* =====================================================
   GLOBAL
===================================================== */
body {
  background: linear-gradient(180deg, #eef3fb, #9bbcf0);
  background-attachment: fixed;
  background-repeat: no-repeat;
  background-size: cover;
}

/* =====================================================
   PAGE WRAPPER
===================================================== */
.events-page {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.events-title {
  font-size: 26px;
  font-weight: 800;
  margin-bottom: 6px;
}

.events-subtitle {
  color: #4b5563;
  margin-bottom: 28px;
}

/* =====================================================
   EVENTS GRID
===================================================== */
.events-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 26px;
}

/* =====================================================
   EVENT CARD
===================================================== */
.event-card {
  background: #ffffff;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 14px 34px rgba(0,0,0,0.12);
  transition: transform .25s ease, box-shadow .25s ease;
  display: flex;
  flex-direction: column;
}

.event-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 20px 42px rgba(0,0,0,0.18);
}

/* IMAGE */
.event-image {
  width: 100%;
  height: 200px;
  object-fit: cover;
  background: #e5e7eb;
}

/* CONTENT */
.event-content {
  padding: 18px 20px 22px;
  display: flex;
  flex-direction: column;
  flex: 1;
}

.event-title {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 6px;
  color: #111827;
}

.event-meta {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 12px;
}

.event-excerpt {
  font-size: 14px;
  color: #374151;
  line-height: 1.5;
  flex: 1;
}

/* CTA */
.event-actions {
  margin-top: 16px;
}

.event-actions a {
  display: inline-block;
  padding: 10px 16px;
  border-radius: 10px;
  background: #6c7cff;
  color: #fff;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
}

.event-actions a:hover {
  background: #5a67e8;
}

/* =====================================================
   MOBILE
===================================================== */
@media (max-width: 640px) {
  .events-page {
    padding: 24px 14px 60px;
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
    <a href="../../index.php" class="btn subtle">Home</a>
    <?php if ($user && in_array($user['role'], ['mpp','admin'], true)): ?>
      <a href="admin_events.php" class="btn primary">Manage Events</a>
    <?php else: ?>
     
    <?php endif; ?>
  </nav>
</header>

<main class="events-page">

  <h2 class="events-title">Upcoming & Published Events</h2>
  <p class="events-subtitle">
    Discover campus events, activities, and programs organised by MPP.
  </p>

  <?php if (empty($rows)): ?>
    <p class="lead">No published events yet.</p>
  <?php else: ?>
    <div class="events-grid">

      <?php foreach ($rows as $r): ?>
        <article class="event-card">

          <?php if (!empty($r['image'])): ?>
            <img
              src="../../uploads/events/<?= htmlspecialchars($r['image']) ?>"
              alt="<?= htmlspecialchars($r['title']) ?>"
              class="event-image">
          <?php else: ?>
            <div class="event-image"></div>
          <?php endif; ?>

          <div class="event-content">
            <h3 class="event-title"><?= htmlspecialchars($r['title']) ?></h3>

            <div class="event-meta">
              <?= date('M j, Y · g:i A', strtotime($r['start_at'])) ?>
              <?php if (!empty($r['location'])): ?>
                · <?= htmlspecialchars($r['location']) ?>
              <?php endif; ?>
            </div>

            <div class="event-excerpt">
              <?= htmlspecialchars($r['excerpt']) ?>
            </div>

            <div class="event-actions">
              <a href="event.view.php?id=<?= (int)$r['id'] ?>">
                View Details →
              </a>
            </div>
          </div>

        </article>
      <?php endforeach; ?>

    </div>
  <?php endif; ?>

</main>

</body>
</html>
