<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$document = null;
$loadError = false;

if (is_int($documentId) && $documentId > 0) {
    try {
        $statement = db()->prepare(
            'SELECT d.document_id AS id,
                    d.tracking_number AS tracking,
                    d.title,
                    t.type_name AS category,
                    d.direction,
                    d.sender_recipient AS party_name,
                    d.date_received,
                    d.status,
                    CONCAT_WS(" / ", l.cabinet, l.drawer, l.folder) AS storage_location,
                    d.created_at AS recorded_at,
                    u.full_name AS recorded_by
             FROM Documents d
             JOIN Document_Types t ON t.type_id = d.type_id
             JOIN Storage_Locations l ON l.location_id = d.location_id
             JOIN Users u ON u.user_id = d.recorded_by
             WHERE d.document_id = :document_id
             LIMIT 1'
        );
        $statement->execute(['document_id' => $documentId]);
        $document = $statement->fetch() ?: null;
    } catch (PDOException $exception) {
        error_log('B-TRACK document detail load error: ' . $exception->getMessage());
        $loadError = true;
    }
}

if (!$document && !$loadError) {
    http_response_code(404);
}

$statusClass = $document ? status_slug((string) $document['status']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="B-TRACK document record details">
    <title><?= $document ? e($document['tracking']) . ' / B-TRACK' : 'Record unavailable / B-TRACK' ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main class="main-content record-detail-page" id="main-content">
        <header class="record-detail-bar">
            <a class="topbar-brand" href="../dashboard.php" aria-label="B-TRACK dashboard">
                <span class="topbar-brand-mark" aria-hidden="true">BT</span>
                <span class="topbar-brand-name">B-TRACK</span>
            </a>
            <a class="record-detail-back" href="index.php">All records</a>
        </header>

        <?php if ($document): ?>
            <section class="record-detail-heading" aria-labelledby="record-title">
                <div>
                    <p class="record-detail-eyebrow">DOCUMENT RECORD / <?= e($document['tracking']) ?></p>
                    <h1 id="record-title"><?= e($document['title']) ?></h1>
                </div>
                <span class="record-status record-status--<?= e($statusClass) ?>"><?= e($document['status']) ?></span>
            </section>

            <section class="record-detail-panel" aria-label="Document information">
                <dl class="record-detail-grid">
                    <div class="record-detail-grid__wide">
                        <dt>Document code</dt>
                        <dd><?= e($document['tracking']) ?></dd>
                    </div>
                    <div>
                        <dt>Document category</dt>
                        <dd><?= e($document['category']) ?></dd>
                    </div>
                    <div>
                        <dt>Direction</dt>
                        <dd><?= e($document['direction']) ?></dd>
                    </div>
                    <div>
                        <dt>Sender / recipient</dt>
                        <dd><?= e($document['party_name']) ?></dd>
                    </div>
                    <div>
                        <dt>Date received / released</dt>
                        <dd><time datetime="<?= e($document['date_received']) ?>"><?= e($document['date_received']) ?></time></dd>
                    </div>
                    <div>
                        <dt>Original paper location</dt>
                        <dd><?= e($document['storage_location']) ?></dd>
                    </div>
                    <div>
                        <dt>Recorded by</dt>
                        <dd><?= e($document['recorded_by']) ?></dd>
                    </div>
                    <div>
                        <dt>Date recorded</dt>
                        <dd><time datetime="<?= e($document['recorded_at']) ?>"><?= e($document['recorded_at']) ?></time></dd>
                    </div>
                </dl>
            </section>
        <?php else: ?>
            <section class="record-not-found" aria-labelledby="record-title">
                <p class="record-detail-eyebrow">DOCUMENT RECORD</p>
                <h1 id="record-title"><?= $loadError ? 'Record could not be loaded' : 'Record not found' ?></h1>
                <p><?= $loadError ? 'The record details are temporarily unavailable. Please try again.' : 'This record may have been moved or the link may be incorrect.' ?></p>
                <a class="button button--primary" href="index.php">Return to all records <span aria-hidden="true">-&gt;</span></a>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
