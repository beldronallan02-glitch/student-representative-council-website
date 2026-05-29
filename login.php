<?php
// login.php - professional full-screen role-first login page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/assets/inc/authenticate.php';

// Redirect if already logged in
if (current_user()) {
    header('Location: index.php');
    exit;
}

$error = null;
$allowed_roles = ['student', 'mpp', 'admin'];
$default_redirect = 'index.php';
$requestedRedirect = $_GET['redirect'] ?? $default_redirect;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $role = strtolower(trim($_POST['role'] ?? ''));
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirectTarget = basename($_POST['redirect'] ?? $default_redirect);

    if ($role === '' || !in_array($role, $allowed_roles, true)) {
        $error = "Please select a role.";
    } elseif ($email === '' || $password === '') {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, name, email, password_hash, role, is_active
             FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            $error = "Invalid credentials.";
        } elseif (!$user['is_active']) {
            $error = "Account disabled. Contact administrator.";
        } elseif (strtolower($user['role']) !== $role) {
            $error = "Selected role does not match your account.";
        } else {
            auth_login($user);
            handle_post_login_redirect();

            $allowed = [
                'index.php',
                'dashboard.php',
                'admin_announcements.php',
                'register_event.php',
                'register_student.php',
                'feedback.php'
            ];

            header('Location: ' . (in_array($redirectTarget, $allowed, true)
                ? $redirectTarget
                : $default_redirect));
            exit;
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>

<link rel="stylesheet" href="css/style.css">

<style>
/* ===============================
   GLOBAL
================================ */
/* Global background handles this now */
/* body block removed */

/* ===============================
   PAGE LAYOUT
================================ */
.page {
  min-height: 100vh;
  display: grid;
  grid-template-columns: 1.2fr 1fr;
}

@media (max-width: 900px) {
  .page {
    grid-template-columns: 1fr;
  }
}

/* ===============================
   LEFT INFO PANEL
================================ */
.info {
  padding: 80px 70px;
  display: flex;
  flex-direction: column;
  justify-content: center;
  background: rgba(255,255,255,0.55);
  backdrop-filter: blur(10px);
}

.info h1 {
  font-size: 42px;
  font-weight: 800;
  margin-bottom: 12px;
  color: #0b1c33;
}

.info p {
  font-size: 16px;
  max-width: 520px;
  line-height: 1.6;
  color: #2f3542;
}

.info ul {
  margin-top: 24px;
  padding-left: 20px;
  font-size: 16px;
}

.info li {
  margin-bottom: 12px;
}

/* ===============================
   RIGHT LOGIN PANEL
================================ */
.form-panel {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 60px;
}

.login-card {
  width: 100%;
  max-width: 420px;
  background: #ffffff;
  border-radius: 18px;
  padding: 36px;
  box-shadow: 0 20px 40px rgba(0,0,0,0.12);
}

h3 {
  margin-top: 0;
  margin-bottom: 16px;
  font-weight: 700;
}

/* ===============================
   ROLE BUTTONS
================================ */
.role-grid {
  display: flex;
  gap: 10px;
}

.role-btn {
  flex: 1;
  padding: 14px;
  border-radius: 12px;
  border: 1px solid #d6d9e0;
  background: #f9fafc;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.25s ease;
}

.role-btn:hover {
  background: #eef1f8;
}

.role-btn.selected {
  background: #7b68ee;
  color: #fff;
  box-shadow: 0 6px 16px rgba(123,104,238,0.35);
  border-color: transparent;
}

/* ===============================
   FORM ELEMENTS
================================ */
label {
  display: block;
  font-size: 14px;
  margin-bottom: 6px;
  font-weight: 600;
}

input[type="email"],
input[type="password"] {
  width: 100%;
  padding: 14px;
  font-size: 15px;
  border-radius: 12px;
  border: 1px solid #ccd2dd;
  margin-bottom: 14px;
}

input:focus {
  outline: none;
  border-color: #7b68ee;
  box-shadow: 0 0 0 2px rgba(123,104,238,0.15);
}

/* ===============================
   BUTTONS
================================ */
.btn-primary {
  background: #7b68ee;
  color: #fff;
  border: none;
  padding: 12px 20px;
  border-radius: 12px;
  font-weight: 600;
  cursor: pointer;
}

.btn-primary:hover {
  background: #6957db;
}

.btn-subtle {
  background: #f0f2f6;
  border: 1px solid #d0d4dd;
  padding: 12px 20px;
  border-radius: 12px;
  cursor: pointer;
}

/* ===============================
   ERROR MESSAGE
================================ */
.error {
  background: #ffe6e6;
  border-left: 4px solid #ff6b6b;
  padding: 12px;
  border-radius: 10px;
  margin-bottom: 16px;
  color: #b00020;
}
</style>
</head>

<body>
<main class="page">

  <!-- LEFT INFORMATION -->
  <section class="info">
    <h1>Welcome to <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></h1>
    <p>
      Centralized announcements, event registration, feedback and complaints
      platform for Universiti Malaysia Sabah students.
    </p>
    <ul>
      <li>📢 Verified MPP announcements</li>
      <li>🎓 Event registration & tracking</li>
      <li>💬 Feedback and complaints system</li>
      <li>🏛️ Facility & service coordination</li>
    </ul>
  </section>

  <!-- RIGHT LOGIN -->
  <section class="form-panel">
    <div class="login-card">

      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <!-- ROLE SELECTION -->
      <div id="roleStep">
        <h3>Select your role</h3>
        <div class="role-grid">
          <button type="button" class="role-btn" data-role="student">Student</button>
          <button type="button" class="role-btn" data-role="mpp">MPP</button>
          <button type="button" class="role-btn" data-role="admin">Admin</button>
        </div>
      </div>

      <!-- LOGIN FORM -->
      <form id="credsForm" method="post" style="display:none;margin-top:20px" novalidate>
        <input type="hidden" name="role" id="roleInput" value="<?= htmlspecialchars($_POST['role'] ?? '') ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($requestedRedirect) ?>">

        <label>Email</label>
        <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">

        <label>Password</label>
        <input type="password" name="password" required>

        <div style="display:flex;gap:10px;margin-top:14px;">
          <button type="submit" class="btn-primary">Sign in</button>
          <button type="button" id="backRole" class="btn-subtle">Change role</button>
        </div>

        <p style="margin-top:14px;font-size:14px;">
          Don’t have an account?
          <a href="register_student.php" style="color:#7b68ee;text-decoration:none;">Register</a>
        </p>
      </form>

    </div>
  </section>

</main>

<script>
(function(){
  const roleBtns = document.querySelectorAll('.role-btn');
  const roleInput = document.getElementById('roleInput');
  const roleStep = document.getElementById('roleStep');
  const credsForm = document.getElementById('credsForm');
  const backRole = document.getElementById('backRole');

  const preRole = "<?= htmlspecialchars($_POST['role'] ?? '') ?>";
  if (preRole) {
    const btn = [...roleBtns].find(b => b.dataset.role === preRole);
    if (btn) selectRole(btn);
  }

  function clear() {
    roleBtns.forEach(b => b.classList.remove('selected'));
  }

  function selectRole(btn) {
    clear();
    btn.classList.add('selected');
    roleInput.value = btn.dataset.role;
    roleStep.style.display = 'none';
    credsForm.style.display = 'block';
  }

  roleBtns.forEach(btn => btn.addEventListener('click', () => selectRole(btn)));
  backRole.addEventListener('click', () => {
    credsForm.style.display = 'none';
    roleStep.style.display = 'block';
    roleInput.value = '';
    clear();
  });
})();
</script>

</body>
</html>
