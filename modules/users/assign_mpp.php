<?php
// modules/users/assign_mpp.php — Admin can promote students to MPP
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

require_role('admin', '/login.php');

$user = current_user();
$error = null;
$notice = null;

// Handle actions: promote or revoke
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
    $action = $_POST['action'] ?? 'promote';
        $targetId = (int)($_POST['user_id'] ?? 0);
        if ($targetId <= 0) {
            $error = 'Invalid user selected.';
        } else {
            try {
        // Load target
                $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$targetId]);
                $target = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$target) {
                    $error = 'User not found.';
        } else if ($action === 'promote') {
          if ($target['role'] !== 'student') {
            $error = 'Only students can be promoted to MPP.';
          } else {
            $upd = $pdo->prepare("UPDATE users SET role = 'mpp', role_label = 'MPP' WHERE id = ?");
            $upd->execute([$targetId]);
            // Ensure an mpp_progress row exists for this new MPP
            try {
              $chk = $pdo->prepare("SELECT mppprogressid FROM mpp_progress WHERE userid = ? LIMIT 1");
              $chk->execute([$targetId]);
              $exists = $chk->fetchColumn();
              if (!$exists) {
                $ins = $pdo->prepare("INSERT INTO mpp_progress (userid, start_date, created_at) VALUES (?, CURDATE(), NOW())");
                $ins->execute([$targetId]);
                $notice = 'Promoted ' . htmlspecialchars($target['name']) . ' to MPP. Initialized MPP progress.';
              } else {
                $notice = 'Promoted ' . htmlspecialchars($target['name']) . ' to MPP.';
              }
            } catch (Throwable $e2) {
              error_log('[assign_mpp] mpp_progress init error: ' . $e2->getMessage());
              $notice = 'Promoted ' . htmlspecialchars($target['name']) . ' to MPP. (Progress init deferred)';
            }
          }
        } else if ($action === 'revoke') {
          if ($target['role'] !== 'mpp') {
            $error = 'Only MPP users can be revoked.';
          } else {
            $upd = $pdo->prepare("UPDATE users SET role = 'student', role_label = 'Student' WHERE id = ?");
            $upd->execute([$targetId]);
            $notice = 'Revoked MPP role from ' . htmlspecialchars($target['name']) . '.';
          }
        } else { $error = 'Invalid action.'; }
            } catch (Throwable $e) {
                error_log('[assign_mpp] error: ' . $e->getMessage());
        $error = 'Action failed. Please try again.';
            }
        }
    }
}

// Load students list
try {
  $qs = trim($_GET['qs'] ?? '');
  if ($qs !== '') {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role='student' AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC");
    $like = "%$qs%";
    $stmt->execute([$like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role='student' ORDER BY created_at DESC");
        $stmt->execute();
    }
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('[assign_mpp] list error: ' . $e->getMessage());
    $students = [];
}

// Load current MPP list
try {
  $qm = trim($_GET['qm'] ?? '');
  if ($qm !== '') {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role='mpp' AND (name LIKE ? OR email LIKE ?) ORDER BY created_at DESC");
    $like = "%$qm%";
    $stmt->execute([$like, $like]);
  } else {
    $stmt = $pdo->prepare("SELECT id, name, email FROM users WHERE role='mpp' ORDER BY created_at DESC");
    $stmt->execute();
  }
  $mpps = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('[assign_mpp] mpp list error: ' . $e->getMessage());
  $mpps = [];
}
$studentsCount = is_array($students) ? count($students) : 0;
$mppsCount = is_array($mpps) ? count($mpps) : 0;

$csrf = csrf_token();
$ROOT = '';
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Assign MPP Role — <?= htmlspecialchars(constant('SITE_NAME') ?? 'MPPConnect') ?></title>
<link rel="stylesheet" href="<?= $ROOT ?>/css/style.css">
<style>
  .page { max-width: 1200px; margin: 20px auto; }
  .page-grid { display:grid; grid-template-columns: 1fr 1fr; gap: 20px; }
  @media (max-width: 900px) { .page-grid { grid-template-columns: 1fr; } }
  .panel { background:#fff; border-radius:16px; padding:18px; box-shadow:0 14px 34px rgba(0,0,0,.08); }
  .panel h3 { margin-top:0; margin-bottom:12px; }
  .panel .meta { color:#6b7280; font-size:13px; margin-bottom:12px; }
  table { width:100%; border-collapse: collapse; }
  th, td { text-align:left; padding:10px; border-bottom:1px solid #e5e7eb; }
  .micro { color:#6b7280; font-size:13px; }
  .error { background:#ffe6e6; border-left:4px solid #ff6b6b; padding:12px; border-radius:10px; margin-bottom:16px; color:#b00020; }
  .notice { background:#e6fff0; border-left:4px solid #37b24d; padding:12px; border-radius:10px; margin-bottom:16px; color:#135f1a; }
  .btn.small { padding:8px 12px; border-radius:10px; }
  .btn.danger { background:#ff6b6b; color:#fff; }
  .actions { display:flex; gap:8px; }
  .searchbar { display:flex; gap:8px; margin-bottom:12px; }
  .searchbar input { flex:1; padding:10px; border-radius:10px; border:1px solid #dfe6ef; }
  .table-wrap { max-height: 60vh; overflow:auto; border:1px solid #eef1f4; border-radius:12px; }
</style>
</head>
<body>
<header class="topbar">
  <div class="brand">
    <div class="logo-blob"></div>
    <div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div>
  </div>
  <nav class="nav">
    <a href="<?= $ROOT ?>/index.php" class="btn subtle">Home</a>
    <a href="<?= $ROOT ?>/logout.php" class="btn primary">Sign Out</a>
  </nav>
</header>

<main class="container page">
  <h2>Assign MPP Role</h2>
  <p class="micro">Admin-only: promote student accounts to MPP and revoke when needed.</p>

  <?php if ($error): ?><div class="error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
  <?php if ($notice): ?><div class="notice"><?= $notice ?></div><?php endif; ?>

  <div class="page-grid">
    <!-- LEFT: STUDENTS -->
    <section class="panel">
      <h3>Students <span class="micro">(<?= (int)$studentsCount ?>)</span></h3>
      <div class="meta">All registered users with Student role.</div>
      <form class="searchbar" method="get" action="">
        <input type="hidden" name="qm" value="<?= htmlspecialchars($_GET['qm'] ?? '') ?>"/>
        <input type="search" name="qs" placeholder="Search students by name or email" value="<?= htmlspecialchars($_GET['qs'] ?? '') ?>"/>
        <button class="btn subtle" type="submit">Search</button>
      </form>
      <?php if (!$students): ?>
        <p class="micro">No student accounts found.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Name</th><th>Email</th><th>Action</th></tr>
          </thead>
          <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td><?= htmlspecialchars($s['name']) ?></td>
              <td><?= htmlspecialchars($s['email']) ?></td>
              <td>
                <form method="post" class="actions" onsubmit="return confirm('Promote this user to MPP?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$s['id'] ?>">
                  <input type="hidden" name="action" value="promote">
                  <button class="btn small primary" type="submit">Promote</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>

    <!-- RIGHT: MPPS -->
    <section class="panel">
      <h3>Current MPPs <span class="micro">(<?= (int)$mppsCount ?>)</span></h3>
      <div class="meta">Students who have been promoted to MPP.</div>
      <form class="searchbar" method="get" action="">
        <input type="hidden" name="qs" value="<?= htmlspecialchars($_GET['qs'] ?? '') ?>"/>
        <input type="search" name="qm" placeholder="Search MPPs by name or email" value="<?= htmlspecialchars($_GET['qm'] ?? '') ?>"/>
        <button class="btn subtle" type="submit">Search</button>
      </form>
      <?php if (!$mpps): ?>
        <p class="micro">No MPP users found.</p>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr><th>Name</th><th>Email</th><th>Action</th></tr>
          </thead>
          <tbody>
          <?php foreach ($mpps as $u): ?>
            <tr>
              <td><?= htmlspecialchars($u['name']) ?></td>
              <td><?= htmlspecialchars($u['email']) ?></td>
              <td>
                <form method="post" class="actions" onsubmit="return confirm('Revoke MPP role for this user?');">
                  <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                  <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                  <input type="hidden" name="action" value="revoke">
                  <button class="btn small danger" type="submit">Revoke</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </section>
  </div>
</main>
</body>
</html>
