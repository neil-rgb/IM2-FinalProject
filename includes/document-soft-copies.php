<?php
declare(strict_types=1);

const DOCUMENT_SOFT_COPY_MAX_BYTES = 10 * 1024 * 1024;
const DOCUMENT_SOFT_COPY_TYPES = [
    'application/pdf' => ['pdf'],
    'image/jpeg' => ['jpg', 'jpeg'],
    'image/png' => ['png'],
];

function soft_copy_request_too_large(): bool
{
    return $_POST === [] && $_FILES === []
        && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > DOCUMENT_SOFT_COPY_MAX_BYTES;
}

/** Validate one optional upload before writing to the database or private storage. */
function read_document_soft_copy_upload(): ?array
{
    $upload = $_FILES['soft_copy'] ?? null;
    if ($upload === null || ($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $error = $upload['error'] ?? null;
    if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
        throw new InvalidArgumentException('Choose a file no larger than 10 MB.');
    }

    $path = $upload['tmp_name'] ?? null;
    $name = $upload['name'] ?? null;
    if ($error !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path) || !is_string($name)) {
        throw new InvalidArgumentException('The upload did not finish. Choose the file again.');
    }

    $size = filesize($path);
    if ($size === false || $size < 1) {
        throw new InvalidArgumentException('Choose a file that is not empty.');
    }
    if ($size > DOCUMENT_SOFT_COPY_MAX_BYTES) {
        throw new InvalidArgumentException('Choose a file no larger than 10 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $extensions = DOCUMENT_SOFT_COPY_TYPES[$mime] ?? [];
    $name = basename(str_replace('\\', '/', $name));
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($extension, $extensions, true)) {
        throw new InvalidArgumentException('Choose a PDF, JPG, or PNG file.');
    }

    $validContents = $mime === 'application/pdf'
        ? str_starts_with((string) file_get_contents($path, false, null, 0, 5), '%PDF-')
        : (@getimagesize($path)['mime'] ?? null) === $mime;
    if (!$validContents) {
        throw new InvalidArgumentException('The file could not be read as a PDF or image.');
    }

    $baseName = pathinfo($name, PATHINFO_FILENAME);
    $baseName = mb_check_encoding($baseName, 'UTF-8')
        ? trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $baseName), " .")
        : '';
    $safeName = mb_strcut($baseName !== '' ? $baseName : 'soft-copy', 0, 200, 'UTF-8') . '.' . $extension;

    return ['path' => $path, 'name' => $safeName, 'mime' => $mime, 'size' => $size];
}

/** Resolve a private storage directory for this installation. */
function document_soft_copy_directory(bool $create = false): string
{
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '') ?: BASE_PATH;
    $configured = getenv('BTRACK_SOFT_COPY_DIR');
    $dataRoot = getenv('LOCALAPPDATA') ?: (getenv('XDG_DATA_HOME') ?: dirname($documentRoot));
    $instance = substr(hash('sha256', BASE_PATH . '|' . DB_NAME), 0, 16);
    $directory = $configured ?: $dataRoot . '/BTRACK/soft-copies/' . $instance;

    // Check the location before making directories, then check its resolved path.
    $existingParent = $directory;
    while (!file_exists($existingParent) && dirname($existingParent) !== $existingParent) {
        $existingParent = dirname($existingParent);
    }

    $normalize = static function (string $path): string {
        $path = rtrim(str_replace('\\', '/', $path), '/');
        return PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    };
    $roots = array_map($normalize, [$documentRoot, realpath(BASE_PATH) ?: BASE_PATH]);
    $isPublic = static function (string $path) use ($normalize, $roots): bool {
        $path = $normalize($path);
        foreach ($roots as $root) {
            if ($path === $root || str_starts_with($path, $root . '/')) {
                return true;
            }
        }
        return false;
    };

    $parent = realpath($existingParent);
    if ($parent === false || $isPublic($parent)) {
        throw new RuntimeException('Soft copy storage must be outside the web root.');
    }
    if ($create && !is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the soft copy directory.');
    }
    $resolved = realpath($directory);
    if ($resolved === false || !is_dir($resolved) || $isPublic($resolved)) {
        throw new RuntimeException('Soft copy storage is unavailable.');
    }

    return $resolved;
}

function document_soft_copy_path(string $storageKey, bool $createDirectory = false): string
{
    if (preg_match('/\A[a-f0-9]{40}\z/', $storageKey) !== 1) {
        throw new RuntimeException('Invalid soft copy storage key.');
    }

    return document_soft_copy_directory($createDirectory) . DIRECTORY_SEPARATOR . $storageKey;
}

function stage_document_soft_copy(array $upload, string $storageKey): string
{
    $path = document_soft_copy_path($storageKey, true);
    if (!@move_uploaded_file($upload['path'], $path)) {
        remove_unused_soft_copy($path);
        throw new RuntimeException('Could not save the file in private storage.');
    }

    return $path;
}

/** Write attachment metadata inside the same transaction as the record. */
function save_document_soft_copy(PDO $connection, int $documentId, string $storageKey, array $upload): void
{
    $statement = $connection->prepare(
        'INSERT INTO Document_Soft_Copies (document_id, storage_key, file_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE storage_key = VALUES(storage_key), file_name = VALUES(file_name),
             mime_type = VALUES(mime_type), file_size = VALUES(file_size)'
    );
    $statement->execute([$documentId, $storageKey, $upload['name'], $upload['mime'], $upload['size']]);
}

function remove_unused_soft_copy(string $path): void
{
    if (is_file($path) && !@unlink($path)) {
        error_log('B-TRACK could not remove an unused soft copy.');
    }
}
