<?php
// announcement_edit.php - edit announcement (WITH IMAGE SUPPORT)

// Path to project root
$PROJECT_ROOT = dirname(__DIR__, 2);

require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';

// Build web root
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

// Auth
$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: ' . root_url('login.php'));
    exit;
}

// Validate ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_announcements.php');
    exit;
}

// Fetch announcement
$stmt = $pdo->prepare("SELECT * FROM announcements WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$announcement = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$announcement) {
    header('Location: admin_announcements.php');
    exit;
}

$error = null;

// HANDLE UPDATE
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {

        $title   = trim($_POST['title'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $body    = trim($_POST['body'] ?? '');
        $tag     = trim($_POST['tag'] ?? '');
        $status  = in_array($_POST['status'] ?? '', ['draft','published','archived'], true)
                    ? $_POST['status'] : 'draft';
        $publish_at = trim($_POST['publish_at'] ?? '') ?: null;

        // Keep existing image by default
        $imageName = $announcement['image'];

        // IMAGE UPLOAD (OPTIONAL)
        if (!empty($_FILES['image']['name'])) {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG or WEBP images allowed.';
            } else {
                $imageName = 'announcement_' . $id . '_' . time() . '.' . $ext;
                $uploadDir = $PROJECT_ROOT . '/uploads/announcements/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                move_uploaded_file(
                    $_FILES['image']['tmp_name'],
                    $uploadDir . $imageName
                );
            }
        }

        if (!$error && $title !== '' && $body !== '') {
            $up = $pdo->prepare("
                UPDATE announcements
                SET title=?, excerpt=?, body=?, tag=?, status=?, publish_at=?, image=?, updated_at=NOW()
                WHERE id=?
            ");
            $up->execute([
                $title, $excerpt, $body, $tag,
                $status, $publish_at, $imageName, $id
            ]);

            header('Location: admin_announcements.php');
            exit;
        }

        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        }
    }
}

$csrf = csrf_token();
$action = 'announcement_edit.php?id=' . $id;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Edit Announcement — MPPConnect</title>
<link rel="stylesheet" href="<?= root_url('css/style.css') ?>">
</head>

<body>

<header class="topbar">
  <div class="brand">
    <div class="logo-blob"></div>
    <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
  </div>
  <nav class="nav">
    <a class="btn subtle" href="admin_announcements.php">Back</a>
    <a class="btn subtle" href="<?= root_url('logout.php') ?>">Sign Out</a>
  </nav>
</header>

<main class="container">
  <div class="card">
    <h2>Edit Announcement</h2>

    <?php if ($error): ?>
      <p style="color:#b00020"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php include __DIR__ . '/announcement_form.php'; ?>
  </div>
</main>

</body>
</html>
