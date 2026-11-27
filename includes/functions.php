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

/** Read the supported document list filters for the results and sidebar. */
function document_list_filters(): array
{
    $years = ['2026', '2025'];
    $statuses = ['Pending', 'In Process', 'Completed'];
    $year = query_value('year', '2026');
    $status = query_value('status');

    return [
        $years,
        $statuses,
        query_value('search'),
        in_array($year, $years, true) ? $year : '',
        in_array($status, $statuses, true) ? $status : '',
    ];
}

/** Carry the filters and originating view through document navigation. */
function document_browse_query(): array
{
    $query = array_filter(array_intersect_key($_GET, array_flip(['search', 'year', 'status'])), 'is_string');

    $origin = query_value('from');
    $inventoryLocation = filter_input(INPUT_GET, 'inventory_location', FILTER_VALIDATE_INT);

    if ($origin === 'list') {
        $query['from'] = 'list';
    } elseif ($origin === 'inventory' && is_int($inventoryLocation) && $inventoryLocation > 0) {
        $query['from'] = 'inventory';
        $query['inventory_location'] = $inventoryLocation;
    }

    return $query;
}

/** Link to the overview and the page from which a document was opened. */
function document_breadcrumb_parents(array $browseQuery): array
{
    $origin = $browseQuery['from'] ?? '';
    $inventoryLocation = $browseQuery['inventory_location'] ?? null;
    unset($browseQuery['from'], $browseQuery['inventory_location']);
    $suffix = $browseQuery === [] ? '' : '?' . http_build_query($browseQuery);
    $items = [['Overview', '../dashboard.php' . $suffix]];

    if ($origin === 'list') {
        $items[] = ['All records', 'index.php' . $suffix];
    } elseif ($origin === 'inventory' && $inventoryLocation !== null) {
        $items[] = ['Folder inventory', 'inventory.php?location_id=' . $inventoryLocation];
    }

    return $items;
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

/** Call after locking the document row to serialize edits with bin moves and restores. */
function document_is_recycled(PDO $connection, int $documentId): bool
{
    $query = $connection->prepare(
        'SELECT document_id FROM Document_Recycle_Bin WHERE document_id = :document_id FOR UPDATE'
    );
    $query->execute(['document_id' => $documentId]);
    return $query->fetchColumn() !== false;
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
        WHERE NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = d.document_id)
        ORDER BY d.created_at DESC, d.document_id DESC
        SQL;

    return db()->query($sql)->fetchAll();
}
