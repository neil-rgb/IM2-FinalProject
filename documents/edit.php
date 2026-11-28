<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');
require __DIR__ . '/../includes/react-shell.php';
require_once __DIR__ . '/../includes/document-soft-copies.php';

$documentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$document = null;
$documentTypes = [];
$storageLocations = [];
$formErrors = [];
$formValues = [
    'title' => '',
    'category' => '',
    'direction' => '',
    'sender_recipient' => '',
    'date_received' => '',
    'location_id' => '',
];
$loadError = false;

if (!is_int($documentId) || $documentId < 1) {
    http_response_code(404);
} else {
    try {
        $statement = db()->prepare(
            'SELECT d.document_id AS id, d.tracking_number, d.title, d.type_id,
                    d.direction, d.sender_recipient, d.date_received, d.status,
                    d.location_id, d.created_at, a.file_name AS soft_copy_name
             FROM Documents d
             LEFT JOIN Document_Soft_Copies a ON a.document_id = d.document_id
             WHERE d.document_id = :document_id
               AND NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = d.document_id)
             LIMIT 1'
        );
        $statement->execute(['document_id' => $documentId]);
        $document = $statement->fetch() ?: null;

        $documentTypes = db()->query(
            'SELECT type_id, type_name FROM Document_Types ORDER BY type_name'
        )->fetchAll();
        $storageLocations = db()->query(
            'SELECT location_id, type_id, cabinet, drawer, folder
             FROM Storage_Locations
             WHERE type_id IS NOT NULL
             ORDER BY type_id, cabinet, drawer, folder'
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('B-TRACK edit form load error: ' . $exception->getMessage());
        $loadError = true;
    }

    if ($document) {
        $formValues = [
            'title' => (string) $document['title'],
            'category' => (string) $document['type_id'],
            'direction' => (string) $document['direction'],
            'sender_recipient' => (string) $document['sender_recipient'],
            'date_received' => (string) $document['date_received'],
            'location_id' => (string) $document['location_id'],
        ];

        $savedDraft = $_SESSION['_document_edit_draft'] ?? null;
        if (is_array($savedDraft) && (int) ($savedDraft['document_id'] ?? 0) === $documentId) {
            $formValues = array_merge($formValues, $savedDraft['values'] ?? []);
            $formErrors = is_array($savedDraft['errors'] ?? null) ? $savedDraft['errors'] : [];
            unset($_SESSION['_document_edit_draft']);
        }
    } elseif (!$loadError) {
        http_response_code(404);
    }
}

$browseQuery = document_browse_query();
$recordUrl = $document ? 'view.php?' . http_build_query(['id' => $document['id']] + $browseQuery) : '';
$breadcrumbItems = document_breadcrumb_parents($browseQuery);
if ($document) {
    $breadcrumbItems[] = [$document['tracking_number'], $recordUrl];
}
$breadcrumbItems[] = ['Edit details'];

$currentSoftCopyName = $document['soft_copy_name'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Edit a B-TRACK document record">
    <title><?= $document ? 'Edit ' . e($document['tracking_number']) . ' / B-TRACK' : 'Edit record / B-TRACK' ?></title>
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
    <div class="app-shell records-workspace">
        <?php require dirname(__DIR__) . '/includes/document-sidebar.php'; ?>

        <main class="main-content edit-document-page" id="main-content">
            <div class="records-content">
                <header class="records-header record-detail-bar">
                    <?php require dirname(__DIR__) . '/includes/menu-button.php'; ?>
                    <?php require dirname(__DIR__) . '/includes/breadcrumb.php'; ?>
                </header>

                <?php if ($document && !$loadError): ?>
                    <section class="edit-document" aria-labelledby="edit-document-title">
                        <header class="record-detail-heading">
                            <div class="record-detail-title">
                                <h1 id="edit-document-title">Edit document details</h1>
                                <div class="record-detail-meta">
                                    <strong class="record-detail-code"><?= e($document['tracking_number']) ?></strong>
                                    <span>Recorded <time datetime="<?= e(str_replace(' ', 'T', $document['created_at'])) ?>"><?= e($document['created_at']) ?></time></span>
                                    <span class="record-status record-status--<?= e(status_slug((string) $document['status'])) ?>">
                                        <span class="sr-only">Current status: </span><?= e($document['status']) ?>
                                    </span>
                                </div>
                            </div>
                        </header>

                        <?php if ($formErrors !== []): ?>
                            <div class="modal-error-summary" role="alert" tabindex="-1" autofocus>
                                <strong>Check these details</strong>
                                <?php if (isset($formErrors['_form'])): ?><span><?= e($formErrors['_form']) ?></span><?php endif; ?>
                                <?php
                                $fieldLabels = [
                                    'title' => 'Document title',
                                    'category' => 'Document category',
                                    'direction' => 'Direction',
                                    'sender_recipient' => 'Sender or recipient',
                                    'date_received' => 'Received or released date',
                                    'location_id' => 'Original paper folder',
                                    'soft_copy' => 'Soft copy',
                                ];
                                $fieldAnchors = [
                                    'title' => 'edit-title',
                                    'category' => 'edit-category',
                                    'direction' => 'edit-direction',
                                    'sender_recipient' => 'edit-sender-recipient',
                                    'date_received' => 'edit-date-received',
                                    'location_id' => 'edit-location_id',
                                    'soft_copy' => 'document-soft-copy',
                                ];
                                $fieldErrorKeys = array_intersect(array_keys($formErrors), array_keys($fieldLabels));
                                ?>
                                <?php if ($fieldErrorKeys !== []): ?>
                                    <ul>
                                        <?php foreach ($fieldErrorKeys as $fieldErrorKey): ?>
                                            <li><a href="#<?= e($fieldAnchors[$fieldErrorKey]) ?>"><?= e($fieldLabels[$fieldErrorKey]) ?>: <?= e($formErrors[$fieldErrorKey]) ?></a></li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php elseif (!isset($formErrors['_form'])): ?>
                                    <span>Correct the highlighted fields, then save again.</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <form class="edit-document-form" action="update.php?<?= e(http_build_query(['id' => $document['id']] + $browseQuery)) ?>" method="post" enctype="multipart/form-data" data-document-form>
                            <?= csrf_field() ?>
                            <input type="hidden" name="document_id" value="<?= e($document['id']) ?>">

                            <div class="document-fields-grid">
                                <section class="record-detail-panel document-fields-panel" aria-labelledby="edit-details-section-title">
                                    <h2 id="edit-details-section-title">Document details</h2>
                                    <div class="modal-form-grid">
                                        <label class="modal-field modal-field--wide">
                                            <span>Document title <b>*</b></span>
                                            <input id="edit-title" name="title" type="text" maxlength="150" required value="<?= e($formValues['title']) ?>"<?= isset($formErrors['title']) ? ' aria-invalid="true" aria-describedby="edit-title-error"' : '' ?>>
                                            <?php if (isset($formErrors['title'])): ?><small id="edit-title-error"><?= e($formErrors['title']) ?></small><?php endif; ?>
                                        </label>

                                        <label class="modal-field">
                                            <span>Document category <b>*</b></span>
                                            <select id="edit-category" name="category" required<?= isset($formErrors['category']) ? ' aria-invalid="true" aria-describedby="edit-category-error"' : '' ?>>
                                                <option value="">Select category</option>
                                                <?php foreach ($documentTypes as $type): ?>
                                                    <option value="<?= e($type['type_id']) ?>"<?= $formValues['category'] === (string) $type['type_id'] ? ' selected' : '' ?>><?= e($type['type_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($formErrors['category'])): ?><small id="edit-category-error"><?= e($formErrors['category']) ?></small><?php endif; ?>
                                        </label>

                                        <label class="modal-field">
                                            <span>Direction <b>*</b></span>
                                            <select id="edit-direction" name="direction" required data-direction-select<?= isset($formErrors['direction']) ? ' aria-invalid="true" aria-describedby="edit-direction-error"' : '' ?>>
                                                <option value="">Select direction</option>
                                                <option value="Incoming"<?= $formValues['direction'] === 'Incoming' ? ' selected' : '' ?>>Incoming</option>
                                                <option value="Outgoing"<?= $formValues['direction'] === 'Outgoing' ? ' selected' : '' ?>>Outgoing</option>
                                            </select>
                                            <?php if (isset($formErrors['direction'])): ?><small id="edit-direction-error"><?= e($formErrors['direction']) ?></small><?php endif; ?>
                                        </label>

                                        <label class="modal-field">
                                            <span data-party-label><?= $formValues['direction'] === 'Incoming' ? 'Received from / Sender' : ($formValues['direction'] === 'Outgoing' ? 'Sent to / Recipient' : 'Sender / Recipient') ?> <b>*</b></span>
                                            <input id="edit-sender-recipient" name="sender_recipient" type="text" maxlength="100" required value="<?= e($formValues['sender_recipient']) ?>"<?= isset($formErrors['sender_recipient']) ? ' aria-invalid="true" aria-describedby="edit-sender-recipient-error"' : '' ?>>
                                            <?php if (isset($formErrors['sender_recipient'])): ?><small id="edit-sender-recipient-error"><?= e($formErrors['sender_recipient']) ?></small><?php endif; ?>
                                        </label>

                                        <label class="modal-field">
                                            <span data-transaction-label><?= $formValues['direction'] === 'Incoming' ? 'Date received' : ($formValues['direction'] === 'Outgoing' ? 'Date released' : 'Document date') ?> <b>*</b></span>
                                            <input id="edit-date-received" name="date_received" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($formValues['date_received']) ?>"<?= isset($formErrors['date_received']) ? ' aria-invalid="true" aria-describedby="edit-date-received-error"' : '' ?>>
                                            <?php if (isset($formErrors['date_received'])): ?><small id="edit-date-received-error"><?= e($formErrors['date_received']) ?></small><?php endif; ?>
                                        </label>
                                    </div>
                                </section>

                                <section class="record-detail-panel document-fields-panel" aria-labelledby="edit-location-section-title">
                                    <h2 id="edit-location-section-title">Paper filing</h2>
                                    <?php
                                    $folderFieldId = 'edit-location_id';
                                    $folderTypeId = $formValues['category'];
                                    require __DIR__ . '/../includes/document-folder-field.php';
                                    ?>
                                    <?php require __DIR__ . '/../includes/soft-copy-field.php'; ?>
                                </section>
                            </div>

                            <footer class="edit-document-actions">
                                <a class="button button--secondary" href="<?= e($recordUrl) ?>">Cancel</a>
                                <button class="button button--primary" type="submit" data-document-submit><span data-submit-label>Save changes</span></button>
                            </footer>
                        </form>
                    </section>
                <?php else: ?>
                    <section class="record-not-found" aria-labelledby="edit-error-title">
                        <p class="record-detail-eyebrow">DOCUMENT RECORD</p>
                        <h1 id="edit-error-title"><?= $loadError ? 'Record could not be loaded' : 'Record not found' ?></h1>
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
