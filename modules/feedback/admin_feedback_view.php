<?php
// modules/feedback/admin_feedback_view.php
$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';

$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) { header('Location: /login.php'); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$fb = get_feedback_by_id($pdo, $id);
if (!$fb) { header('Location: admin_feedback.php'); exit; }
$images = get_feedback_images($pdo, $id);
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<link rel="stylesheet" href="/css/style.css">
<body>
<header class="topbar"><nav class="nav"><a href="admin_feedback.php" class="btn subtle">Back</a></nav></header>
<main class="container"><div class="card">
  <h2><?= htmlspecialchars($fb['event_title'] ?? $fb['prompt_title'] ?? 'Feedback') ?></h2>
  <small class="micro"><?= $fb['anonymous'] ? 'Anonymous' : htmlspecialchars($fb['user_name'] ?? 'Student') ?> · <?= (int)$fb['rating'] ?>/5 · <?= htmlspecialchars(date('M j, Y', strtotime($fb['created_at']))) ?></small>
  <p><?= nl2br(htmlspecialchars($fb['comment'] ?? '')) ?></p>
  <?php if (!empty($images)): ?><div><strong>Images</strong><div style="display:flex;gap:8px;margin-top:8px;"><?php foreach ($images as $img): ?>
    <a href="serve_image_feedback.php?img=<?= (int)$img['id'] ?>" target="_blank"><img src="serve_image_feedback.php?img=<?= (int)$img['id'] ?>&thumb=1" style="width:120px;height:80px;object-fit:cover;border-radius:6px"></a>
  <?php endforeach; ?></div></div><?php endif; ?>
</div></main></body></html>
