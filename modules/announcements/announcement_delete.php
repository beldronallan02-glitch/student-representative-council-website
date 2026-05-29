<?php
// announcement_delete.php - soft-delete (archive) an announcement (robust includes)

// Path to project root (filesystem)
$PROJECT_ROOT = dirname(__DIR__, 2);

// include project-wide config and helpers
require_once $PROJECT_ROOT . '/config.php';
require_once $PROJECT_ROOT . '/assets/inc/authenticate.php';
require_once $PROJECT_ROOT . '/assets/inc/csrf.php';

// Build web-root path (URL) relative to document root so redirects/links work.
$docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'] ?? '')), '/');
$projRootFs = rtrim(str_replace('\\', '/', realpath($PROJECT_ROOT)), '/');
$webRoot = '';
if ($docRoot !== '' && strpos($projRootFs, $docRoot) === 0) {
    $webRoot = substr($projRootFs, strlen($docRoot));
    $webRoot = $webRoot === '' ? '' : ('/' . ltrim($webRoot, '/'));
}
$webRoot = $webRoot ?: '';

// Helper to build web links to project root files
function root_url($path = '') {
    global $webRoot;
    $path = ltrim($path, '/');
    return ($webRoot ?: '') . ($path ? "/{$path}" : '');
}

// Auth: only mpp or admin allowed
$user = current_user();
if (!$user || !in_array($user['role'] ?? '', ['mpp','admin'], true)) {
    header('Location: ' . root_url('login.php'));
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin_announcements.php');
    exit;
}

// Verify CSRF
if (!verify_csrf($_POST['csrf_token'] ?? '')) {
    // don't reveal internals, just log and redirect
    error_log('announcement_delete: invalid CSRF by user id ' . ($user['id'] ?? 'unknown'));
    header('Location: admin_announcements.php');
    exit;
}

// Validate ID
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    header('Location: admin_announcements.php');
    exit;
}

// Soft-delete (archive) the announcement
try {
    $stmt = $pdo->prepare("UPDATE announcements SET status = 'archived', updated_at = NOW() WHERE id = ?");
    $stmt->execute([$id]);
} catch (Exception $e) {
    error_log('announcement_delete error: ' . $e->getMessage());
    // optionally set a flash message in session here
}

// Redirect back to admin list
header('Location: admin_announcements.php');
exit;
