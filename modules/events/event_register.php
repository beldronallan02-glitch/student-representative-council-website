<?php
// modules/events/event_register.php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/../../assets/inc/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
ob_start();

$ROOT = '';

/* ===============================
   EVENT VALIDATION
================================ */
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: $ROOT/modules/events/events_public.php");
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, title, capacity, image
    FROM events
    WHERE id = ? AND status = 'published'
    LIMIT 1
");
$stmt->execute([$id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    header("Location: $ROOT/modules/events/events_public.php");
    exit;
}

/* ===============================
   USER PREFILL
================================ */
$user = current_user();
$error = null;

$prefill_name  = $user['name']  ?? '';
$prefill_email = $user['email'] ?? '';

/* ===============================
   HANDLE SUBMISSION
================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {

        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($name === '' || $email === '') {
            $error = 'Name and email are required.';
        }

        // Prevent duplicate
        if (!$error) {
            $chk = $pdo->prepare("
                SELECT 1 FROM event_registrations
                WHERE event_id = ? AND participant_email = ?
                LIMIT 1
            ");
            $chk->execute([$id, $email]);
            if ($chk->fetch()) {
                $error = 'You have already registered for this event.';
            }
        }

        // Capacity check
        if (!$error && !empty($event['capacity'])) {
            $cnt = $pdo->prepare("
                SELECT COUNT(*) FROM event_registrations
                WHERE event_id = ? AND status='registered'
            ");
            $cnt->execute([$id]);
            if ((int)$cnt->fetchColumn() >= (int)$event['capacity']) {
                $error = 'This event is already full.';
            }
        }

        // Insert registration
        if (!$error) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO event_registrations
                    (event_id, user_id, participant_name, participant_email, status)
                    VALUES (?, ?, ?, ?, 'registered')
                ");
                $stmt->execute([
                    $id,
                    $user['id'] ?? null,
                    $name,
                    $email
                ]);

                header("Location: $ROOT/modules/events/event.view.php?id=$id&registered=1");
                exit;

            } catch (PDOException $e) {
    die('DATABASE ERROR: ' . $e->getMessage());
}

            }
        }
    }


$csrf = csrf_token();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"/>
<title>Register — <?= htmlspecialchars($event['title']) ?></title>
<link rel="stylesheet" href="<?= $ROOT ?>/css/style.css">
<style>
    .register-wrap { max-width: 1000px; margin: 20px auto; }
    .grid { display:grid; grid-template-columns: 1fr; gap:18px; }
    @media (min-width: 900px) { .grid { grid-template-columns: 1.1fr 0.9fr; } }
    .panel { background:#fff; border-radius:16px; padding:18px; box-shadow:0 14px 34px rgba(0,0,0,.08); }
    .panel h2, .panel h3 { margin-top:0; }
    .media-frame { position: relative; width: 100%; border-radius: 16px; overflow: hidden; background:#e5e7eb; }
    .media-frame::before { content: ''; display:block; padding-top:56.25%; }
    .media-frame > img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; cursor: zoom-in; }
    label { display:block; font-weight:600; margin-top:12px; }
    input[type="text"], input[type="email"], input[type="password"] { width:100%; padding:12px; border-radius:12px; border:1px solid #d6d9e0; margin-top:6px; }
    .btn.primary { background:#7b68ee; color:#fff; border:none; padding:12px 18px; border-radius:12px; cursor:pointer; }
    .btn.subtle { background:#eef1f8; color:#0b1c33; border:none; padding:12px 18px; border-radius:12px; cursor:pointer; }
    .alert.alert-danger { background:#ffe6e6; border-left:4px solid #ff6b6b; padding:12px; border-radius:10px; margin-bottom:16px; color:#b00020; }

    /* Lightbox overlay */
    .lightbox-overlay { position: fixed; inset:0; background: rgba(0,0,0,0.75); display:none; align-items:center; justify-content:center; z-index:9999; }
    .lightbox-overlay.open { display:flex; }
    .lightbox-content { max-width:90vw; max-height:90vh; }
    .lightbox-content img { width:100%; height:auto; border-radius:12px; }
    .lightbox-close { position:fixed; top:20px; right:20px; background:#fff; color:#111827; border:none; padding:10px 14px; border-radius:10px; cursor:pointer; }
</style>
</head>
<body>

<main class="container register-wrap">
    <div class="grid">
        <section class="panel">
            <h2><?= htmlspecialchars($event['title']) ?></h2>
            <?php if (!empty($event['image'])): ?>
            <div class="media-frame" style="margin-top:10px;">
                <img src="<?= $ROOT ?>/uploads/events/<?= htmlspecialchars($event['image']) ?>" alt="<?= htmlspecialchars($event['title']) ?>" id="eventPosterReg">
            </div>
            <?php endif; ?>
            <p class="micro" style="margin-top:8px;">Confirm your registration details below.</p>
        </section>

        <section class="panel">

<h3>Register for Event</h3>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $id ?>">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">

<label>
Full Name
<input type="text" name="name" required value="<?= htmlspecialchars($prefill_name) ?>">
</label>

<label>
Email Address
<input type="email" name="email" required value="<?= htmlspecialchars($prefill_email) ?>">
</label>

<div style="display:flex;gap:10px;margin-top:18px">
<button class="btn primary">Confirm Registration</button>
<a class="btn subtle" href="<?= $ROOT ?>/modules/events/event.view.php?id=<?= $id ?>">Back</a>
</div>

</form>
        </section>
    </div>
</main>

<!-- Lightbox Modal -->
<div class="lightbox-overlay" id="lightbox">
    <button class="lightbox-close" id="lightboxClose" aria-label="Close image">Close</button>
    <div class="lightbox-content">
        <img src="" alt="Expanded event image" id="lightboxImg">
    </div>
</div>

<script>
(function(){
    var poster = document.getElementById('eventPosterReg');
    var overlay = document.getElementById('lightbox');
    var img = document.getElementById('lightboxImg');
    var closeBtn = document.getElementById('lightboxClose');

    function openLightbox(src) {
        img.src = src;
        overlay.classList.add('open');
    }
    function closeLightbox() {
        overlay.classList.remove('open');
        img.src = '';
    }

    if (poster) {
        poster.addEventListener('click', function(){ openLightbox(poster.src); });
    }
    closeBtn.addEventListener('click', closeLightbox);
    overlay.addEventListener('click', function(e){ if (e.target === overlay) closeLightbox(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') closeLightbox(); });
})();
</script>
</body>
</html>
