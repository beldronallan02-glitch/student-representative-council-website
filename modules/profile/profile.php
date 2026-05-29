<?php
// profile.php - User Profile & Personalisation Module (FINAL & FIXED)

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';

// Protect page
require_login('../../login.php');

// Use relative path instead of hardcoded ROOT
$user = current_user();

$success = '';
$error = '';

// Detect available user table columns for optional fields
$availableCols = [];
try {
  $colsStmt = $pdo->query("SHOW COLUMNS FROM users");
  foreach ($colsStmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    $availableCols[] = $c['Field'];
  }
} catch (Throwable $e) {
  $availableCols = [];
}
function has_col($name, $availableCols) { return in_array($name, $availableCols, true); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $role_label = trim($_POST['role_label'] ?? '');
    $profileImageName = $user['profile_image'] ?? null;
  $email = trim($_POST['email'] ?? ($user['email'] ?? ''));
  $phone = trim($_POST['phone'] ?? ($user['phone'] ?? ''));
  $student_id = trim($_POST['student_id'] ?? ($user['student_id'] ?? ''));
  $department = trim($_POST['department'] ?? ($user['department'] ?? ''));
  $program = trim($_POST['program'] ?? ($user['program'] ?? ''));
  $year = trim($_POST['year'] ?? ($user['year'] ?? ''));
  $bio = trim($_POST['bio'] ?? ($user['bio'] ?? ''));

    if ($name === '' || $role_label === '') {
        $error = 'Name and profile title cannot be empty.';
    }

    // Handle profile image upload
    if ($error === '' && !empty($_FILES['profile_image']['name'])) {

        // File size check (max 2MB)
        if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
            $error = 'Image size must be less than 2MB.';
        } else {
            $allowed = ['jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));

            if (!in_array($ext, $allowed)) {
                $error = 'Only JPG, PNG or WEBP images are allowed.';
            } else {
                $profileImageName = 'user_' . $user['id'] . '.' . $ext;
                $uploadDir = __DIR__ . '/../../uploads/profiles/';
                $uploadPath = $uploadDir . $profileImageName;

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }

                move_uploaded_file($_FILES['profile_image']['tmp_name'], $uploadPath);
                // Remove older profile images with different extensions for this user to avoid stale references
                foreach ($allowed as $otherExt) {
                  if ($otherExt !== $ext) {
                    $old = $uploadDir . 'user_' . $user['id'] . '.' . $otherExt;
                    if (is_file($old)) {
                      @unlink($old);
                    }
                  }
                }
            }
        }
    }

    // Update database
    if ($error === '') {
        try {
        $setParts = [ 'name = ?', 'role_label = ?', 'profile_image = ?' ];
        $params = [ $name, $role_label, $profileImageName ];

        if (has_col('email', $availableCols)) {
          if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address.');
          }
          $setParts[] = 'email = ?';
          $params[] = $email;
        }
        if (has_col('phone', $availableCols)) { $setParts[] = 'phone = ?'; $params[] = $phone; }
        if (has_col('student_id', $availableCols)) { $setParts[] = 'student_id = ?'; $params[] = $student_id; }
        if (has_col('department', $availableCols)) { $setParts[] = 'department = ?'; $params[] = $department; }
        if (has_col('program', $availableCols)) { $setParts[] = 'program = ?'; $params[] = $program; }
        if (has_col('year', $availableCols)) { $setParts[] = 'year = ?'; $params[] = $year; }
        if (has_col('bio', $availableCols)) { $setParts[] = 'bio = ?'; $params[] = $bio; }

        $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ' WHERE id = ?';
        $params[] = $user['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

            // Update session (CRITICAL)
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['role_label'] = $role_label;
            $_SESSION['user']['profile_image'] = $profileImageName;
        if (has_col('email', $availableCols)) { $_SESSION['user']['email'] = $email; }
        if (has_col('phone', $availableCols)) { $_SESSION['user']['phone'] = $phone; }
        if (has_col('student_id', $availableCols)) { $_SESSION['user']['student_id'] = $student_id; }
        if (has_col('department', $availableCols)) { $_SESSION['user']['department'] = $department; }
        if (has_col('program', $availableCols)) { $_SESSION['user']['program'] = $program; }
        if (has_col('year', $availableCols)) { $_SESSION['user']['year'] = $year; }
        if (has_col('bio', $availableCols)) { $_SESSION['user']['bio'] = $bio; }

            // Refresh local user variable
            $user = current_user();

            $success = 'Profile updated successfully.';
        } catch (Exception $e) {
            $error = 'Failed to update profile.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>My Profile — MPPConnect</title>
<link rel="stylesheet" href="../../css/style.css">

<style>
.profile-wrapper{display:grid;grid-template-columns:280px 1fr;gap:24px}
.profile-sidebar{text-align:center;padding:24px}
.avatar-lg{width:110px;height:110px;border-radius:50%;object-fit:cover;background:#333;margin:0 auto 12px}
.profile-menu a{display:block;padding:10px;border-radius:10px;background:rgba(255,255,255,.04);margin-bottom:8px;text-decoration:none}
.profile-menu a.active{background:rgba(255,255,255,.1);color:var(--accent-1)}
.profile-content{padding:28px}
.alert{padding:10px 14px;border-radius:10px;margin-bottom:16px}
.alert.success{background:rgba(34,197,94,.15);color:#22c55e}
.alert.error{background:rgba(239,68,68,.15);color:#ef4444}
@media(max-width:900px){.profile-wrapper{grid-template-columns:1fr}}

/* Vertical one-column form */
.form-stack{display:flex;flex-direction:column;gap:14px}
.form-stack label{font-weight:600}
.form-stack input,.form-stack textarea,.form-stack select{width:100%;padding:10px;border-radius:8px;border:1px solid #dfe6ef}
</style>
</head>

<body>

<header class="topbar">
  <!-- FIXED BACK BUTTON -->
  <a href="../../index.php" class="btn subtle">← Back</a>
</header>

<main class="container">
<section class="card glass profile-wrapper">

  <!-- SIDEBAR -->
  <aside class="profile-sidebar">
    <?php
      $avatarFs = __DIR__ . '/../../uploads/profiles/' . ($user['profile_image'] ?? '');
      $avatarSrc = (empty($user['profile_image']) || !is_file($avatarFs))
        ? '../../assets/img/default-avatar.png'
        : '../../uploads/profiles/' . htmlspecialchars($user['profile_image']);
    ?>
    <img class="avatar-lg" src="<?= $avatarSrc ?>">

    <h3><?= htmlspecialchars($user['name']) ?></h3>
    <p class="muted"><?= htmlspecialchars($user['role_label'] ?? 'Student') ?></p>

    <div class="profile-menu">
      <a class="active">Profile Settings</a>
      <a style="opacity:.5">Security (Coming Soon)</a>
    </div>
  </aside>

  <!-- CONTENT -->
  <div class="profile-content">
    <h2>Edit Profile</h2>
    <p class="lead">Your profile details are visible across the entire system.</p>

    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-stack">

      <div>
        <label>Full Name</label>
        <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" required>
      </div>

      <div>
        <label>Profile Title (e.g. Dev Student)</label>
        <input type="text" name="role_label" value="<?= htmlspecialchars($user['role_label'] ?? 'Student') ?>" required>
      </div>

      <?php if (has_col('email', $availableCols)): ?>
      <div>
        <label>Email</label>
        <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
      </div>
      <?php endif; ?>

      <?php if (has_col('phone', $availableCols)): ?>
      <div>
        <label>Phone</label>
        <input type="text" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if (has_col('student_id', $availableCols)): ?>
      <div>
        <label>Student ID</label>
        <input type="text" name="student_id" value="<?= htmlspecialchars($user['student_id'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if (has_col('department', $availableCols)): ?>
      <div>
        <label>Department</label>
        <input type="text" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if (has_col('program', $availableCols)): ?>
      <div>
        <label>Program</label>
        <input type="text" name="program" value="<?= htmlspecialchars($user['program'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if (has_col('year', $availableCols)): ?>
      <div>
        <label>Year of Study</label>
        <input type="text" name="year" value="<?= htmlspecialchars($user['year'] ?? '') ?>">
      </div>
      <?php endif; ?>

      <?php if (has_col('bio', $availableCols)): ?>
      <div>
        <label>Bio</label>
        <textarea name="bio" rows="4"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
      </div>
      <?php endif; ?>

      <div>
        <label>Profile Picture</label>
        <input type="file" name="profile_image" accept="image/*">
      </div>

      <div style="margin-top:20px">
        <button class="btn primary">Save Changes</button>
      </div>

    </form>
  </div>

</section>
</main>

</body>
</html>
