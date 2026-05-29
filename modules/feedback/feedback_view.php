<?php
// modules/feedback/feedback_view.php

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';

$user = current_user();
if (!$user) {
    header('Location: /MPPCONNECT/login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: feedback_list.php');
    exit;
}

$fb = get_feedback_by_id($pdo, $id);
if (!$fb) {
    header('Location: feedback_list.php');
    exit;
}

$is_admin = in_array($user['role'] ?? '', ['mpp','admin'], true);
$is_owner = (!empty($fb['user_id']) && (int)$fb['user_id'] === (int)$user['id']);

if (!$is_admin && !$is_owner) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$images = get_feedback_images($pdo, $id);

/* Star renderer */
function render_stars(int $rating): string {
    $stars = '';
    for ($i = 1; $i <= 5; $i++) {
        $stars .= $i <= $rating ? '★' : '☆';
    }
    return $stars;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Feedback — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="/MPPCONNECT/css/style.css">

<style>
.feedback-view {
  max-width: 820px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.feedback-header {
  margin-bottom: 18px;
}

.feedback-title {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 6px;
}

.feedback-meta {
  font-size: 14px;
  color: #6b7280;
}

.feedback-rating {
  font-size: 20px;
  color: #f59e0b;
  margin: 14px 0;
}

.feedback-comment {
  font-size: 15px;
  line-height: 1.6;
  color: #374151;
}

.feedback-images {
  margin-top: 18px;
}

.feedback-images-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
  gap: 12px;
  margin-top: 10px;
}

.feedback-images-grid img {
  width: 100%;
  height: 90px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
  transition: transform .2s ease;
}

.feedback-images-grid img:hover {
  transform: scale(1.04);
}

.feedback-actions {
  margin-top: 22px;
  display: flex;
  gap: 10px;
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
    <a href="feedback_list2.php" class="btn subtle">Back</a>
  </nav>
</header>

<main class="feedback-view">

  <div class="card">

    <!-- HEADER -->
    <div class="feedback-header">
      <div class="feedback-title">
        <?= htmlspecialchars($fb['event_title'] ?? $fb['prompt_title'] ?? 'Feedback') ?>
      </div>
      <div class="feedback-meta">
        <?= $fb['anonymous'] ? 'Anonymous' : htmlspecialchars($fb['user_name'] ?? 'Student') ?>
        · <?= date('M j, Y', strtotime($fb['created_at'])) ?>
      </div>
    </div>

    <!-- RATING -->
    <div class="feedback-rating">
      <?= render_stars((int)$fb['rating']) ?>
      <span style="font-size:14px;color:#6b7280">
        (<?= (int)$fb['rating'] ?>/5)
      </span>
    </div>

    <!-- COMMENT -->
    <?php if (!empty($fb['comment'])): ?>
      <div class="feedback-comment">
        <?= nl2br(htmlspecialchars($fb['comment'])) ?>
      </div>
    <?php else: ?>
      <div class="micro">No written comment was provided.</div>
    <?php endif; ?>

    <!-- IMAGES -->
    <?php if (!empty($images)): ?>
      <div class="feedback-images">
        <strong>Attached Images</strong>
        <div class="feedback-images-grid">
          <?php foreach ($images as $img): ?>
            <a href="serve_image_feedback.php?img=<?= (int)$img['id'] ?>" target="_blank">
              <img
                src="serve_image_feedback.php?img=<?= (int)$img['id'] ?>&thumb=1"
                alt="Feedback attachment">
            </a>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>

    <!-- ACTIONS -->
    <?php if ($is_owner && !$fb['anonymous']): ?>
      <div class="feedback-actions">
        <a class="btn subtle" href="feedback_edit.php?id=<?= (int)$fb['id'] ?>">Edit</a>
        <a class="btn subtle" href="feedback_delete.php?id=<?= (int)$fb['id'] ?>">Withdraw</a>
      </div>
    <?php endif; ?>

  </div>

</main>

</body>
</html>
