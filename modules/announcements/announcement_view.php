<?php
// announcement_view.php - view a single announcement (FINAL)

// Path to project root (filesystem)
$PROJECT_ROOT = dirname(__DIR__, 2);

// include project-wide config and helpers
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

// Build web-root path
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';

// Helper
function root_url($path = '') {
    global $webRoot;
    return ($webRoot ?: '') . '/' . ltrim($path, '/');
}

// Get ID
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: announcements_public.php');
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT a.*, u.name AS author_name
        FROM announcements a
        LEFT JOIN users u ON u.id = a.author_id
        WHERE a.id = ?
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $ann = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log($e->getMessage());
    $ann = false;
}

if (!$ann) {
    header('Location: announcements_public.php');
    exit;
}

$user = current_user();

// Authorization
if (($ann['status'] ?? '') !== 'published' &&
    (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true))) {
    header('Location: announcements_public.php');
    exit;
}

$publishAt = !empty($ann['publish_at'])
    ? date('M j, Y H:i', strtotime($ann['publish_at']))
    : date('M j, Y H:i', strtotime($ann['created_at']));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= htmlspecialchars($ann['title']) ?> — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">

<style>
/* Announcement hero image */
.announcement-hero{
  width:100%;
  height:260px;
  border-radius:16px;
  object-fit:cover;
  margin-bottom:20px;
  background:#eaeaea;
}

@media (max-width:700px){
  .announcement-hero{
    height:180px;
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
    <a href="announcements_public.php" class="btn subtle">Back</a>
    <a href="<?= root_url('index.php') ?>" class="btn subtle">Home</a>
  </nav>
</header>

<main class="container">
  <div class="card glass">

    <!-- IMAGE -->
    <?php if (!empty($ann['image'])): ?>
      <img
        src="<?= root_url('uploads/announcements/' . $ann['image']) ?>"
        alt="<?= htmlspecialchars($ann['title']) ?>"
        class="announcement-hero">
    <?php endif; ?>

    <!-- TITLE -->
    <h1><?= htmlspecialchars($ann['title']) ?></h1>

    <!-- META -->
    <div class="micro">
      By <?= htmlspecialchars($ann['author_name'] ?? 'MPP') ?>
      · <?= $publishAt ?>
    </div>

    <!-- EXCERPT -->
    <?php if (!empty($ann['excerpt'])): ?>
      <p class="lead"><?= htmlspecialchars($ann['excerpt']) ?></p>
    <?php endif; ?>

    <!-- BODY -->
    <div style="margin-top:14px">
      <?= nl2br(htmlspecialchars($ann['body'] ?? '')) ?>
    </div>

    <!-- ADMIN ACTIONS -->
    <?php if ($user && in_array($user['role'] ?? '', ['mpp','admin'], true)): ?>
      <div style="margin-top:20px">
        <a class="btn subtle" href="announcement_edit.php?id=<?= (int)$ann['id'] ?>">Edit</a>
        <form method="post" action="announcement_delete.php" style="display:inline"
              onsubmit="return confirm('Archive this announcement?');">
          <input type="hidden" name="id" value="<?= (int)$ann['id'] ?>">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
          <button class="btn ghost">Archive</button>
        </form>
      </div>
    <?php endif; ?>

  </div>
</main>

</body>
</html>
