<?php
// announcement_create.php - create a new announcement (robust includes)

// Path to project root (filesystem)
$PROJECT_ROOT = dirname(__DIR__, 2);

// include project-wide config and helpers
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';

// Build web-root path (URL) relative to document root so HTML links work.
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';

// Helper to build web links to project root files
function root_url($path = '') {
    global $webRoot;
    $path = ltrim($path, '/');
    return ($webRoot ?: '') . ($path ? "/{$path}" : '');
}

// Auth: only mpp or admin allowed
$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: ' . root_url('login.php'));
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $title = trim((string)($_POST['title'] ?? ''));
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $body = trim((string)($_POST['body'] ?? ''));
        $tag = trim((string)($_POST['tag'] ?? ''));
        $status = in_array($_POST['status'] ?? 'draft', ['draft','published','archived'], true) ? $_POST['status'] : 'draft';
        $publish_at = trim((string)($_POST['publish_at'] ?? '')) ?: null;

        if ($title === '' || $body === '') {
            $error = 'Title and body are required.';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO announcements (title, excerpt, body, tag, author_id, status, publish_at, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$title, $excerpt, $body, $tag, $user['id'], $status, $publish_at]);

                // After successful save redirect to admin list (avoid resubmit)
                header('Location: admin_announcements.php');
                exit;
            } catch (Exception $e) {
                error_log('announcement_create insert error: ' . $e->getMessage());
                $error = 'Failed to create announcement. Try again later.';
            }
        }
    }
}

// Prepare values for the form partial
$csrf = csrf_token();
// module-local action (posts to this file)
$action = 'announcement_create.php';
$announcement = [
  'title' => '',
  'excerpt' => '',
  'body' => '',
  'tag' => '',
  'status' => 'draft',
  'publish_at' => ''
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <title>Create Announcement — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
  <link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
  <style>
    /* Ensure global gradient stays fixed while scrolling */
    html, body { min-height: 100%; margin: 0; padding: 0; }
    body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }
  </style>
</head>
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a class="btn subtle" href="admin_announcements.php">Back</a>
      <a class="btn subtle" href="<?= htmlspecialchars(root_url('logout.php')) ?>">Sign Out</a>
    </nav>
  </header>

  <main class="container">
    <div class="card">
      <h2>Create announcement</h2>

      <?php if ($error): ?>
        <div style="color:#b00020;margin-bottom:12px;"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <?php
        // announcement_form.php expects $action, $csrf and $announcement variables
        include __DIR__ . '/announcement_form.php';
      ?>
    </div>
  </main>
</body>
</html>
