<?php
require_once dirname(__DIR__) . '/includes/auth.php';

$categories = [
    'Finance',
    'Resolutions',
    'Requests & Endorsements',
    'Notices & Communications',
    'Administrative Records',
];
$directions = ['Incoming', 'Outgoing'];
$today = date('Y-m-d');
$notice = pull_flash();
$errors = [];

$values = [
    'title' => '',
    'category' => '',
    'direction' => '',
    'document_date' => '',
    'party_name' => '',
    'transaction_date' => '',
    'description' => '',
    'remarks' => '',
];

if (is_post_request()) {
    foreach (array_keys($values) as $field) {
        $values[$field] = post_value($field);
    }

    if (!verify_csrf_token(post_value('_token'))) {
        $errors['_form'] = 'Your form session expired. Refresh the page and try again.';
    }

    if ($values['title'] === '') {
        $errors['title'] = 'Document title is required.';
    }

    if (!in_array($values['category'], $categories, true)) {
        $errors['category'] = 'Select a document category.';
    }

    if (!in_array($values['direction'], $directions, true)) {
        $errors['direction'] = 'Select whether the document is incoming or outgoing.';
    }

    if ($values['document_date'] === '') {
        $errors['document_date'] = 'Document date is required.';
    }

    if ($values['party_name'] === '') {
        $errors['party_name'] = 'Enter the sender or recipient.';
    }

    if ($values['transaction_date'] === '') {
        $errors['transaction_date'] = 'Received or released date is required.';
    }

    foreach (['document_date', 'transaction_date'] as $dateField) {
        if ($values[$dateField] !== '') {
            $date = DateTime::createFromFormat('Y-m-d', $values[$dateField]);

            if (!$date || $date->format('Y-m-d') !== $values[$dateField]) {
                $errors[$dateField] = 'Enter a valid date.';
            }
        }
    }

    if ($values['description'] === '') {
        $errors['description'] = 'Subject or description is required.';
    }

    if ($errors === []) {
        $sequence = (int) ($_SESSION['document_sequence'] ?? 0) + 1;
        $_SESSION['document_sequence'] = $sequence;
        $tracking = tracking_number($sequence);
        $recordedBy = current_user()['full_name'] ?? 'Barangay Secretary';
        $document = [
            'id' => $sequence,
            'tracking' => $tracking,
            'title' => $values['title'],
            'category' => $values['category'],
            'direction' => $values['direction'],
            'document_date' => $values['document_date'],
            'party_name' => $values['party_name'],
            'transaction_date' => $values['transaction_date'],
            'description' => $values['description'],
            'remarks' => $values['remarks'],
            'status' => 'Pending',
            'recorded' => date('M d, Y'),
            'recorded_at' => date('Y-m-d H:i:s'),
            'recorded_by' => $recordedBy,
        ];

        $_SESSION['documents'] = is_array($_SESSION['documents'] ?? null) ? $_SESSION['documents'] : [];
        $_SESSION['documents'][] = $document;
        $_SESSION['processing_history'] = is_array($_SESSION['processing_history'] ?? null) ? $_SESSION['processing_history'] : [];
        $_SESSION['processing_history'][] = [
            'document_id' => $sequence,
            'tracking' => $tracking,
            'action' => 'Document recorded',
            'status' => 'Pending',
            'handled_by' => $recordedBy,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        flash('success', 'Document saved', $tracking . ' was recorded successfully with Pending status.');
        redirect('create.php');
    }
}

function field_error(array $errors, string $field): string
{
    return $errors[$field] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Record a barangay document in B-TRACK">
    <title>B-TRACK / Record Document</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/document-form.css">
</head>
<body class="document-page">
    <main class="document-modal" aria-labelledby="document-form-title">
        <header class="document-modal__header">
            <div class="form-brand" aria-label="B-TRACK">
                <span class="form-brand__mark" aria-hidden="true">BT</span>
                <div>
                    <strong>B-TRACK</strong>
                    <span>NEW DOCUMENT / ENTRY</span>
                </div>
            </div>
            <a class="close-form" href="index.php" aria-label="Close document form" title="Close">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"></path></svg>
            </a>
        </header>

        <div class="document-modal__body">
            <div class="form-intro">
                <div>
                    <span>DOCUMENT REGISTRY / CREATE</span>
                    <h1 id="document-form-title">Record document</h1>
                    <p>Enter the details from the incoming or outgoing document.</p>
                </div>
                <div class="auto-record">
                    <span>DATE RECORDED</span>
                    <strong><?= e(date('M d, Y')) ?></strong>
                </div>
            </div>

            <?php if ($notice): ?>
                <div class="form-notice form-notice--success" role="status">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"></path></svg>
                    <div><strong><?= e($notice['title']) ?></strong><p><?= e($notice['message']) ?></p></div>
                </div>
            <?php endif; ?>

            <div class="validation-summary" id="validation-summary" role="alert" tabindex="-1"<?= $errors === [] ? ' hidden' : '' ?>>
                <strong>Check the required fields</strong>
                <p><?= e($errors['_form'] ?? 'Complete the highlighted fields before saving this document.') ?></p>
            </div>

            <form class="document-form" action="create.php" method="post" data-auth-form data-document-form novalidate>
                <?= csrf_field() ?>

                <section class="form-section" aria-labelledby="document-information-title">
                    <div class="form-section__heading">
                        <span>01</span>
                        <h2 id="document-information-title">Document information</h2>
                    </div>

                    <div class="form-grid">
                        <div class="form-field form-field--wide">
                            <label for="title">Document title <span aria-hidden="true">*</span></label>
                            <input id="title" name="title" type="text" value="<?= e($values['title']) ?>" required data-error-message="Document title is required." aria-describedby="title-error" aria-invalid="<?= isset($errors['title']) ? 'true' : 'false' ?>">
                            <p class="field-error" id="title-error" aria-live="polite"><?= e(field_error($errors, 'title')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="category">Category <span aria-hidden="true">*</span></label>
                            <select id="category" name="category" required data-error-message="Select a document category." aria-describedby="category-error" aria-invalid="<?= isset($errors['category']) ? 'true' : 'false' ?>">
                                <option value="">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= e($category) ?>"<?= $values['category'] === $category ? ' selected' : '' ?>><?= e($category) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-error" id="category-error" aria-live="polite"><?= e(field_error($errors, 'category')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="direction">Direction <span aria-hidden="true">*</span></label>
                            <select id="direction" name="direction" required data-direction-select data-error-message="Select incoming or outgoing." aria-describedby="direction-error" aria-invalid="<?= isset($errors['direction']) ? 'true' : 'false' ?>">
                                <option value="">Select direction</option>
                                <?php foreach ($directions as $direction): ?>
                                    <option value="<?= e($direction) ?>"<?= $values['direction'] === $direction ? ' selected' : '' ?>><?= e($direction) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="field-error" id="direction-error" aria-live="polite"><?= e(field_error($errors, 'direction')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="document_date">Document date <span aria-hidden="true">*</span></label>
                            <input id="document_date" name="document_date" type="date" value="<?= e($values['document_date']) ?>" max="<?= e($today) ?>" required data-error-message="Document date is required." aria-describedby="document_date-error" aria-invalid="<?= isset($errors['document_date']) ? 'true' : 'false' ?>">
                            <p class="field-error" id="document_date-error" aria-live="polite"><?= e(field_error($errors, 'document_date')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="recorded_date">Date recorded</label>
                            <input id="recorded_date" type="date" value="<?= e($today) ?>" readonly aria-readonly="true">
                            <p class="field-hint">Automatically assigned by the system.</p>
                        </div>
                    </div>
                </section>

                <section class="form-section" aria-labelledby="routing-information-title">
                    <div class="form-section__heading">
                        <span>02</span>
                        <h2 id="routing-information-title">Sender or recipient</h2>
                    </div>

                    <div class="form-grid">
                        <div class="form-field">
                            <label for="party_name" data-party-label>Sender / Recipient <span aria-hidden="true">*</span></label>
                            <input id="party_name" name="party_name" type="text" value="<?= e($values['party_name']) ?>" required data-error-message="Sender or recipient is required." aria-describedby="party_name-error" aria-invalid="<?= isset($errors['party_name']) ? 'true' : 'false' ?>">
                            <p class="field-error" id="party_name-error" aria-live="polite"><?= e(field_error($errors, 'party_name')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="transaction_date" data-transaction-label>Date received / released <span aria-hidden="true">*</span></label>
                            <input id="transaction_date" name="transaction_date" type="date" value="<?= e($values['transaction_date']) ?>" max="<?= e($today) ?>" required data-error-message="Received or released date is required." aria-describedby="transaction_date-error" aria-invalid="<?= isset($errors['transaction_date']) ? 'true' : 'false' ?>">
                            <p class="field-error" id="transaction_date-error" aria-live="polite"><?= e(field_error($errors, 'transaction_date')) ?></p>
                        </div>
                    </div>
                </section>

                <section class="form-section" aria-labelledby="processing-information-title">
                    <div class="form-section__heading">
                        <span>03</span>
                        <h2 id="processing-information-title">Processing details</h2>
                    </div>

                    <div class="form-grid">
                        <div class="form-field form-field--wide">
                            <label for="description">Subject or description <span aria-hidden="true">*</span></label>
                            <textarea id="description" name="description" rows="3" required data-error-message="Subject or description is required." aria-describedby="description-error" aria-invalid="<?= isset($errors['description']) ? 'true' : 'false' ?>"><?= e($values['description']) ?></textarea>
                            <p class="field-error" id="description-error" aria-live="polite"><?= e(field_error($errors, 'description')) ?></p>
                        </div>

                        <div class="form-field">
                            <label for="status">Initial status</label>
                            <input id="status" name="status" type="text" value="Pending" readonly aria-readonly="true">
                            <p class="field-hint">New documents always begin as Pending.</p>
                        </div>

                        <div class="form-field">
                            <label for="recorded_by">Recorded by</label>
                            <input id="recorded_by" type="text" value="<?= e(current_user()['full_name'] ?? 'Barangay Secretary') ?>" readonly aria-readonly="true">
                            <p class="field-hint">Uses the active secretary account.</p>
                        </div>

                        <div class="form-field form-field--wide">
                            <label for="remarks">Remarks <span class="optional">OPTIONAL</span></label>
                            <textarea id="remarks" name="remarks" rows="2"><?= e($values['remarks']) ?></textarea>
                        </div>
                    </div>
                </section>

                <footer class="form-actions">
                    <a href="index.php">Cancel</a>
                    <button type="submit" data-document-submit>
                        <span data-submit-label>Save document</span>
                        <span aria-hidden="true">-&gt;</span>
                    </button>
                </footer>
            </form>
        </div>
    </main>

    <script src="../assets/js/app.js" defer></script>
</body>
</html>
