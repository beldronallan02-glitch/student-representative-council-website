<?php
// assets/inc/authenticate.php
// Central authentication helper functions for MPPConnect
// Include this after config.php on any page needing session control

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Log the user in and store their info in the session.
 * @param array $user associative array from DB
 */
function auth_login(array $user): void {
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id'            => (int)$user['id'],
        'email'         => $user['email'],
        'name'          => $user['name'],
        'role'          => $user['role'],
        'role_label'    => $user['role_label'] ?? ucfirst($user['role']),
        'profile_image' => $user['profile_image'] ?? null,
    ];
}

/**
 * Log the user out and destroy the session safely.
 */
function auth_logout(): void {
    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();
}

/**
 * Return the current logged-in user (array) or null.
 */
function current_user(): ?array {
    if (empty($_SESSION['user'])) return null;
    static $cachedUser = null;
    if ($cachedUser !== null) return $cachedUser;

    $sessionUser = $_SESSION['user'];
    $id = (int)($sessionUser['id'] ?? 0);
    if ($id > 0) {
        try {
            global $pdo;
            if ($pdo) {
                $stmt = $pdo->prepare("SELECT id, email, name, role, role_label, profile_image FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $merged = [
                        'id'            => (int)$row['id'],
                        'email'         => $row['email'],
                        'name'          => $row['name'],
                        'role'          => $row['role'],
                        'role_label'    => $row['role_label'] ?? ($sessionUser['role_label'] ?? ucfirst($row['role'])),
                        'profile_image' => $row['profile_image'] ?? null,
                    ];
                    $_SESSION['user'] = $merged;
                    $cachedUser = $merged;
                    return $cachedUser;
                }
            }
        } catch (Throwable $e) {
            // fall back to session values on error
        }
    }
    $cachedUser = $sessionUser;
    return $cachedUser;
}

/**
 * Require user to be logged in.
 * If not logged in, store the current page and redirect to login.php.
 */
function require_login(string $redirectTo = 'login.php'): void {
    if (!current_user()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Require specific role (e.g., 'admin' or 'mpp').
 * Redirects to login if unauthorized.
 */
function require_role(string $role, string $redirectTo = 'login.php'): void {
    $user = current_user();

    if (
        !$user ||
        strtolower($user['role']) !== strtolower($role)
    ) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * After successful login, call this to continue to the original page.
 */
function handle_post_login_redirect(): void {
    if (!empty($_SESSION['redirect_after_login'])) {
        $redirect = basename($_SESSION['redirect_after_login']); // safe basename
        unset($_SESSION['redirect_after_login']);
        header('Location: ' . $redirect);
        exit;
    }
}
