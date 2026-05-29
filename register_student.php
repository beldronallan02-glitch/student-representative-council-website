<?php
// register_student.php — Student self-registration page
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/assets/inc/authenticate.php';
require_once __DIR__ . '/assets/inc/csrf.php';

// If already logged in, go to dashboard/home
if (current_user()) { header('Location: index.php'); exit; }

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // CSRF check
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Invalid request. Please refresh and try again.';
        } else {
                $name    = trim($_POST['name'] ?? '');
                $email   = trim($_POST['email'] ?? '');
                $matric  = trim($_POST['matric_no'] ?? '');
                $password  = $_POST['password'] ?? '';
                $password2 = $_POST['password2'] ?? '';

                // Basic validation
                if ($name === '' || $email === '' || $password === '') {
                        $error = 'Name, email and password are required.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'Please enter a valid email address.';
                } elseif (strlen($password) < 8) {
                        $error = 'Password must be at least 8 characters.';
                } elseif ($password !== $password2) {
                        $error = 'Passwords do not match.';
                } else {
                        // Ensure email uniqueness (case-insensitive)
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1");
                        $stmt->execute([$email]);
                        if ($stmt->fetch()) {
                                $error = 'Email already registered.';
                        } else {
                                try {
                                        $hash = password_hash($password, PASSWORD_DEFAULT);
                                        $ins = $pdo->prepare(
                                                "INSERT INTO users (matric_no, name, email, password_hash, role, is_active, created_at)
                                                 VALUES (?, ?, ?, ?, 'student', 1, NOW())"
                                        );
                                        $ins->execute([$matric ?: null, $name, $email, $hash]);
                                        $success = 'Account created. You can now sign in.';
                                } catch (Throwable $e) {
                                        error_log('[register_student] insert error: ' . $e->getMessage());
                                        $error = 'Failed to create account. Please try again later.';
                                }
                        }
                }
        }
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Register — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .register-card { max-width: 480px; margin: 60px auto; background:#fff; border-radius:18px; padding:28px; box-shadow:0 20px 40px rgba(0,0,0,0.12); }
        label { display:block; font-weight:600; margin-top:12px; }
        input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:12px; border-radius:12px; border:1px solid #d6d9e0; margin-top:6px; }
        .btn-primary { background:#7b68ee; color:#fff; border:none; padding:12px 18px; border-radius:12px; cursor:pointer; }
        .btn-subtle { background:#eef1f8; color:#0b1c33; border:none; padding:12px 18px; border-radius:12px; cursor:pointer; }
        .error { background:#ffe6e6; border-left:4px solid #ff6b6b; padding:12px; border-radius:10px; margin-bottom:16px; color:#b00020; }
        .success { background:#e6fff0; border-left:4px solid #37b24d; padding:12px; border-radius:10px; margin-bottom:16px; color:#135f1a; }
        .topbar { display:flex; align-items:center; gap:12px; padding:20px; }
        .brand { display:flex; align-items:center; gap:10px; }
        .brand-text h1 { margin:0; font-size:24px; }
        .accent { color:#7b68ee; }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <div class="logo-blob"></div>
            <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
        </div>
    </header>

    <main class="container">
        <div class="register-card">
            <h2>Create your student account</h2>

            <?php if ($error): ?>
                <div class="error"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="success"><?= htmlspecialchars($success) ?></div>
                <p><a class="btn-primary" href="login.php">Go to Login</a></p>
            <?php else: ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

                <label>Full Name
                    <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
                </label>

                <label>Email
                    <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </label>

                <label>Matric No. (optional)
                    <input type="text" name="matric_no" value="<?= htmlspecialchars($_POST['matric_no'] ?? '') ?>">
                </label>

                <label>Password (min 8 chars)
                    <input type="password" name="password" required>
                </label>

                <label>Confirm Password
                    <input type="password" name="password2" required>
                </label>

                <div style="display:flex;gap:10px;margin-top:16px;">
                    <button type="submit" class="btn-primary">Create Account</button>
                    <a class="btn-subtle" href="login.php">Back to Login</a>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
