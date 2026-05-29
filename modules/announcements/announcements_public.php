<?php
// announcements_public.php - Facebook-style feed (FIXED BACKGROUND)

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

/* Resolve web root safely */
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';

function root_url($path = '') {
    global $webRoot;
    return ($webRoot ?: '') . '/' . ltrim($path, '/');
}

/* Fetch announcements */
try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.title, a.excerpt, a.image, a.publish_at,
               u.name AS author
        FROM announcements a
        LEFT JOIN users u ON u.id = a.author_id
        WHERE a.status = 'published'
        ORDER BY COALESCE(a.publish_at, a.created_at) DESC
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
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Announcements — MPPConnect</title>

<link rel="stylesheet" href="<?= root_url('css/style.css') ?>">

<style>
/* =========================================================
   GLOBAL BACKGROUND FIX (IMPORTANT)
========================================================= */
html, body {
  margin: 0;
  padding: 0;
  min-height: 100%;
}

body {
  font-family: "Inter", system-ui, -apple-system, BlinkMacSystemFont, sans-serif;
  background: linear-gradient(180deg, #dde3ed, #95bbe9);
  background-repeat: no-repeat;
  background-attachment: fixed;
  color: #06101a;
}

/* =========================================================
   FEED WRAPPER
========================================================= */
.container {
  padding: 40px 0;
}

.feed {
  max-width: 900px;
  margin: 0 auto;
  display: flex;
  flex-direction: column;
  gap: 22px;
}

/* =========================================================
   POST CARD
========================================================= */
.feed-post {
  background: rgba(255,255,255,0.88);
  border-radius: 20px;
  border: 1px solid rgba(0,0,0,0.08);
  overflow: hidden;
  box-shadow: 0 12px 28px rgba(0,0,0,0.08);
}

/* HEADER */
.feed-header {
  padding: 18px 22px;
}

.feed-author {
  font-weight: 700;
  font-size: 15px;
}

.feed-date {
  font-size: 12px;
  color: #6b7280;
}

/* IMAGE */
.feed-image {
  width: 100%;
  height: 420px;
  object-fit: cover;
  background: #eaeaea;
}

/* BODY */
.feed-body {
  padding: 18px 22px;
}

.feed-body h3 {
  margin: 0 0 10px;
  font-size: 20px;
}

.feed-body p {
  margin: 0;
  font-size: 15px;
  line-height: 1.6;
  color: #4b5563;
}

/* FOOTER */
.feed-footer {
  padding: 16px 22px;
  border-top: 1px solid rgba(0,0,0,0.08);
}

.feed-footer a {
  font-weight: 700;
  color: #7b68ee;
  text-decoration: none;
}

.feed-footer a:hover {
  text-decoration: underline;
}

/* =========================================================
   MOBILE
========================================================= */
@media (max-width: 768px) {
  .container {
    padding: 20px 0;
  }
  .feed {
    max-width: 100%;
    padding: 0 12px;
  }
  .feed-image {
    height: 240px;
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
    <?php if ($user && in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
      <a href="<?= root_url('modules/announcements/admin_announcements.php') ?>" class="btn primary">Manage</a>
    <?php else: ?>
      
    <?php endif; ?>
  </nav>
</header>

<main class="container">
  <div class="feed">

    <?php if (empty($rows)): ?>
      <p class="lead">No announcements yet.</p>
    <?php else: foreach ($rows as $r): ?>

      <article class="feed-post">

        <div class="feed-header">
          <div class="feed-author">
            <?= htmlspecialchars($r['author'] ?? 'MPP') ?>
          </div>
          <div class="feed-date">
            <?= $r['publish_at'] ? date('M j, Y', strtotime($r['publish_at'])) : '' ?>
          </div>
        </div>

        <?php if (!empty($r['image'])): ?>
          <img
            src="<?= root_url('uploads/announcements/' . $r['image']) ?>"
            alt="<?= htmlspecialchars($r['title']) ?>"
            class="feed-image">
        <?php endif; ?>

        <div class="feed-body">
          <h3><?= htmlspecialchars($r['title']) ?></h3>
          <p><?= htmlspecialchars($r['excerpt']) ?></p>
        </div>

        <div class="feed-footer">
          <a href="<?= root_url('/modules/announcements/announcement_view.php?id=' . (int)$r['id']) ?>">
            Read more →
          </a>
        </div>

      </article>

    <?php endforeach; endif; ?>

  </div>
</main>

</body>
</html>
