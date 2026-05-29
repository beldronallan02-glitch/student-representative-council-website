<?php
// complaint_edit.php - edit complaint (owner only while 'new' or 'in_review')

$PROJECT_ROOT = dirname(__DIR__, 2);
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';

$docRoot = rtrim(str_replace('\\','/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$projRootFs = rtrim(str_replace('\\','/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
function root_url($path = '') { global $webRoot; $path = ltrim($path, '/'); return ($webRoot ?: '') . ($path ? "/{$path}" : ''); }

$user = current_user();
if (!$user) { header('Location: ' . root_url('login.php')); exit; }

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { header('Location: complaint_list.php'); exit; }

try {
    // Avoid deleted_at filter in case the column doesn't exist in this schema
    $stmt = $pdo->prepare("SELECT * FROM complaints WHERE id = :id LIMIT 1");
    $stmt->execute([':id'=>$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) throw new Exception('Not found');
    if ($c['user_id'] != $user['id']) { header('HTTP/1.1 403 Forbidden'); echo 'Forbidden'; exit; }
    if (!in_array($c['status'], ['new','in_review'], true)) { $err = "Cannot edit complaint in current status."; }
} catch (Exception $e) {
    error_log('complaint_edit error: '.$e->getMessage());
    header('Location: complaint_list.php'); exit;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $priority = in_array($_POST['priority'] ?? 'low', ['low','medium','high']) ? $_POST['priority'] : 'low';
    $description = trim($_POST['description'] ?? '');
    $is_anon = isset($_POST['is_anonymous']) ? 1 : 0;

    if ($title === '') $errors[] = "Title required.";
    if ($description === '') $errors[] = "Description required.";

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            $ust = $pdo->prepare("UPDATE complaints SET title=:title, category=:cat, location=:loc, description=:desc, priority=:prio, is_anonymous=:anon, updated_at = NOW() WHERE id=:id");
            $ust->execute([':title'=>$title, ':cat'=>$category, ':loc'=>$location, ':desc'=>$description, ':prio'=>$priority, ':anon'=>$is_anon, ':id'=>$id]);
            $ast = $pdo->prepare("INSERT INTO complaint_audit (complaint_id, user_id, action, note) VALUES (:cid,:uid,'edited',:note)");
            $ast->execute([':cid'=>$id, ':uid'=>$user['id'], ':note'=>'Edited by owner']);
            // handle additional images same as create (append)
            define('CMP_UPLOAD_DIR', __DIR__ . '/uploads/complaints');
            if (!is_dir(CMP_UPLOAD_DIR)) mkdir(CMP_UPLOAD_DIR, 0755, true);
            $dir = CMP_UPLOAD_DIR . '/complaint_' . intval($id);
            if (!is_dir($dir)) mkdir($dir, 0755, true);

            if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
                $existing = $pdo->prepare("SELECT COUNT(*) FROM complaint_images WHERE complaint_id = :cid");
                $existing->execute([':cid'=>$id]);
                $countExisting = (int)$existing->fetchColumn();
                $maxFiles = 6;
                $count = 0;
                for ($i=0; $i<count($_FILES['images']['name']); $i++) {
                    if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if ($countExisting + $count >= $maxFiles) break;
                    $tmp = $_FILES['images']['tmp_name'][$i];
                    $orig = basename($_FILES['images']['name'][$i]);
                    $size = $_FILES['images']['size'][$i];
                    $info = @getimagesize($tmp);
                    if ($info === false) continue;
                    $mime = $info['mime'];
                    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
                    if (!in_array($mime, $allowed, true)) continue;
                    if ($size > (5*1024*1024)) continue;
                    $ext = image_type_to_extension($info[2], false);
                    $ext = ($ext === 'jpeg') ? 'jpg' : $ext;
                    $filename = uniqid('cimg_', true) . '.' . $ext;
                    $dest = $dir . '/' . $filename;
                    if (move_uploaded_file($tmp, $dest)) {
                        $stmtImg = $pdo->prepare("INSERT INTO complaint_images (complaint_id, filename, original_name, mime, size) VALUES (:cid,:fn,:on,:mime,:size)");
                        $stmtImg->execute([':cid'=>$id, ':fn'=>$filename, ':on'=>$orig, ':mime'=>$mime, ':size'=>$size]);
                        $count++;
                    }
                }
            }

            $pdo->commit();
            header("Location: complaint_view.php?id={$id}"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('complaint_edit save error: '.$e->getMessage());
            $errors[] = "Failed to save changes.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/></head>
<link rel="stylesheet" href="<?= htmlspecialchars(root_url('css/style.css')) ?>">
<body>
  <header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
      <a href="complaint_view.php?id=<?= (int)$id ?>" class="btn subtle">Back</a>
      <a class="btn subtle" href="complaint_list.php">Back to list</a>
    </nav>
  </header>

  <main class="container">
    <div class="card">
      <h2>Edit Complaint</h2>
      <?php if (!empty($errors)): ?><div class="alert alert-danger"><ul><?php foreach ($errors as $er) echo '<li>'.htmlspecialchars($er).'</li>'; ?></ul></div><?php endif; ?>

      <form method="post" enctype="multipart/form-data" class="form">
        <label>Title
          <input type="text" name="title" value="<?= htmlspecialchars($_POST['title'] ?? $c['title']) ?>" required>
        </label>

        <label>Category
          <input type="text" name="category" value="<?= htmlspecialchars($_POST['category'] ?? $c['category']) ?>">
        </label>

        <label>Location
          <input type="text" name="location" value="<?= htmlspecialchars($_POST['location'] ?? $c['location']) ?>">
        </label>

        <label>Priority
          <select name="priority">
            <option value="low" <?= (($_POST['priority'] ?? $c['priority'])==='low') ? 'selected' : '' ?>>Low</option>
            <option value="medium" <?= (($_POST['priority'] ?? $c['priority'])==='medium') ? 'selected' : '' ?>>Medium</option>
            <option value="high" <?= (($_POST['priority'] ?? $c['priority'])==='high') ? 'selected' : '' ?>>High</option>
          </select>
        </label>

        <label>Description
          <textarea name="description" rows="6" required><?= htmlspecialchars($_POST['description'] ?? $c['description']) ?></textarea>
        </label>

        <label>Attach additional images (will be appended; max total 6)
          <input type="file" name="images[]" accept="image/*" multiple>
        </label>

        <label class="inline"><input type="checkbox" name="is_anonymous" <?= (isset($_POST['is_anonymous']) || $c['is_anonymous']) ? 'checked' : '' ?>> Submit anonymously</label>

        <div style="margin-top:12px;">
          <button class="btn primary" type="submit">Save changes</button>
          <a class="btn subtle" href="complaint_view.php?id=<?= (int)$id ?>">Cancel</a>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
