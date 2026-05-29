<?php
// announcement_form.php - reusable announcement form partial
// Expects:
//   - $action (string) optional
//   - $csrf  (string) required
//   - $announcement (assoc) optional

$announcement = $announcement ?? [
  'title' => '',
  'excerpt' => '',
  'body' => '',
  'tag' => '',
  'status' => 'draft',
  'publish_at' => '',
  'image' => ''
];

// Form action fallback
$formAction = !empty($action)
  ? $action
  : ($_SERVER['PHP_SELF'] ?? '');

// Escape helper
function e($v) {
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
?>

<form method="post"
      action="<?= e($formAction) ?>"
      enctype="multipart/form-data">

<input type="hidden" name="csrf_token" value="<?= e($csrf ?? '') ?>">

<!-- TITLE -->
<label>Title</label>
<input type="text"
       name="title"
       required
       value="<?= e($announcement['title']) ?>"
       class="input">

<!-- EXCERPT -->
<label>Excerpt (short)</label>
<textarea name="excerpt"
          rows="2"
          class="input"><?= e($announcement['excerpt']) ?></textarea>

<!-- BODY -->
<label>Content</label>
<textarea name="body"
          rows="8"
          required
          class="input"><?= e($announcement['body']) ?></textarea>

<!-- TAG -->
<label>Tag</label>
<input type="text"
       name="tag"
       value="<?= e($announcement['tag']) ?>"
       class="input">

<!-- STATUS -->
<label>Status</label>
<select name="status" class="input">
  <option value="draft" <?= $announcement['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
  <option value="published" <?= $announcement['status'] === 'published' ? 'selected' : '' ?>>Published</option>
  <option value="archived" <?= $announcement['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
</select>

<!-- PUBLISH DATE -->
<label>Publish at (optional)</label>
<input type="datetime-local"
       name="publish_at"
       value="<?= !empty($announcement['publish_at'])
          ? date('Y-m-d\TH:i', strtotime($announcement['publish_at']))
          : '' ?>"
       class="input">

<!-- IMAGE -->
<label>Announcement Image</label>

<?php if (!empty($announcement['image'])): ?>
  <img src="<?= e(root_url('uploads/announcements/' . $announcement['image'])) ?>"
       style="max-width:100%;border-radius:12px;margin-bottom:10px">
<?php endif; ?>

<input type="file"
       name="image"
       accept="image/*"
       class="input">

<!-- ACTIONS -->
<div style="display:flex;gap:12px;margin-top:16px">
  <button class="btn primary" type="submit">Save Announcement</button>
  <a class="btn subtle" href="admin_announcements.php">Cancel</a>
</div>

</form>

<style>
/* Local form polish (matches your global UI) */
.input{
  width:100%;
  padding:12px;
  border-radius:10px;
  border:1px solid rgba(0,0,0,.15);
  margin-bottom:14px;
  font-family:inherit;
}
label{
  font-weight:600;
  margin-bottom:6px;
  display:block;
}
</style>
