<?php
// admin_announcements.php - admin side announcements management (FINAL)

// Path to project root (filesystem)
$PROJECT_ROOT = dirname(__DIR__, 2);

// include project-wide config and helpers
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';

// Build web-root path
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';

// Auth check
$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: ' . ($webRoot ?: '../../') . '/login.php');
    exit;
}

// Search / list
$search = trim($_GET['q'] ?? '');
$archived = (($_GET['archived'] ?? '') === '1');
try {
    if ($search !== '') {
      $stmt = $pdo->prepare("
    SELECT a.*, u.name AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.author_id = u.id
    WHERE (a.title LIKE ? OR a.excerpt LIKE ?)
    ORDER BY COALESCE(a.publish_at, a.created_at) DESC
  ");
      
      $stmt->execute(['%'.$search.'%','%'.$search.'%']);
    } else {
      $stmt = $pdo->query("
    SELECT a.*, u.name AS author_name
    FROM announcements a
    LEFT JOIN users u ON a.author_id = u.id
    WHERE 1=1
    ORDER BY COALESCE(a.publish_at, a.created_at) DESC
  ");
      
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Filter rows based on archived toggle
    if ($archived) {
      $rows = array_values(array_filter($rows, function($r){ return ($r['status'] ?? '') === 'archived'; }));
    } else {
      $rows = array_values(array_filter($rows, function($r){ return ($r['status'] ?? '') !== 'archived'; }));
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    $rows = [];
}

function root_url($path = '') {
    global $webRoot;
    return ($webRoot ?: '') . '/' . ltrim($path, '/');
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Manage Announcements — MPPConnect</title>

<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
<style>
  /* Ensure global gradient stays fixed while scrolling */
  html, body { min-height: 100%; margin: 0; padding: 0; }
  body { background-attachment: fixed; background-repeat: no-repeat; background-size: cover; }

  .thumb {
  width:60px;
  height:40px;
  object-fit:cover;
  border-radius:6px;
  background:#eee;
}
.small{font-size:13px;color:var(--muted)}
</style>
</head>
<body>

<header class="topbar">
  <div class="brand">
    <div class="logo-blob"></div>
    <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
  </div>
  <nav class="nav">
    <a class="btn subtle" href="<?= root_url('index.php') ?>">Home</a>
    <a class="btn subtle" href="announcements_public.php">Public View</a>
    
  </nav>
</header>

<main class="container">
<div class="card">

  <div style="display:flex;justify-content:space-between;align-items:center">
    <h2><?= $archived ? 'Archived Announcements' : 'Announcements' ?></h2>
    <div style="display:flex;gap:8px;align-items:center">
      <?php if ($archived): ?>
        <a class="btn subtle" href="admin_announcements.php">Back to Active</a>
      <?php else: ?>
        <a class="btn subtle" href="admin_announcements.php?archived=1">View Archived</a>
      <?php endif; ?>
      <a class="btn primary" href="announcement_create.php">+ Create Announcement</a>
    </div>
  </div>

  <form method="get" style="margin-top:14px;display:flex;gap:8px">
    <input type="search" name="q" value="<?= htmlspecialchars($search) ?>"
           placeholder="Search announcements..."
           style="padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,.1);width:320px">
    <button class="btn subtle">Search</button>
  </form>

  <table style="width:100%;margin-top:18px;border-collapse:collapse">
    <thead>
      <tr>
        <th>Image</th>
        <th>Title & Excerpt</th>
        <th>Status</th>
        <th>Publish</th>
        <th>Author</th>
        <th style="text-align:right">Actions</th>
      </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
      <tr><td colspan="6" class="small">No announcements found.</td></tr>
    <?php else: foreach ($rows as $r): ?>
      <tr style="border-top:1px solid rgba(0,0,0,.06)">
        <td>
          <?php if (!empty($r['image'])): ?>
            <img src="<?= root_url('uploads/announcements/'.$r['image']) ?>" class="thumb">
          <?php else: ?>
            <span class="small">No image</span>
          <?php endif; ?>
        </td>
        <td>
          <strong><?= htmlspecialchars($r['title']) ?></strong>
          <div class="small"><?= htmlspecialchars($r['excerpt']) ?></div>
        </td>
        <td><?= htmlspecialchars($r['status']) ?></td>
        <td><?= $r['publish_at'] ? date('Y-m-d', strtotime($r['publish_at'])) : '-' ?></td>
        <td><?= htmlspecialchars($r['author_name']) ?></td>
        <td style="text-align:right">
          <a class="btn subtle" href="announcement_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
          <?php if ($archived): ?>
            <form method="post" action="announcement_action.php" style="display:inline" onsubmit="return confirm('Unarchive this announcement?');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="unarchive">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <button class="btn subtle">Unarchive</button>
            </form>
            <form method="post" action="announcement_action.php" style="display:inline" onsubmit="return confirm('Delete permanently? This cannot be undone.');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <button class="btn ghost" style="margin-left:6px">Delete</button>
            </form>
          <?php else: ?>
            <form method="post" action="announcement_action.php" style="display:inline" onsubmit="return confirm('Archive this announcement?');">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <input type="hidden" name="action" value="archive">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
              <button class="btn ghost">Archive</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>

</div>
</main>
</body>
</html>
