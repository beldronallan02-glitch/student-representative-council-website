<?php
// modules/progress/progress_view.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

$user = current_user();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: progress_public.php');
    exit;
}

/* ===============================
   FETCH ENTRY
================================ */
$stmt = $pdo->prepare("
    SELECT 
        p.*, 
        u.name AS author
    FROM progress_entries p
    LEFT JOIN users u ON p.user_id = u.id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$entry = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$entry) {
    header('Location: progress_public.php');
    exit;
}

/* ===============================
   ACCESS CONTROL
================================ */
$is_owner = $user && (int)$user['id'] === (int)$entry['user_id'];
$is_admin = $user && in_array($user['role'], ['mpp','admin'], true);

if ($entry['status'] !== 'published' && !($is_owner || $is_admin)) {
    header('Location: progress_public.php');
    exit;
}

/* ===============================
   FETCH IMAGES
================================ */
$imgStmt = $pdo->prepare("
    SELECT * 
    FROM progress_images 
    WHERE progress_id = ?
    ORDER BY id ASC
");
$imgStmt->execute([$id]);
$images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($entry['title']) ?> — MPPConnect</title>

<link rel="stylesheet" href="../../css/style.css">

<style>
.progress-view {
  max-width: 980px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.progress-title {
  font-size: 26px;
  font-weight: 800;
  margin-bottom: 6px;
}

.progress-meta {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 14px;
}

.progress-desc {
  font-size: 15px;
  line-height: 1.65;
  color: #374151;
}

/* IMAGE GRID */
.progress-gallery {
  margin-top: 24px;
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px;
}

.progress-gallery img {
  width: 100%;
  height: 220px;
  object-fit: cover;
  border-radius: 14px;
  cursor: pointer;
  border: 1px solid #e5e7eb;
  transition: transform .2s ease, box-shadow .2s ease;
}

.progress-gallery img:hover {
  transform: translateY(-2px);
  box-shadow: 0 12px 24px rgba(0,0,0,0.15);
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
    <a class="btn subtle" href="progress_public.php">Back</a>
    <?php if ($is_owner || $is_admin): ?>
      <a class="btn subtle" href="progress_manage.php">Manage</a>
    <?php endif; ?>
  </nav>
</header>

<main class="progress-view">

  <div class="card">

    <div class="progress-title">
      <?= htmlspecialchars($entry['title']) ?>
    </div>

    <div class="progress-meta">
      By <?= htmlspecialchars($entry['author'] ?? 'MPP') ?>
      · <?= htmlspecialchars(date('M j, Y', strtotime($entry['created_at']))) ?>
      <?php if ($entry['status'] !== 'published'): ?>
        · <strong style="color:#b45309">Draft</strong>
      <?php endif; ?>
    </div>

    <?php if (!empty($entry['description'])): ?>
      <div class="progress-desc">
        <?= nl2br(htmlspecialchars($entry['description'])) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($images)): ?>
      <div class="progress-gallery">
        <?php foreach ($images as $img): ?>
          <a
            href="../../uploads/progress/<?= htmlspecialchars($img['filename']) ?>"
            target="_blank"
          >
            <img
              src="../../uploads/progress/<?= htmlspecialchars($img['filename']) ?>"
              alt="Progress image"
            >
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</main>

</body>
</html>
