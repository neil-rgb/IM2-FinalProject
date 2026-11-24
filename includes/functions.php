<?php
declare(strict_types=1);

/** Escape a value before displaying it in HTML. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Redirect to another local page and stop the current request. */
function redirect(string $location, int $status = 302): void
{
    if (preg_match('/[\r\n]/', $location) === 1) {
        throw new InvalidArgumentException('Invalid redirect location.');
    }

    header('Location: ' . $location, true, $status);
    exit;
}

function is_post_request(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function post_value(string $key, string $default = ''): string
{
    $value = $_POST[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

function query_value(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;

    return is_string($value) ? trim($value) : $default;
}

/** Store a one-request message in the session. */
function flash(string $type, string $title, string $message): void
{
    $_SESSION['_flash'] = [
        'type' => $type,
        'title' => $title,
        'message' => $message,
    ];
}

/** Retrieve and remove the current flash message. */
function pull_flash(): ?array
{
    $message = $_SESSION['_flash'] ?? null;
    unset($_SESSION['_flash']);

    return is_array($message) ? $message : null;
}

/** Return the session CSRF token, creating it when necessary. */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf_token(?string $token): bool
{
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    return is_string($token)
        && is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $token);
}

/** Create the human-readable B-TRACK tracking number. */
function tracking_number(int $sequence, ?int $year = null): string
{
    $recordYear = $year ?? (int) date('Y');

    return sprintf('BT-%04d-%04d', $recordYear, max(0, $sequence));
}

/** Convert a status label into a safe CSS modifier. */
function status_slug(string $status): string
{
    $slug = strtolower(trim($status));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';

    return trim($slug, '-');
}
