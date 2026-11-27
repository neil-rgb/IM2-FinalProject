<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');

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
                    d.location_id, d.created_at
             FROM Documents d
             WHERE d.document_id = :document_id
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

$hasCurrentCategoryLocations = false;
foreach ($storageLocations as $storageLocation) {
    if ((string) $storageLocation['type_id'] === $formValues['category']) {
        $hasCurrentCategoryLocations = true;
        break;
    }
}
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
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main class="main-content record-detail-page edit-document-page" id="main-content">
        <header class="record-detail-bar">
            <a class="topbar-brand" href="../dashboard.php" aria-label="B-TRACK dashboard">
                <span class="topbar-brand-mark" aria-hidden="true">BT</span>
                <span class="topbar-brand-name">B-TRACK</span>
            </a>
            <?php if ($document): ?>
                <a class="record-detail-back" href="view.php?id=<?= urlencode((string) $document['id']) ?>">Back to record</a>
            <?php else: ?>
                <a class="record-detail-back" href="index.php">All records</a>
            <?php endif; ?>
        </header>

        <?php if ($document && !$loadError): ?>
            <div class="edit-document-shell">
                <section class="document-modal edit-document-panel" aria-labelledby="edit-document-title">
                    <header class="document-modal__header">
                        <div class="modal-heading-lockup">
                            <div class="modal-heading-meta">
                                <span class="modal-kicker">DOCUMENT RECORD / <?= e($document['tracking_number']) ?></span>
                                <span class="modal-step">EDIT / 01</span>
                            </div>
                            <h1 id="edit-document-title">Edit document details</h1>
                            <p>Update the record information and original paper location.</p>
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
                            ];
                            $fieldAnchors = [
                                'title' => 'edit-title',
                                'category' => 'edit-category',
                                'direction' => 'edit-direction',
                                'sender_recipient' => 'edit-sender-recipient',
                                'date_received' => 'edit-date-received',
                                'location_id' => 'edit-location_id',
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

                    <form class="modal-document-form edit-document-form" action="update.php" method="post" data-document-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="document_id" value="<?= e($document['id']) ?>">

                        <section class="modal-form-section" aria-labelledby="edit-details-section-title">
                            <header class="modal-section-heading">
                                <span>01</span>
                                <h2 id="edit-details-section-title">Document details</h2>
                            </header>
                            <div class="modal-form-grid">
                                <label class="modal-field modal-field--wide">
                                    <span>Document title <b>*</b></span>
                                    <input id="edit-title" name="title" type="text" maxlength="150" required value="<?= e($formValues['title']) ?>" aria-invalid="<?= isset($formErrors['title']) ? 'true' : 'false' ?>">
                                    <?php if (isset($formErrors['title'])): ?><small><?= e($formErrors['title']) ?></small><?php endif; ?>
                                </label>

                                <label class="modal-field">
                                    <span>Document category <b>*</b></span>
                                    <select id="edit-category" name="category" required aria-invalid="<?= isset($formErrors['category']) ? 'true' : 'false' ?>">
                                        <option value="">Select category</option>
                                        <?php foreach ($documentTypes as $type): ?>
                                            <option value="<?= e($type['type_id']) ?>"<?= $formValues['category'] === (string) $type['type_id'] ? ' selected' : '' ?>><?= e($type['type_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($formErrors['category'])): ?><small><?= e($formErrors['category']) ?></small><?php endif; ?>
                                </label>

                                <label class="modal-field">
                                    <span>Direction <b>*</b></span>
                                    <select id="edit-direction" name="direction" required data-direction-select aria-invalid="<?= isset($formErrors['direction']) ? 'true' : 'false' ?>">
                                        <option value="">Select direction</option>
                                        <option value="Incoming"<?= $formValues['direction'] === 'Incoming' ? ' selected' : '' ?>>Incoming</option>
                                        <option value="Outgoing"<?= $formValues['direction'] === 'Outgoing' ? ' selected' : '' ?>>Outgoing</option>
                                    </select>
                                    <?php if (isset($formErrors['direction'])): ?><small><?= e($formErrors['direction']) ?></small><?php endif; ?>
                                </label>

                                <label class="modal-field">
                                    <span data-party-label><?= $formValues['direction'] === 'Incoming' ? 'Received from / Sender' : ($formValues['direction'] === 'Outgoing' ? 'Sent to / Recipient' : 'Sender / Recipient') ?> <b>*</b></span>
                                    <input id="edit-sender-recipient" name="sender_recipient" type="text" maxlength="100" required value="<?= e($formValues['sender_recipient']) ?>" aria-invalid="<?= isset($formErrors['sender_recipient']) ? 'true' : 'false' ?>">
                                    <?php if (isset($formErrors['sender_recipient'])): ?><small><?= e($formErrors['sender_recipient']) ?></small><?php endif; ?>
                                </label>

                                <label class="modal-field">
                                    <span data-transaction-label><?= $formValues['direction'] === 'Incoming' ? 'Date received' : ($formValues['direction'] === 'Outgoing' ? 'Date released' : 'Date received / released') ?> <b>*</b></span>
                                    <input id="edit-date-received" name="date_received" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($formValues['date_received']) ?>" aria-invalid="<?= isset($formErrors['date_received']) ? 'true' : 'false' ?>">
                                    <?php if (isset($formErrors['date_received'])): ?><small><?= e($formErrors['date_received']) ?></small><?php endif; ?>
                                </label>
                            </div>
                        </section>

                        <section class="modal-form-section" aria-labelledby="edit-location-section-title">
                            <header class="modal-section-heading">
                                <span>02</span>
                                <h2 id="edit-location-section-title">Storage location</h2>
                            </header>
                            <fieldset id="edit-location_id" class="modal-field modal-field--wide modal-location-field" data-location-group aria-invalid="<?= isset($formErrors['location_id']) ? 'true' : 'false' ?>">
                                <legend>Original paper folder <b>*</b></legend>
                                <div class="storage-choice-list" data-location-choices>
                                    <?php foreach ($storageLocations as $location): ?>
                                        <?php $locationTypeId = (string) $location['type_id']; ?>
                                        <label class="storage-choice" data-storage-choice<?= $locationTypeId !== $formValues['category'] ? ' hidden' : '' ?>>
                                            <input type="radio" name="location_id" value="<?= e($location['location_id']) ?>" data-location-choice data-type-id="<?= e($locationTypeId) ?>" required aria-invalid="<?= isset($formErrors['location_id']) ? 'true' : 'false' ?>"<?= $formValues['category'] === '' ? ' disabled' : '' ?><?= $formValues['location_id'] === (string) $location['location_id'] ? ' checked' : '' ?>>
                                            <span class="storage-choice__path"><?= e($location['cabinet']) ?> / <?= e($location['drawer']) ?> / <?= e($location['folder']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="modal-field__hint" data-location-empty<?= $hasCurrentCategoryLocations ? ' hidden' : '' ?>><?= $formValues['category'] === '' ? 'Choose a category to see its matching cabinet folders.' : 'No storage folders are configured for this category.' ?></small>
                                <?php if (isset($formErrors['location_id'])): ?><small><?= e($formErrors['location_id']) ?></small><?php endif; ?>
                            </fieldset>
                        </section>

                        <div class="modal-form-meta">
                            <span>TRACKING NUMBER <strong><?= e($document['tracking_number']) ?></strong></span>
                            <span>CURRENT STATUS <strong><?= e($document['status']) ?></strong></span>
                            <span>DATE RECORDED <strong><?= e($document['created_at']) ?></strong></span>
                        </div>

                        <footer class="modal-form-actions">
                            <a class="modal-cancel" href="view.php?id=<?= urlencode((string) $document['id']) ?>">Cancel</a>
                            <button class="modal-save" type="submit" data-document-submit><span data-submit-label>Save changes</span><span aria-hidden="true">-&gt;</span></button>
                        </footer>
                    </form>
                </section>
            </div>
        <?php else: ?>
            <section class="record-not-found" aria-labelledby="edit-error-title">
                <p class="record-detail-eyebrow">DOCUMENT RECORD</p>
                <h1 id="edit-error-title"><?= $loadError ? 'Record could not be loaded' : 'Record not found' ?></h1>
                <p><?= $loadError ? 'The record details are temporarily unavailable. Please try again.' : 'This record may have been moved or the link may be incorrect.' ?></p>
                <a class="button button--primary" href="index.php">Return to all records <span aria-hidden="true">-&gt;</span></a>
            </section>
        <?php endif; ?>
    </main>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
