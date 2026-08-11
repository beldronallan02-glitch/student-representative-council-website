<?php
// dashboard.php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/assets/inc/authenticate.php';

// ensure logged in
require_login('/login.php');

$user = current_user();
?>
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Dashboard — <?= htmlspecialchars($user['name']) ?></title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
  <header class="topbar">
    <div class="brand">
      <div class="logo-blob"></div>
      <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
    </div>
    <nav class="nav">
      <a class="btn subtle" href="/index.php">Home</a>
      <?php if ($user['role'] === 'admin' || $user['role'] === 'mpp'): ?>
        <a class="btn" href="/modules/announcements/admin_announcements.php">Manage Announcements</a>
      <?php endif; ?>
      <a class="btn ghost" href="/logout.php">Sign Out</a>
    </nav>
  </header>

  <main class="container">
    <div class="card">
      <h2>Welcome, <?= htmlspecialchars($user['name']) ?></h2>
      <p>Your role: <strong><?= htmlspecialchars($user['role']) ?></strong></p>

      <?php if ($user['role'] === 'admin'): ?>
        <p><a href="/modules/announcements/admin_announcements.php" class="btn">Admin: Manage Announcements</a></p>
      <?php endif; ?>

      <section>
        <h3>Your quick actions</h3>
        <ul>
          <li><a href="/modules/profile/profile.php">Edit profile</a></li>
          <li><a href="/modules/events/events_public.php">View events</a></li>
        </ul>
      </section>
    </div>
  </main>
</body>
</html>
