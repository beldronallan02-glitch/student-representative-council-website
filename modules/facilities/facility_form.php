<?php
// modules/facilities/facility_form.php
// Expects: $action, $csrf, $facility (assoc or default)
$facility = $facility ?? ['name'=>'','description'=>'','location'=>'','capacity'=>''];
$action = $action ?? htmlspecialchars($_SERVER['PHP_SELF'] . (isset($_GET['id']) ? '?id=' . (int)$_GET['id'] : ''));
?>
<form method="post" action="<?= $action ?>" enctype="multipart/form-data">
  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
  <label>Name<br>
    <input type="text" name="name" required value="<?= htmlspecialchars($facility['name']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef">
  </label><br><br>

  <label>Location<br>
    <input type="text" name="location" value="<?= htmlspecialchars($facility['location']) ?>" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef">
  </label><br><br>

  <label>Capacity (optional)<br>
    <input type="number" name="capacity" min="1" value="<?= htmlspecialchars($facility['capacity']) ?>" style="padding:10px;border-radius:8px;border:1px solid #dfe6ef">
  </label><br><br>

  <label>Description<br>
    <textarea name="description" rows="4" style="width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef"><?= htmlspecialchars($facility['description']) ?></textarea>
  </label><br><br>

  <fieldset style="border:1px solid #dfe6ef;border-radius:8px;padding:12px;margin-bottom:12px">
    <legend style="padding:0 6px;color:#374151">Facility Images</legend>
    <p class="micro" style="margin-top:0;color:#6b7280">Upload one or more images (JPG, PNG, GIF, WEBP). The first image will be shown as the cover. Max 5MB each.</p>
    <input type="file" name="images[]" multiple accept="image/*" style="padding:6px;border-radius:8px;border:1px solid #dfe6ef">
  </fieldset>

  <div style="display:flex;gap:10px">
    <button class="btn primary" type="submit">Save</button>
    <a class="btn subtle" href="facility_manage.php">Cancel</a>
  </div>
</form>
