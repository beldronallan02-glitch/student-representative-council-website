<?php
// modules/events/event_form.php
// Expects: $action (string, optional), $csrf (string), $event (assoc or null)

$event = $event ?? [
  'title' => '',
  'excerpt' => '',
  'description' => '',
  'location' => '',
  'capacity' => '',
  'start_at' => '',
  'end_at' => '',
  'status' => 'draft',
  'image' => null
];

$action = $action ?? htmlspecialchars(
  $_SERVER['PHP_SELF'] . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : '')
);
?>

<form method="post" action="<?= $action ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

  <!-- TITLE -->
  <label>Title<br>
    <input type="text" name="title" required
      value="<?= htmlspecialchars($event['title']) ?>"
      style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef">
  </label><br><br>

  <!-- EXCERPT -->
  <label>Excerpt<br>
    <textarea name="excerpt" rows="2"
      style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"><?= htmlspecialchars($event['excerpt']) ?></textarea>
  </label><br><br>

  <!-- DESCRIPTION -->
  <label>Description<br>
    <textarea name="description" rows="6" required
      style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"><?= htmlspecialchars($event['description']) ?></textarea>
  </label><br><br>

  <!-- IMAGE UPLOAD -->
  <label>Event Poster (optional)</label><br>
  <?php if (!empty($event['image'])): ?>
    <div style="margin-bottom:10px">
      <img
        src="../../uploads/events/<?= htmlspecialchars($event['image']) ?>"
        alt="Event Poster"
        style="max-width:100%;height:200px;object-fit:cover;border-radius:10px;border:1px solid #e5e7eb">
    </div>
  <?php endif; ?>

  <input type="file" name="image" accept="image/*"
    style="margin-bottom:20px">
  <small style="color:#6b7280;display:block;margin-bottom:20px">
    Recommended size: 1200×600px (JPG/PNG)
  </small>

  <!-- LOCATION & CAPACITY -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <label>Location<br>
      <input type="text" name="location"
        value="<?= htmlspecialchars($event['location']) ?>"
        style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
    </label>

    <label>Capacity (optional)<br>
      <input type="number" min="1" name="capacity"
        value="<?= htmlspecialchars($event['capacity']) ?>"
        style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
    </label>
  </div>
  <br>

  <!-- DATE & TIME -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
    <label>Start (YYYY-mm-dd HH:ii:ss)<br>
      <input type="text" name="start_at" required
        value="<?= htmlspecialchars($event['start_at']) ?>"
        style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
    </label>

    <label>End (optional)<br>
      <input type="text" name="end_at"
        value="<?= htmlspecialchars($event['end_at']) ?>"
        style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
    </label>
  </div>
  <br>

  <!-- STATUS -->
  <label>Status<br>
    <select name="status"
      style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
      <option value="draft" <?= ($event['status'] === 'draft') ? 'selected' : '' ?>>Draft</option>
      <option value="published" <?= ($event['status'] === 'published') ? 'selected' : '' ?>>Published</option>
      <option value="cancelled" <?= ($event['status'] === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
    </select>
  </label>
  <br><br>

  <!-- ACTIONS -->
  <div style="display:flex;gap:10px">
    <button class="btn primary" type="submit">Save event</button>
    <a class="btn subtle" href="admin_events.php">Cancel</a>
  </div>
</form>
