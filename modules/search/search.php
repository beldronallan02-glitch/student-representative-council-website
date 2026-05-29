<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../assets/inc/authenticate.php';
require_once __DIR__ . '/search_model.php';

require_login('/MPPCONNECT/login.php');

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q !== '') {
    $results = global_search($pdo, $q);
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Search — <?= htmlspecialchars($q) ?></title>
<link rel="stylesheet" href="../../css/style.css">
</head>
<body>

<header class="topbar">
  <div class="brand"><h1>Search</h1></div>
  <nav class="nav">
    <a class="btn subtle" href="../../index.php">Home</a>
  </nav>
  <form action="search.php" method="get" style="max-width:600px;margin:10px auto 0;display:flex;gap:8px">
    <input type="text" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Search announcements, events, progress" style="flex:1;padding:10px;border:1px solid #d1d5db;border-radius:10px">
    <button class="btn primary" type="submit">Search</button>
    <?php if ($q !== ''): ?><a class="btn subtle" href="search.php">Clear</a><?php endif; ?>
  </form>
</header>

<main class="container">

<h2>Results for “<?= htmlspecialchars($q) ?>”</h2>

<?php if (empty($results)): ?>
  <p class="lead">No results found.</p>
<?php else: ?>
  <div class="card glass">
    <?php foreach ($results as $r): ?>
      <article style="margin-bottom:14px">
        <small class="micro"><?= htmlspecialchars($r['type']) ?></small>
        <h4><?= htmlspecialchars($r['title']) ?></h4>
        <p><?= htmlspecialchars(mb_strimwidth($r['excerpt'] ?? '', 0, 160, '…')) ?></p>
        <a class="link" href="<?= htmlspecialchars($r['url']) ?>">View →</a>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

</main>
</body>
</html>
