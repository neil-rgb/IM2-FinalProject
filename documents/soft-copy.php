<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');
require_once __DIR__ . '/../includes/document-soft-copies.php';

if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

header('Cache-Control: private, no-store');
header('Content-Type: text/plain; charset=UTF-8');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'none'; base-uri 'none'; frame-ancestors 'none'");

if (!user_has_role('Secretary')) {
    http_response_code(403);
    exit('Only an active secretary can open soft copies.');
}

$userId = (int) current_user()['id'];
$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
session_write_close();

if (!is_int($documentId) || $documentId < 1) {
    http_response_code(404);
    exit('Soft copy unavailable.');
}

$connection = null;
try {
    $connection = db();
    $userQuery = $connection->prepare('SELECT status FROM Users WHERE user_id = ?');
    $userQuery->execute([$userId]);
    if ($userQuery->fetchColumn() !== 'Active') {
        http_response_code(403);
        exit('Only an active secretary can open soft copies.');
    }

    // Hold the metadata row until the file is read, so a replacement cannot remove it mid-read.
    $connection->beginTransaction();
    $statement = $connection->prepare(
        'SELECT a.storage_key, a.file_name, a.mime_type, a.file_size
         FROM Document_Soft_Copies a
         WHERE a.document_id = ?
           AND NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = a.document_id)
         FOR UPDATE'
    );
    $statement->execute([$documentId]);
    $file = $statement->fetch();
    $contents = false;
    if ($file && isset(DOCUMENT_SOFT_COPY_TYPES[$file['mime_type']])) {
        $path = document_soft_copy_path($file['storage_key']);
        $contents = @file_get_contents($path, false, null, 0, DOCUMENT_SOFT_COPY_MAX_BYTES + 1);
    }
    $connection->commit();
} catch (RuntimeException $exception) {
    if ($connection && $connection->inTransaction()) {
        $connection->rollBack();
    }
    error_log('B-TRACK soft copy access error: ' . $exception->getMessage());
    http_response_code(503);
    exit('The soft copy could not be opened. Try again later.');
}

if ($contents === false || strlen($contents) !== (int) $file['file_size']) {
    http_response_code(404);
    exit('Soft copy unavailable.');
}

$filename = $file['file_name'];
$fallbackName = (string) preg_replace('/[^A-Za-z0-9_. -]/', '_', $filename);
$disposition = query_value('download') === '1' ? 'attachment' : 'inline';

header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . strlen($contents));
header("Content-Disposition: {$disposition}; filename=\"{$fallbackName}\"; filename*=UTF-8''" . rawurlencode($filename));
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo $contents;
}
