<?php
// modules/progress/progress_form.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /MPPCONNECT/login.php');
    exit;
}

/* ===============================
   MODE & DATA
================================ */
$action = ($_GET['action'] ?? 'create') === 'edit' ? 'edit' : 'create';
$id = (int)($_GET['id'] ?? 0);

$entry = [
    'title' => '',
    'description' => '',
    'status' => 'draft'
];
$images = [];

/* ===============================
   LOAD EXISTING ENTRY (EDIT)
================================ */
if ($action === 'edit' && $id > 0) {

    $stmt = $pdo->prepare("
        SELECT * FROM progress_entries 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmt->execute([$id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$entry) {
        header('Location: progress_manage.php');
        exit;
    }

    // Only owner or admin can edit
    if ($user['role'] !== 'admin' && (int)$entry['user_id'] !== (int)$user['id']) {
        header('Location: progress_manage.php');
        exit;
    }

    // Load images
    $imgStmt = $pdo->prepare("
        SELECT * 
        FROM progress_images 
        WHERE progress_id = ? 
        ORDER BY id ASC
    ");
    $imgStmt->execute([$id]);
    $images = $imgStmt->fetchAll(PDO::FETCH_ASSOC);
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title><?= $action === 'edit' ? 'Edit Progress' : 'New Progress' ?> — MPPConnect</title>

<link rel="stylesheet" href="../../css/style.css">

<style>
.preview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(120px,1fr));
  gap: 14px;
  margin-top: 14px;
}
.preview-grid img {
  width: 100%;
  height: 90px;
  object-fit: cover;
  border-radius: 10px;
  border: 1px solid #e5e7eb;
}
.preview-item {
  text-align: center;
}
.preview-item form {
  margin-top: 6px;
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
    <a class="btn subtle" href="progress_manage.php">Back</a>
    <a class="btn subtle" href="../../logout.php">Sign Out</a>
  </nav>
</header>

<main class="container">
  <div class="card">

    <h2><?= $action === 'edit' ? 'Edit Progress Post' : 'Create Progress Post' ?></h2>

    <form
      method="post"
      action="progress_save.php<?= $action === 'edit' ? '?id='.(int)$id : '' ?>"
      enctype="multipart/form-data"
      novalidate
    >

      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

      <!-- TITLE -->
      <label>
        Title
        <input
          type="text"
          name="title"
          required
          value="<?= htmlspecialchars($entry['title']) ?>"
          style="width:100%;padding:10px;border-radius:8px;border:1px solid #d1d5db"
        >
      </label>

      <br><br>

      <!-- DESCRIPTION -->
      <label>
        Description
        <textarea
          name="description"
          rows="6"
          style="width:100%;padding:10px;border-radius:8px;border:1px solid #d1d5db"
        ><?= htmlspecialchars($entry['description']) ?></textarea>
      </label>

      <br><br>

      <!-- STATUS -->
      <label>
        Status
        <select name="status" style="padding:8px;border-radius:8px;border:1px solid #d1d5db">
          <option value="draft" <?= $entry['status']==='draft' ? 'selected' : '' ?>>Draft</option>
          <option value="published" <?= $entry['status']==='published' ? 'selected' : '' ?>>Published</option>
        </select>
      </label>

      <br><br>

      <!-- IMAGE UPLOAD -->
      <label>
        Upload images (JPEG / PNG / WEBP · max 5MB each)
        <input
          type="file"
          name="images[]"
          multiple
          accept="image/jpeg,image/png,image/webp"
        >
      </label>

      <!-- EXISTING IMAGES -->
      <?php if (!empty($images)): ?>
        <div class="preview-grid">
          <?php foreach ($images as $img): ?>
            <div class="preview-item">
              <img
                src="../../uploads/progress/<?= htmlspecialchars($img['filename']) ?>"
                alt="Progress image"
              >
              <form method="post" action="progress_image_delete.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                <button class="btn ghost" type="submit">Remove</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div style="margin-top:18px;display:flex;gap:10px">
        <button class="btn primary" type="submit">Save Post</button>
        <a class="btn subtle" href="progress_manage.php">Cancel</a>
      </div>

    </form>

  </div>
</main>

</body>
</html>
