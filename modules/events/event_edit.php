<?php
// modules/events/event_edit.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /MPPCONNECT/login.php');
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_events.php');
    exit;
}

/* Fetch existing event */
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header('Location: admin_events.php');
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
               IMAGE HANDLING
            =============================== */
            $newImageName = $event['image']; // default: keep old image

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
                    $newImageName = 'event_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

                    /* Move new image */
                    move_uploaded_file(
                        $_FILES['image']['tmp_name'],
                        $uploadDir . $newImageName
                    );

                    /* Delete old image if exists */
                    if (!empty($event['image'])) {
                        $oldPath = $uploadDir . $event['image'];
                        if (is_file($oldPath)) {
                            unlink($oldPath);
                        }
                    }
                }
            }

            if (!$error) {
                try {
                    $up = $pdo->prepare("
                        UPDATE events SET
                          title = ?,
                          excerpt = ?,
                          description = ?,
                          location = ?,
                          capacity = ?,
                          start_at = ?,
                          end_at = ?,
                          image = ?,
                          status = ?,
                          updated_at = NOW()
                        WHERE id = ?
                    ");

                    $up->execute([
                        $title,
                        $excerpt,
                        $description,
                        $location,
                        $capacity,
                        $start_at,
                        $end_at,
                        $newImageName,
                        $status,
                        $id
                    ]);

                    header('Location: admin_events.php');
                    exit;

                } catch (Exception $e) {
                    error_log('event_edit error: ' . $e->getMessage());
                    $error = 'Failed to update event.';
                }
            }
        }
    }
}

$csrf = csrf_token();
$action = htmlspecialchars($_SERVER['PHP_SELF'] . '?id=' . $id);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Edit Event — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
    <link rel="stylesheet" href="../../css/style.css">
    <style>
        /* Ensure global gradient stays fixed while scrolling */
        html, body { min-height: 100%; margin: 0; padding: 0; }
        body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }
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
</header>

<main class="container">
  <div class="card">
    <h2>Edit event</h2>

    <?php if ($error): ?>
      <div style="color:#b00020;margin-bottom:12px">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <?php
      // event_form.php now supports image preview & upload
      include __DIR__ . '/event_form.php';
    ?>
  </div>
</main>

</body>
</html>
