<?php
// modules/feedback/feedback_create.php

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once __DIR__ . '/inc/feedback_model.php';
require_once __DIR__ . '/inc/image_helper.php';

/* resolve web root */
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

/* ===============================
   PROMPT RESOLUTION
================================ */
$fatal_error = null;
$prompt = null;
$prompt_id = (int)($_GET['prompt'] ?? 0);
$event_id  = (int)($_GET['event'] ?? 0);

if ($prompt_id > 0) {
  $prompt = get_prompt($pdo, $prompt_id);
} elseif ($event_id > 0) {
  $prompt = get_prompt_by_event($pdo, $event_id);
}

// If no prompt exists but event is provided, auto-create a prompt
if (!$prompt && $event_id > 0) {
  // Load event details
  $evStmt = $pdo->prepare("SELECT id, title, end_at FROM events WHERE id = ? LIMIT 1");
  $evStmt->execute([$event_id]);
  $ev = $evStmt->fetch(PDO::FETCH_ASSOC);

  if (!$ev) {
    $fatal_error = 'Event not found.';
  } else {
    $hasEnded = false;
    if (!empty($ev['end_at']) && $ev['end_at'] !== '0000-00-00 00:00:00') {
      $hasEnded = (time() >= strtotime($ev['end_at']));
    }

    // Verify the current user registered for this event (by user_id or email)
    $isRegistered = false;
    try {
      $regChk = $pdo->prepare(
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
      $regChk->execute([$event_id, $user['id'], $user['email'] ?? '']);
      $isRegistered = (bool)$regChk->fetchColumn();
    } catch (Throwable $e) {
      $isRegistered = false;
    }

    if (!$hasEnded) {
      $fatal_error = 'Feedback is available only after the event ends.';
    } elseif (!$isRegistered) {
      $fatal_error = 'You must be registered for this event to give feedback.';
    } else {
      // Create prompt with a sensible title; open_at defaults to event end time
      $newPid = create_feedback_prompt_for_event(
        $pdo,
        (int)$ev['id'],
        'Feedback for ' . ($ev['title'] ?? ('Event #' . (int)$ev['id'])),
        $ev['end_at'] ?? null,
        null
      );
      // Whether newly created or already existed, try to fetch now
      $prompt = get_prompt_by_event($pdo, (int)$ev['id']);
    }
  }
}

if ($prompt) {
  $prompt_id = (int)$prompt['id'];
  $event_id  = (int)$prompt['event_id'];
} elseif ($fatal_error === null) {
  $fatal_error = 'This feedback request is no longer available or has already been completed.';
}

/* ===============================
   SUBMISSION
================================ */
$errors = [];

if (!$fatal_error && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $rating    = (int)($_POST['rating'] ?? 0);
    $comment   = trim($_POST['comment'] ?? '');
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;

    if ($rating < 1 || $rating > 5) {
        $errors[] = 'Please choose a rating between 1 and 5.';
    }

    // prevent duplicate by user account (anonymous entries can't be reliably linked)
    $chk = $pdo->prepare("
        SELECT COUNT(*) FROM feedbacks 
        WHERE prompt_id = :pid 
          AND deleted_at IS NULL
          AND user_id = :uid
    ");
    $chk->execute([
        ':pid' => $prompt_id,
        ':uid' => $user['id']
    ]);

    if ((int)$chk->fetchColumn() > 0) {
        $errors[] = 'You have already submitted feedback for this event.';
    }

    if (empty($errors)) {
        $fid = insert_feedback($pdo, [
            'prompt_id' => $prompt_id,
            'event_id'  => $event_id,
            'user_id'   => $anonymous ? null : $user['id'],
            'rating'    => $rating,
            'comment'   => $comment,
            'anonymous' => $anonymous
        ]);

        /* Images */
        if (!empty($_FILES['images']['name'][0])) {
            $saved = save_feedback_images($_FILES['images'], $fid, $imgErrors);
            foreach ($saved as $s) {
                $pdo->prepare("
                    INSERT INTO feedback_images
                    (feedback_id, filename, original_name, mime, size)
                    VALUES (?,?,?,?,?)
                ")->execute([
                    $fid,
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
            header('Location: feedback_list.php?submitted=1');
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
<title>Give Feedback — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">

<style>
.feedback-wrap {
  max-width:720px;
  margin:0 auto;
  padding:40px 20px 80px;
}
.notice {
  background:#f9fafb;
  border:1px solid #e5e7eb;
  padding:18px;
  border-radius:14px;
  color:#374151;
}
.feedback-helper {
  font-size:14px;
  color:#6b7280;
  margin-bottom:16px;
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
    <a href="<?= root_url('index.php') ?>" class="btn subtle">Home</a>
  </nav>
</header>

<main class="feedback-wrap">

<?php if ($fatal_error): ?>
  <div class="notice">
    <h3>Feedback unavailable</h3>
    <p><?= htmlspecialchars($fatal_error) ?></p>
    <a class="btn primary" href="feedback_list.php">Return to feedback list</a>
  </div>
<?php else: ?>

  <div class="card">
    <h2>Feedback for <?= htmlspecialchars($prompt['event_title'] ?? $prompt['title']) ?></h2>
    <div class="feedback-helper">
      Your response helps improve future events. Your identity is kept confidential.
    </div>

    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger">
        <ul><?php foreach ($errors as $e) echo '<li>'.htmlspecialchars($e).'</li>'; ?></ul>
      </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">

      <label>Overall rating
        <select name="rating" required>
          <option value="">Select</option>
          <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>" <?= ((int)($_POST['rating'] ?? 0) === $i) ? 'selected' : '' ?>>
              <?= $i ?> — <?= str_repeat('★', $i) ?>
            </option>
          <?php endfor; ?>
        </select>
      </label>

      <label>Comments (optional)
        <textarea name="comment" rows="4"><?= htmlspecialchars($_POST['comment'] ?? '') ?></textarea>
      </label>

      <label>Attach images (optional)
        <input type="file" name="images[]" multiple accept="image/*">
      </label>

      <label class="inline">
        <input type="checkbox" name="anonymous" <?= isset($_POST['anonymous']) ? 'checked' : '' ?>>
        Submit anonymously
      </label>

      <div style="margin-top:16px">
        <button class="btn primary" type="submit">Submit Feedback</button>
        <a class="btn subtle" href="feedback_list2.php">Cancel</a>
      </div>

    </form>
  </div>

<?php endif; ?>

</main>

</body>
</html>
