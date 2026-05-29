<?php
// modules/events/event_create.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /MPPCONNECT/login.php');
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {

        /* ===============================
           FORM DATA
        =============================== */
        $title = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $capacity = ($_POST['capacity'] !== '' ? (int)$_POST['capacity'] : null);
        $start_at = trim($_POST['start_at'] ?? '');
        $end_at = trim($_POST['end_at'] ?? '') ?: null;
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','cancelled'], true)
            ? $_POST['status']
            : 'draft';

        if ($title === '' || $description === '' || $start_at === '') {
            $error = 'Title, description and start date are required.';
        } else {

            /* ===============================
               IMAGE UPLOAD (OPTIONAL)
            =============================== */
            $imageFileName = null;

            if (!empty($_FILES['image']['name'])) {
                $uploadDir = __DIR__ . '/../../uploads/events/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                $allowedTypes = ['image/jpeg','image/png','image/webp'];
                if (!in_array($_FILES['image']['type'], $allowedTypes, true)) {
                    $error = 'Invalid image type. Only JPG, PNG, or WEBP allowed.';
                } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                    $error = 'Image upload failed.';
                } else {
                    $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                    $imageFileName = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $imageFileName);
                }
            }

            if (!$error) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO events
                        (title, excerpt, description, location, capacity, start_at, end_at, image, author_id, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");

                    $stmt->execute([
                        $title,
                        $excerpt,
                        $description,
                        $location,
                        $capacity,
                        $start_at,
                        $end_at,
                        $imageFileName,
                        $user['id'],
                        $status
                    ]);

                    $event_id = (int)$pdo->lastInsertId();

                    /* ===============================
                       FEEDBACK PROMPT (UNCHANGED)
                    =============================== */
                    $fbModelPath = __DIR__ . '/../feedback/inc/feedback_model.php';
                    if (is_file($fbModelPath)) {
                        require_once $fbModelPath;

                        $open_at = null;
                        $close_at = null;
                        if (!empty($end_at)) {
                            $ts = strtotime($end_at);
                            if ($ts !== false) {
                                $open_at = date('Y-m-d H:i:s', $ts);
                                $close_at = date('Y-m-d H:i:s', strtotime('+30 days', $ts));
                            }
                        }

                        try {
                            create_feedback_prompt_for_event($pdo, $event_id, $title, $open_at, $close_at);
                        } catch (Exception $e) {
                            error_log('Feedback hook error: ' . $e->getMessage());
                        }
                    }

                    header('Location: admin_events.php');
                    exit;

                } catch (Exception $e) {
                    error_log('event_create error: ' . $e->getMessage());
                    $error = 'Failed to create event.';
                }
            }
        }
    }
}

$csrf = csrf_token();
$action = '';
$event = null;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Create Event — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
  <link rel="stylesheet" href="../../css/style.css">
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
    <a class="btn subtle" href="admin_events.php">Back</a>
    
  </nav>
</header>

<main class="container">
  <div class="card">
    <h2>Create event</h2>
    <?php if ($error): ?>
      <div style="color:#b00020;margin-bottom:12px"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php
      // event_form.php contains the form fields + file input
      include __DIR__ . '/event_form.php';
    ?>
  </div>
</main>

</body>
</html>
