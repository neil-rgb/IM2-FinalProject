<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function is_authenticated(): bool
{
    $user = current_user();

    return $user !== null
        && isset($user['id'], $user['username'])
        && (string) $user['id'] !== ''
        && (string) $user['username'] !== '';
}

/** Save the authenticated account in the current session. */
function login_user(array $user): void
{
    if (!isset($user['id'], $user['username'])) {
        throw new InvalidArgumentException('The user ID and username are required.');
    }

    session_regenerate_id(true);

    $_SESSION['user'] = [
        'id' => (string) $user['id'],
        'username' => (string) $user['username'],
        'full_name' => (string) ($user['full_name'] ?? $user['username']),
        'role' => (string) ($user['role'] ?? 'Secretary'),
    ];
    $_SESSION['authenticated_at'] = time();
    $_SESSION['_session_started_at'] = time();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies') && !headers_sent()) {
        $cookie = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $cookie['path'],
            $cookie['domain'],
            (bool) $cookie['secure'],
            (bool) $cookie['httponly']
        );
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Redirect guests away from a protected page. */
function require_auth(string $loginPath = 'login.php'): void
{
    if (!is_authenticated()) {
        if (($_SERVER['HTTP_X_BTRACK_REACT'] ?? '') === '1') {
            http_response_code(401);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['message' => 'Sign in to continue.']);
            exit;
        }
        redirect($loginPath . '?notice=unauthorized');
    }
}

/** Redirect an authenticated user away from sign-in or registration pages. */
function require_guest(string $dashboardPath = 'dashboard.php'): void
{
    if (is_authenticated()) {
        redirect($dashboardPath);
    }
}

function user_has_role(string $role): bool
{
    $user = current_user();

    return $user !== null
        && isset($user['role'])
        && strcasecmp((string) $user['role'], $role) === 0;
}

/** Stop an authenticated user from opening a role-restricted page. */
function require_role(string $role, string $fallbackPath = 'dashboard.php'): void
{
    require_auth();

    if (!user_has_role($role)) {
        redirect($fallbackPath . '?notice=unauthorized');
    }
}
