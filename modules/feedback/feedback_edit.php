<?php
// modules/feedback/feedback_edit.php

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';
require_once __DIR__ . '/inc/image_helper.php';

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

/* Only the original author can edit, and anonymous feedback is locked */
if (empty($fb['user_id']) || (int)$fb['user_id'] !== (int)$user['id']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating    = (int)($_POST['rating'] ?? 0);
    $comment   = trim($_POST['comment'] ?? '');
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please select a rating between 1 and 5.';
    }

    if (empty($errors)) {
        $upd = $pdo->prepare("
            UPDATE feedbacks
            SET rating = :rating,
                comment = :comment,
                anonymous = :anon,
                updated_at = NOW()
            WHERE id = :id
        ");
        $upd->execute([
            ':rating'  => $rating,
            ':comment' => $comment,
            ':anon'    => $anonymous,
            ':id'      => $id
        ]);

        /* Append images (existing images are preserved) */
        if (!empty($_FILES['images']['name'][0])) {
            $saved = save_feedback_images($_FILES['images'], $id, $imgErrors);
            foreach ($saved as $s) {
                $pdo->prepare("
                    INSERT INTO feedback_images
                    (feedback_id, filename, original_name, mime, size)
                    VALUES (?,?,?,?,?)
                ")->execute([
                    $id,
                    $s['filename'],
                    $s['original_name'],
                    $s['mime'],
                    $s['size']
                ]);
            }
            if (!empty($imgErrors)) {
                $errors = array_merge($errors, $imgErrors);
            }
        }

        if (empty($errors)) {
            header('Location: feedback_view.php?id=' . $id);
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Edit Feedback — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="/MPPCONNECT/css/style.css">

<style>
.feedback-edit {
  max-width: 720px;
  margin: 0 auto;
  padding: 40px 20px 80px;
}

.feedback-edit h2 {
  font-size: 22px;
  font-weight: 800;
  margin-bottom: 6px;
}

.feedback-context {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 18px;
}

.feedback-helper {
  font-size: 14px;
  color: #6b7280;
  margin-bottom: 16px;
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
    <a href="feedback_view.php?id=<?= (int)$id ?>" class="btn subtle">Back</a>
  </nav>
</header>

<main class="feedback-edit">

  <div class="card">

    <h2>Edit Feedback</h2>
    <div class="feedback-context">
      <?= htmlspecialchars($fb['event_title'] ?? $fb['prompt_title'] ?? 'Feedback') ?>
    </div>
    <div class="feedback-helper">
      You may update your rating, comment, or add additional images.
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul>
          <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

      <label>Overall rating
        <select name="rating" required>
          <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= ((int)$fb['rating'] === $i) ? 'selected' : '' ?>>
              <?= $i ?> — <?= str_repeat('★', $i) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>

      <label>Comment
        <textarea name="comment" rows="4"><?= htmlspecialchars($fb['comment'] ?? '') ?></textarea>
      </label>

      <label>Attach additional images (optional)
        <input type="file" name="images[]" accept="image/*" multiple>
      </label>

      <label class="inline">
        <input type="checkbox" name="anonymous" <?= $fb['anonymous'] ? 'checked' : '' ?>>
        Submit anonymously
      </label>

      <div style="margin-top:16px;">
        <button class="btn primary" type="submit">Save Changes</button>
        <a class="btn subtle" href="feedback_view.php?id=<?= (int)$id ?>">Cancel</a>
      </div>

    </form>

  </div>

</main>

</body>
</html>
