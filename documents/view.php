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
$notice = pull_flash();
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
        </header>

        <?php if ($notice): ?>
            <div class="dashboard-toast dashboard-toast--<?= e($notice['type'] ?? 'success') ?>" role="<?= ($notice['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
                <span><?= e(($notice['message'] ?? '') !== '' ? $notice['message'] : ($notice['title'] ?? 'Notice')) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($document): ?>
            <section class="record-detail-heading" aria-label="Record actions">
                <p class="record-detail-eyebrow">DOCUMENT RECORD / <?= e($document['tracking']) ?></p>
                <div class="record-detail-heading__actions">
                    <span class="record-status record-status--<?= e($statusClass) ?>"><?= e($document['status']) ?></span>
                    <nav class="record-detail-navigation" aria-label="Record navigation">
                        <a class="button button--primary record-detail-back" href="index.php">All records</a>
                        <a class="record-dashboard-exit" href="../dashboard.php" aria-label="Return to dashboard" title="Return to dashboard">
                            <svg class="record-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                <path fill-rule="evenodd" d="M9.53 2.47a.75.75 0 0 1 0 1.06L4.81 8.25H15a6.75 6.75 0 0 1 0 13.5h-3a.75.75 0 0 1 0-1.5h3a5.25 5.25 0 1 0 0-10.5H4.81l4.72 4.72a.75.75 0 1 1-1.06 1.06l-6-6a.75.75 0 0 1 0-1.06l6-6a.75.75 0 0 1 1.06 0Z" clip-rule="evenodd" />
                            </svg>
                            <span class="sr-only">Return to dashboard</span>
                        </a>
                    </nav>
                    <a class="button button--primary record-detail-edit" href="edit.php?id=<?= urlencode((string) $document['id']) ?>">
                        <svg class="record-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
                            <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" />
                        </svg>
                        <span>Edit details</span>
                    </a>
                </div>
            </section>

            <section class="record-detail-cover" aria-labelledby="record-title">
                <span class="folder-tab" aria-hidden="true"></span>
                <div class="record-detail-code">
                    <span>DOCUMENT FILE</span>
                    <strong><?= e($document['tracking']) ?></strong>
                </div>
                <h1 id="record-title"><?= e($document['title']) ?></h1>
                <div class="document-recorded record-detail-cover__recorded">
                    <span>RECORDED</span>
                    <time datetime="<?= e($document['recorded_at']) ?>"><?= e($document['recorded_at']) ?></time>
                </div>
            </section>

            <section class="record-detail-panel" aria-label="Document information">
                <div class="record-detail-sheet">
                    <header class="record-detail-sheet__header">
                        <strong>DOCUMENT REGISTER</strong>
                        <span>RECORD DETAILS / <?= e($document['tracking']) ?></span>
                    </header>
                    <div class="record-detail-groups">
                        <section class="record-detail-group" aria-labelledby="record-classification-title">
                            <header>
                                <span>01</span>
                                <h2 id="record-classification-title">Classification</h2>
                            </header>
                            <dl>
                                <div>
                                    <dt>Document category</dt>
                                    <dd><?= e($document['category']) ?></dd>
                                </div>
                                <div>
                                    <dt>Direction</dt>
                                    <dd><?= e($document['direction']) ?></dd>
                                </div>
                            </dl>
                        </section>

                        <section class="record-detail-group" aria-labelledby="record-correspondence-title">
                            <header>
                                <span>02</span>
                                <h2 id="record-correspondence-title">Correspondence</h2>
                            </header>
                            <dl>
                                <div>
                                    <dt>Sender / recipient</dt>
                                    <dd><?= e($document['party_name']) ?></dd>
                                </div>
                                <div>
                                    <dt>Date received / released</dt>
                                    <dd><time datetime="<?= e($document['date_received']) ?>"><?= e($document['date_received']) ?></time></dd>
                                </div>
                            </dl>
                        </section>

                        <section class="record-detail-group" aria-labelledby="record-archive-title">
                            <header>
                                <span>03</span>
                                <h2 id="record-archive-title">Filing &amp; entry</h2>
                            </header>
                            <dl>
                                <div>
                                    <dt>Original paper location</dt>
                                    <dd><?= e($document['storage_location']) ?></dd>
                                </div>
                                <div>
                                    <dt>Recorded by</dt>
                                    <dd><?= e($document['recorded_by']) ?></dd>
                                </div>
                            </dl>
                        </section>
                    </div>
                </div>
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
