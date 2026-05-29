<?php
// modules/progress/progress_manage.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /MPPCONNECT/login.php');
    exit;
}

/* ===============================
   LOAD PROGRESS ENTRIES
================================ */
try {
    if ($user['role'] === 'admin') {
        $stmt = $pdo->prepare("
            SELECT 
                p.*, 
                u.name AS author,
                (
                    SELECT filename 
                    FROM progress_images 
                    WHERE progress_id = p.id 
                    ORDER BY id ASC 
                    LIMIT 1
                ) AS thumb
            FROM progress_entries p
            LEFT JOIN users u ON u.id = p.user_id
            ORDER BY p.created_at DESC
        ");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                p.*, 
                u.name AS author,
                (
                    SELECT filename 
                    FROM progress_images 
                    WHERE progress_id = p.id 
                    ORDER BY id ASC 
                    LIMIT 1
                ) AS thumb
            FROM progress_entries p
            LEFT JOIN users u ON u.id = p.user_id
            WHERE p.user_id = ?
            ORDER BY p.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    }
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log('progress_manage error: '.$e->getMessage());
    $rows = [];
}
/* ===============================
   LOAD PROGRESS LOGS (NEW SCHEMA)
================================ */
try {
  if ($user['role'] === 'admin') {
    $stmt = $pdo->prepare("
      SELECT 
        l.logid, l.title, l.description, l.status, l.remarks, l.logged_at,
        u.name AS author,
        (
          SELECT imgpath FROM progress_log_images i
          WHERE i.logid = l.logid
          ORDER BY i.imageid ASC
          LIMIT 1
        ) AS thumb
      FROM progress_log l
      JOIN mpp_progress mp ON mp.mppprogressid = l.mppprogressid
      JOIN users u ON u.id = mp.userid
      WHERE (l.remarks IS NULL OR l.remarks = '')
      ORDER BY l.logged_at DESC
    ");
    $stmt->execute();
  } else {
    $stmt = $pdo->prepare("
      SELECT 
        l.logid, l.title, l.description, l.status, l.remarks, l.logged_at,
        u.name AS author,
        (
          SELECT imgpath FROM progress_log_images i
          WHERE i.logid = l.logid
          ORDER BY i.imageid ASC
          LIMIT 1
        ) AS thumb
      FROM progress_log l
      JOIN mpp_progress mp ON mp.mppprogressid = l.mppprogressid
      JOIN users u ON u.id = mp.userid
      WHERE mp.userid = ? AND (l.remarks IS NULL OR l.remarks = '')
      ORDER BY l.logged_at DESC
    ");
    $stmt->execute([$user['id']]);
  }
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  error_log('progress_manage (new schema) error: '.$e->getMessage());
  $rows = [];
}

$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Progress & Impact — Manage</title>

<link rel="stylesheet" href="../../css/style.css">

<style>
.manage-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px,1fr));
  gap: 22px;
  margin-top: 20px;
}

.manage-card {
  background: rgba(255,255,255,0.9);
  border-radius: 18px;
  padding: 14px;
  box-shadow: 0 14px 34px rgba(0,0,0,0.12);
  display: flex;
  flex-direction: column;
}

.manage-thumb {
  width: 100%;
  height: 160px;
  object-fit: cover;
  border-radius: 12px;
  background: #e5e7eb;
}

.manage-title {
  font-size: 17px;
  font-weight: 800;
  margin: 10px 0 4px;
}

.manage-meta {
  font-size: 13px;
  color: #6b7280;
  margin-bottom: 8px;
}

.badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 700;
}

.badge.draft {
  background: #fef3c7;
  color: #92400e;
}

.badge.published {
  background: #dcfce7;
  color: #166534;
}
.badge.published {
  background: #dcfce7;
  color: #166534;
}
/* new status styles */
.badge.planned { background: #e0f2fe; color: #075985; }
.badge.in-progress { background: #fef3c7; color: #92400e; }
.badge.completed { background: #dcfce7; color: #166534; }
.badge.delayed { background: #fee2e2; color: #b91c1c; }

.manage-actions {
  margin-top: auto;
  display: flex;
  justify-content: space-between;
  gap: 8px;
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
    <a class="btn subtle" href="../../index.php">Home</a>
  </nav>
</header>

<main class="container">

  <div class="card">

    <div style="display:flex;justify-content:space-between;align-items:center">
      <h2>Progress & Impact — Manage</h2>
      <a class="btn primary" href="progress_form.php?action=create">+ New Post</a>
    </div>

    <?php if (empty($rows)): ?>
      <p class="lead" style="margin-top:14px">
        No posts yet. Click <strong>New Post</strong> to share your progress and impact.
      </p>
    <?php else: ?>

      <div class="manage-grid">

        <?php foreach ($rows as $r):
          $thumb = !empty($r['thumb'])
          ? '../../' . $r['thumb']
            : '../../uploads/progress/placeholder.png';
        ?>

          <article class="manage-card">

            <img
              src="<?= htmlspecialchars($thumb) ?>"
              alt="<?= htmlspecialchars($r['title']) ?>"
              class="manage-thumb"
            >

            <div class="manage-title">
              <?= htmlspecialchars($r['title']) ?>
            </div>

            <div class="manage-meta">
              By <?= htmlspecialchars($r['author'] ?? $user['name']) ?>
              · <?= htmlspecialchars(date('M j, Y', strtotime($r['logged_at']))) ?>
            </div>

            <?php
              $statusClass = 'planned';
              switch ($r['status']) {
                case 'in_progress': $statusClass = 'in-progress'; break;
                case 'completed': $statusClass = 'completed'; break;
                case 'delayed': $statusClass = 'delayed'; break;
                case 'planned': default: $statusClass = 'planned';
              }
            ?>
            <span class="badge <?= $statusClass ?>">
              <?= htmlspecialchars(str_replace('_',' ', ucfirst($r['status']))) ?>
            </span>

            <div class="manage-actions">
              <div>
                <a class="btn subtle" href="progress_view.php?logid=<?= (int)$r['logid'] ?>">View</a>
                <a class="btn subtle" href="progress_form.php?action=edit&logid=<?= (int)$r['logid'] ?>">Edit</a>
              </div>

              <form
                method="post"
                action="progress_action.php"
                onsubmit="return confirm('Delete this post and all its images?');"
              >
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
                <input type="hidden" name="logid" value="<?= (int)$r['logid'] ?>">
                <input type="hidden" name="action" value="delete">
                <button class="btn ghost" type="submit">Delete</button>
              </form>
            </div>

          </article>

        <?php endforeach; ?>

      </div>

    <?php endif; ?>

  </div>

</main>

</body>
</html>
