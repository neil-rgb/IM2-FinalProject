<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$document = null;
$loadError = false;
$processingHistory = [];
$historyLoadError = false;

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
                    l.cabinet,
                    l.drawer,
                    l.folder,
                    d.created_at AS recorded_at,
                    u.full_name AS recorded_by,
                    b.deleted_at,
                    a.file_name AS soft_copy_name
             FROM Documents d
             JOIN Document_Types t ON t.type_id = d.type_id
             JOIN Storage_Locations l ON l.location_id = d.location_id
             JOIN Users u ON u.user_id = d.recorded_by
             LEFT JOIN Document_Recycle_Bin b ON b.document_id = d.document_id
             LEFT JOIN Document_Soft_Copies a ON a.document_id = d.document_id
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

if ($document) {
    try {
        $historyStatement = db()->prepare(
            'SELECT h.action_date, h.action_type, h.remarks, h.new_status, u.full_name AS handled_by_name
             FROM Processing_History h
             JOIN Users u ON u.user_id = h.handled_by
             WHERE h.document_id = :document_id
             ORDER BY h.action_date DESC, h.history_id DESC'
        );
        $historyStatement->execute(['document_id' => $document['id']]);
        $processingHistory = $historyStatement->fetchAll();
    } catch (PDOException $exception) {
        error_log('B-TRACK processing history load error: ' . $exception->getMessage());
        $historyLoadError = true;
    }
}

$statusClass = $document ? status_slug((string) $document['status']) : '';
$notice = pull_flash();
$isRecycled = ($document['deleted_at'] ?? null) !== null;
$canMoveToBin = $document && !$isRecycled && user_has_role('Secretary');
$showRecycleForm = $canMoveToBin && query_value('move-to-bin') === '1';
$nextStatus = $document && !$isRecycled && user_has_role('Secretary')
    ? next_document_status((string) $document['status'])
    : null;
$browseQuery = document_browse_query();
$browseSuffix = $browseQuery === [] ? '' : '?' . http_build_query($browseQuery);
$recordQuery = $document ? http_build_query(['id' => $document['id']] + $browseQuery) : '';
$recordUrl = 'view.php?' . $recordQuery;
$breadcrumbItems = document_breadcrumb_parents($browseQuery);
if ($isRecycled) {
    $breadcrumbItems[] = ['Recycle Bin', 'recycle-bin.php'];
}
$breadcrumbItems[] = [$document['tracking'] ?? 'Record unavailable'];
$statusDraft = null;
$staleStatusDraft = false;
$savedStatusDraft = $_SESSION['_document_status_draft'] ?? null;
if ($document && is_array($savedStatusDraft) && $savedStatusDraft['document_id'] === (int) $document['id']) {
    unset($_SESSION['_document_status_draft']);
    if ($savedStatusDraft['status'] === $document['status']) {
        $statusDraft = $savedStatusDraft;
    } else {
        $staleStatusDraft = true;
        $notice = ['type' => 'error', 'message' => 'The status changed. Review the record before continuing.'];
    }
}
$showStatusForm = $nextStatus !== null && !$staleStatusDraft && !$showRecycleForm
    && ($statusDraft !== null || query_value('change-status') === '1');
$invalidRemarks = $statusDraft['invalid_remarks'] ?? false;
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
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/page-transition.js" defer blocking="render"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/ui-system.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell records-workspace document-record-page">
        <?php require dirname(__DIR__) . '/includes/document-sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="records-content record-view">
                <header class="records-header record-detail-bar">
                    <?php require dirname(__DIR__) . '/includes/menu-button.php'; ?>
                    <?php require dirname(__DIR__) . '/includes/breadcrumb.php'; ?>
                </header>

                <?php if ($notice): ?>
                    <div class="dashboard-toast dashboard-toast--<?= e($notice['type'] ?? 'success') ?>" role="<?= ($notice['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
                        <span><?= e(($notice['message'] ?? '') !== '' ? $notice['message'] : ($notice['title'] ?? 'Notice')) ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($document): ?>
                    <header class="record-detail-heading record-page-heading">
                        <div class="record-detail-title">
                            <h1 id="record-title"><?= e($document['title']) ?></h1>
                            <div class="record-detail-meta">
                                <strong class="record-detail-code"><?= e($document['tracking']) ?></strong>
                                <span>Recorded <time datetime="<?= e(str_replace(' ', 'T', $document['recorded_at'])) ?>"><?= e($document['recorded_at']) ?></time></span>
                                <?php if ($isRecycled): ?><span>In Recycle Bin</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="record-detail-heading__actions" role="group" aria-label="Record actions">
                            <span class="record-status record-status--<?= e($statusClass) ?>"><?= e($document['status']) ?></span>
                            <?php if ($nextStatus !== null): ?>
                                <a class="button button--secondary" href="<?= e($recordUrl . '&change-status=1') ?>" aria-haspopup="dialog" aria-controls="record-status-dialog" data-record-dialog-trigger>Change status</a>
                            <?php endif; ?>
                            <?php if ($isRecycled): ?>
                                <a class="button button--secondary record-detail-back" href="recycle-bin.php">Recycle Bin</a>
                            <?php else: ?>
                                <a class="button button--primary record-detail-edit" href="edit.php?<?= e($recordQuery) ?>">
                                    <svg class="record-nav-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                        <path d="M21.731 2.269a2.625 2.625 0 0 0-3.712 0l-1.157 1.157 3.712 3.712 1.157-1.157a2.625 2.625 0 0 0 0-3.712ZM19.513 8.199l-3.712-3.712-8.4 8.4a5.25 5.25 0 0 0-1.32 2.214l-.8 2.685a.75.75 0 0 0 .933.933l2.685-.8a5.25 5.25 0 0 0 2.214-1.32l8.4-8.4Z" />
                                        <path d="M5.25 5.25a3 3 0 0 0-3 3v10.5a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3V13.5a.75.75 0 0 0-1.5 0v5.25a1.5 1.5 0 0 1-1.5 1.5H5.25a1.5 1.5 0 0 1-1.5-1.5V8.25a1.5 1.5 0 0 1 1.5-1.5h5.25a.75.75 0 0 0 0-1.5H5.25Z" />
                                    </svg>
                                    <span>Edit details</span>
                                </a>
                                <?php if ($canMoveToBin): ?>
                                    <a class="button button--secondary recycle-record-button" href="<?= e($recordUrl . '&move-to-bin=1') ?>" aria-label="Move to Recycle Bin" title="Move to Recycle Bin" aria-haspopup="dialog" aria-controls="move-to-bin-dialog" data-record-dialog-trigger>
                                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7m4-7v7"></path></svg>
                                    </a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </header>

                    <?php if ($nextStatus !== null): ?>
                        <dialog class="record-status-dialog popup-panel popup-panel--confirmation" id="record-status-dialog" aria-labelledby="status-dialog-title" aria-describedby="status-dialog-description"<?= $showStatusForm ? ' open' : '' ?>>
                            <header class="document-modal__header">
                                <div>
                                    <h2 id="status-dialog-title">Change document status</h2>
                                    <p id="status-dialog-description">
                                        The change and your remark will be saved in processing history.
                                        <?php if ($nextStatus === 'Completed'): ?>Completed is final.<?php endif; ?>
                                    </p>
                                </div>
                                <a class="popup-close" href="<?= e($recordUrl) ?>" aria-label="Close dialog" data-record-dialog-cancel><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></a>
                            </header>
                            <form class="record-status-form" action="status.php<?= e($browseSuffix) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="document_id" value="<?= e($document['id']) ?>">
                                <input type="hidden" name="current_status" value="<?= e($document['status']) ?>">

                                <div class="record-status-transition">
                                    <span class="record-status record-status--<?= e($statusClass) ?>"><span class="sr-only">Current status: </span><?= e($document['status']) ?></span>
                                    <span aria-hidden="true">→</span>
                                    <span class="record-status record-status--<?= e(status_slug($nextStatus)) ?>"><span class="sr-only">Next status: </span><?= e($nextStatus) ?></span>
                                </div>

                                <?php if ($statusDraft && !$invalidRemarks): ?>
                                    <p class="record-status-error" role="alert"><?= e($statusDraft['message']) ?></p>
                                <?php endif; ?>

                                <label class="modal-field" for="status-remarks">
                                    <span>Remarks <b aria-hidden="true">*</b></span>
                                    <textarea id="status-remarks" name="remarks" rows="3" maxlength="255" required autofocus aria-describedby="status-remarks-hint<?= $invalidRemarks ? ' status-remarks-error' : '' ?>"<?= $invalidRemarks ? ' aria-invalid="true"' : '' ?>><?= e($statusDraft['remarks'] ?? '') ?></textarea>
                                    <small id="status-remarks-hint" class="modal-field__hint">Briefly explain the change, up to 255 characters.</small>
                                    <?php if ($invalidRemarks): ?>
                                        <small id="status-remarks-error" role="alert"><?= e($statusDraft['message']) ?></small>
                                    <?php endif; ?>
                                </label>

                                <div class="form-actions">
                                    <a class="button button--secondary" href="<?= e($recordUrl) ?>" data-record-dialog-cancel>Cancel</a>
                                    <button class="button button--primary" type="submit">Confirm status change</button>
                                </div>
                            </form>
                        </dialog>
                    <?php endif; ?>

                    <?php if ($canMoveToBin): ?>
                        <dialog class="record-status-dialog popup-panel popup-panel--confirmation" id="move-to-bin-dialog" aria-labelledby="move-to-bin-title" aria-describedby="move-to-bin-description"<?= $showRecycleForm ? ' open' : '' ?>>
                            <header class="document-modal__header">
                                <div>
                                    <h2 id="move-to-bin-title">Move record to Recycle Bin?</h2>
                                    <p id="move-to-bin-description"><?= e($document['tracking']) ?> will leave active records. You can restore it from the Recycle Bin.</p>
                                </div>
                                <a class="popup-close" href="<?= e($recordUrl) ?>" aria-label="Close dialog" data-record-dialog-cancel><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" /></svg></a>
                            </header>
                            <form class="record-status-form" action="recycle-bin.php<?= e($browseSuffix) ?>" method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="document_id" value="<?= e($document['id']) ?>">
                                <div class="form-actions">
                                    <a class="button button--secondary" href="<?= e($recordUrl) ?>" data-record-dialog-cancel autofocus>Cancel</a>
                                    <button class="button button--primary" type="submit">Move to Recycle Bin</button>
                                </div>
                            </form>
                        </dialog>
                    <?php endif; ?>

                    <div class="record-detail-columns">
                        <section class="record-detail-panel" aria-label="Document information">
                            <section class="record-detail-group" aria-labelledby="record-classification-title">
                                <h2 id="record-classification-title">Classification</h2>
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
                                <h2 id="record-correspondence-title">Correspondence</h2>
                                <dl>
                                    <div>
                                        <dt><?= $document['direction'] === 'Incoming' ? 'Received from' : 'Sent to' ?></dt>
                                        <dd><?= e($document['party_name']) ?></dd>
                                    </div>
                                    <div>
                                        <dt><?= $document['direction'] === 'Incoming' ? 'Date received' : 'Date released' ?></dt>
                                        <dd><time datetime="<?= e($document['date_received']) ?>"><?= e($document['date_received']) ?></time></dd>
                                    </div>
                                </dl>
                            </section>

                        </section>

                        <section class="record-detail-panel" aria-label="Storage and soft copy">
                            <section class="record-detail-group" aria-labelledby="record-archive-title">
                                <h2 id="record-archive-title">Filing &amp; entry</h2>
                                <dl>
                                    <div>
                                        <dt>Original paper location</dt>
                                        <dd><?= e(storage_location_code($document['cabinet'], $document['drawer'], $document['folder'])) ?></dd>
                                    </div>
                                    <div>
                                        <dt>Recorded by</dt>
                                        <dd><?= e($document['recorded_by']) ?></dd>
                                    </div>
                                </dl>
                            </section>

                            <?php if (user_has_role('Secretary')): ?>
                                <section class="record-detail-group record-soft-copy" aria-labelledby="record-soft-copy-title">
                                    <h2 id="record-soft-copy-title">Soft copy</h2>
                                    <div class="record-soft-copy__details">
                                        <div>
                                            <p><?= e($document['soft_copy_name'] ?? 'No soft copy attached') ?></p>
                                            <?php if (!$isRecycled && $document['soft_copy_name'] === null): ?><p class="record-soft-copy-hint">You can attach a file in Edit details.</p><?php endif; ?>
                                            <?php if ($isRecycled && $document['soft_copy_name'] !== null): ?>
                                                <p>Restore this record to open the file.</p>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!$isRecycled && $document['soft_copy_name'] !== null): ?>
                                            <div class="record-soft-copy__actions">
                                                <a class="button button--secondary" href="soft-copy.php?id=<?= e($document['id']) ?>" target="_blank" rel="noopener">Preview</a>
                                                <a class="button button--secondary" href="soft-copy.php?id=<?= e($document['id']) ?>&amp;download=1">Download</a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </section>
                            <?php endif; ?>
                        </section>
                    </div>
                    <details class="record-history" <?= $historyLoadError ? 'open' : '' ?>>
                        <summary><span>Processing history</span><span class="record-history-count"><?= $historyLoadError ? 'Unavailable' : count($processingHistory) . ' ' . (count($processingHistory) === 1 ? 'entry' : 'entries') ?></span></summary>
                        <?php if ($historyLoadError): ?>
                            <p role="alert">History could not be loaded. Refresh the page to try again.</p>
                        <?php elseif ($processingHistory === []): ?>
                            <p>No processing history recorded yet.</p>
                        <?php else: ?>
                            <div class="record-table-wrap" role="region" aria-label="Document processing history" tabindex="0">
                                <table class="record-table record-history-table">
                                    <caption class="sr-only">Read-only processing history, newest first</caption>
                                    <thead><tr><th scope="col">Date</th><th scope="col">Action</th><th scope="col">Handled by</th><th scope="col">Details</th></tr></thead>
                                    <tbody>
                                        <?php foreach ($processingHistory as $entry): ?>
                                            <tr>
                                                <td data-label="Date"><time datetime="<?= e(str_replace(' ', 'T', $entry['action_date'])) ?>"><?= e($entry['action_date']) ?></time></td>
                                                <td data-label="Action"><?= e($entry['action_type']) ?></td>
                                                <td data-label="Handled by"><?= e($entry['handled_by_name']) ?></td>
                                                <td data-label="Details"><?= e($entry['remarks'] ?? '') ?><?= ($entry['new_status'] ?? '') !== '' ? ' · ' . e($entry['new_status']) : '' ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </details>
                <?php else: ?>
                    <section class="record-not-found" aria-labelledby="record-title">
                        <p class="record-detail-eyebrow">DOCUMENT RECORD</p>
                        <h1 id="record-title"><?= $loadError ? 'Record could not be loaded' : 'Record not found' ?></h1>
                        <p><?= $loadError ? 'The record details are temporarily unavailable. Please try again.' : 'This record may have been moved or the link may be incorrect.' ?></p>
                        <a class="button button--primary" href="index.php">Return to all records <span aria-hidden="true">-&gt;</span></a>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
