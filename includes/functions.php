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

function next_document_status(string $status): ?string
{
    return match ($status) {
        'Pending' => 'In Process',
        'In Process' => 'Completed',
        default => null,
    };
}

/** Read document rows in the shape shared by the cabinet and list views. */
function fetch_documents(): array
{
    $sql = <<<'SQL'
        SELECT d.document_id AS id,
               d.tracking_number AS tracking,
               d.title,
               t.type_name AS category,
               d.direction,
               d.sender_recipient AS party_name,
               d.date_received AS recorded,
               d.status,
               CONCAT(l.cabinet, ' / ', l.drawer, ' / ', l.folder) AS location,
               d.created_at AS recorded_at,
               u.full_name AS recorded_by
        FROM Documents d
        JOIN Document_Types t ON t.type_id = d.type_id
        JOIN Storage_Locations l ON l.location_id = d.location_id
        JOIN Users u ON u.user_id = d.recorded_by
        ORDER BY d.created_at DESC, d.document_id DESC
        SQL;

    return db()->query($sql)->fetchAll();
}
