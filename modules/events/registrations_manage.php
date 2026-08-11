<?php
// modules/events/registrations_manage.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

$user = current_user();
if (!$user || !in_array($user['role'], ['mpp','admin'], true)) {
    header('Location: /login.php');
    exit;
}

$event_id = (int)($_GET['event_id'] ?? 0);
if ($event_id <= 0) {
    header('Location: admin_events.php');
    exit;
}

$stmt = $pdo->prepare("SELECT id, title FROM events WHERE id=?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header('Location: admin_events.php');
    exit;
}

/* Cancel registration */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) die('Invalid CSRF');
    $rid = (int)$_POST['rid'];
    $pdo->prepare("
        UPDATE event_registrations
        SET status='cancelled'
        WHERE id=?
    ")->execute([$rid]);
    header("Location: registrations_manage.php?event_id=$event_id");
    exit;
}

/* Fetch registrations */
$stmt = $pdo->prepare("
    SELECT participant_name, participant_email, status, created_at, id
    FROM event_registrations
    WHERE event_id=?
    ORDER BY created_at DESC
");
$stmt->execute([$event_id]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Registrations — <?= htmlspecialchars($event['title']) ?></title>
<link rel="stylesheet" href="../../css/style.css">
</head>
<body>

<header class="topbar">
    <div class="brand"><div class="logo-blob"></div><div class="brand-text"><h1>MPP<span class="accent">Connect</span></h1></div></div>
    <nav class="nav">
        <a class="btn subtle" href="../../index.php">Home</a>
        <a class="btn subtle" href="admin_events.php">Events</a>
        
    </nav>
    </header>

<main class="container">
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;flex-wrap:wrap;">
            <h2 style="margin:0">Registrations — <?= htmlspecialchars($event['title']) ?></h2>
            <div style="display:flex;gap:8px;align-items:center;">
                <a class="btn subtle" href="admin_events.php">Back to Events</a>
                <a class="btn primary" href="../../index.php">Go Home</a>
            </div>
        </div>

        <?php if (!$rows): ?>
            <p class="micro" style="margin-top:10px">No registrations yet.</p>
        <?php else: ?>
            <style>
                .table-card { background:#fff; border-radius:14px; box-shadow:0 10px 28px rgba(0,0,0,.08); overflow:hidden; margin-top:12px; }
                .reg-table { width:100%; border-collapse:separate; border-spacing:0; }
                .reg-table th, .reg-table td { padding:12px 14px; border-top:1px solid #eef1f4; text-align:left; }
                .reg-table thead th { background:#f8fafc; color:#374151; font-weight:700; border-top:none; font-size:13px; }
                .reg-table tbody tr:hover { background:#f9fbff; }
                .status-pill { padding:6px 10px; border-radius:999px; font-weight:700; font-size:12px; display:inline-block; }
                .status-registered { background:#dcfce7; color:#166534; }
                .status-cancelled { background:#fee2e2; color:#b91c1c; }
                .actions { display:flex; gap:8px; justify-content:flex-end; align-items:center; }
            </style>
            <div class="table-card">
                <table class="reg-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Registered At</th>
                            <th style="text-align:right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <?php
                            $st = strtolower($r['status'] ?? 'registered');
                            $cls = 'status-pill status-' . ($st === 'registered' ? 'registered' : 'cancelled');
                            $when = '';
                            try { $dt = new DateTime($r['created_at']); $when = $dt->format('j F Y, g:i A'); } catch (Exception $e) { $when = htmlspecialchars($r['created_at']); }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($r['participant_name']) ?></td>
                            <td><?= htmlspecialchars($r['participant_email']) ?></td>
                            <td><span class="<?= htmlspecialchars($cls) ?>"><?= htmlspecialchars(ucfirst($st)) ?></span></td>
                            <td><?= $when ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($r['status'] === 'registered'): ?>
                                        <form method="post" onsubmit="return confirm('Cancel registration?');" style="display:inline">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                            <input type="hidden" name="rid" value="<?= (int)$r['id'] ?>">
                                            <input type="hidden" name="action" value="cancel">
                                            <button class="btn ghost" type="submit">Cancel</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="micro">No actions</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</main>
</body>
</html>
