<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');

if (!is_post_request()) {
    redirect('index.php');
}

$documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($documentId) || $documentId < 1) {
    flash('error', 'Record not found', 'Return to the records list and open the document again.');
    redirect('index.php');
}

$editUrl = 'edit.php?id=' . $documentId;
$values = [
    'title' => post_value('title'),
    'category' => post_value('category'),
    'direction' => post_value('direction'),
    'sender_recipient' => post_value('sender_recipient'),
    'date_received' => post_value('date_received'),
    'location_id' => post_value('location_id'),
];
$errors = [];

if (!verify_csrf_token(isset($_POST['_token']) && is_string($_POST['_token']) ? $_POST['_token'] : null)) {
    $errors['_form'] = 'This form expired. Review the details and save again.';
}

$characterLength = static function (string $value): int {
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
};

if ($values['title'] === '') {
    $errors['title'] = 'Enter a document title.';
} elseif ($characterLength($values['title']) > 150) {
    $errors['title'] = 'The title must be 150 characters or fewer.';
}

$categoryId = filter_var($values['category'], FILTER_VALIDATE_INT);
if (!is_int($categoryId) || $categoryId < 1) {
    $errors['category'] = 'Choose a document category.';
}

if (!in_array($values['direction'], ['Incoming', 'Outgoing'], true)) {
    $errors['direction'] = 'Choose Incoming or Outgoing.';
}

if ($values['sender_recipient'] === '') {
    $errors['sender_recipient'] = 'Enter the sender or recipient.';
} elseif ($characterLength($values['sender_recipient']) > 100) {
    $errors['sender_recipient'] = 'The sender or recipient must be 100 characters or fewer.';
}

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $values['date_received']);
$dateErrors = DateTimeImmutable::getLastErrors();
$validDate = $date !== false
    && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0))
    && $date->format('Y-m-d') === $values['date_received'];

if (!$validDate) {
    $errors['date_received'] = 'Enter a valid received or released date.';
} elseif ($date > new DateTimeImmutable('today')) {
    $errors['date_received'] = 'The received or released date cannot be in the future.';
}

$locationId = filter_var($values['location_id'], FILTER_VALIDATE_INT);
if (!is_int($locationId) || $locationId < 1) {
    $errors['location_id'] = 'Choose a folder for the original paper copy.';
}

if ($errors === []) {
    try {
        $typeQuery = db()->prepare('SELECT type_id FROM Document_Types WHERE type_id = :type_id LIMIT 1');
        $typeQuery->execute(['type_id' => $categoryId]);
        if (!$typeQuery->fetch()) {
            $errors['category'] = 'That document category is no longer available.';
        }

        $locationQuery = db()->prepare(
            'SELECT type_id FROM Storage_Locations WHERE location_id = :location_id LIMIT 1'
        );
        $locationQuery->execute(['location_id' => $locationId]);
        $locationTypeId = $locationQuery->fetchColumn();

        if ($locationTypeId === false) {
            $errors['location_id'] = 'That folder is no longer available. Choose another folder.';
        } elseif ((int) $locationTypeId !== $categoryId) {
            $errors['location_id'] = 'Choose a folder assigned to the selected document category.';
        }
    } catch (PDOException $exception) {
        error_log('B-TRACK document edit validation error: ' . $exception->getMessage());
        $errors['_form'] = 'The record could not be checked right now. Please try again.';
    }
}

if ($errors !== []) {
    $_SESSION['_document_edit_draft'] = [
        'document_id' => $documentId,
        'values' => $values,
        'errors' => $errors,
    ];
    redirect($editUrl);
}

$user = current_user();
$userId = filter_var($user['id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($userId) || $userId < 1) {
    flash('error', 'Sign in again', 'Your session could not be verified.');
    redirect('../login.php?notice=unauthorized');
}

$connection = db();
try {
    $connection->beginTransaction();

    $userQuery = $connection->prepare('SELECT status FROM Users WHERE user_id = :user_id FOR UPDATE');
    $userQuery->execute(['user_id' => $userId]);
    if ($userQuery->fetchColumn() !== 'Active') {
        $connection->rollBack();
        $_SESSION['_document_edit_draft'] = [
            'document_id' => $documentId,
            'values' => $values,
            'errors' => ['_form' => 'Only an active secretary account can update document details.'],
        ];
        redirect($editUrl);
    }

    $recordQuery = $connection->prepare(
        'SELECT type_id, location_id FROM Documents WHERE document_id = :document_id FOR UPDATE'
    );
    $recordQuery->execute(['document_id' => $documentId]);
    $currentRecord = $recordQuery->fetch();

    if (!$currentRecord) {
        $connection->rollBack();
        flash('error', 'Record not found', 'This document may have been removed from the database.');
        redirect('index.php');
    }

    $updateQuery = $connection->prepare(
        'UPDATE Documents
         SET title = :title,
             type_id = :type_id,
             direction = :direction,
             sender_recipient = :sender_recipient,
             date_received = :date_received
         WHERE document_id = :document_id'
    );
    $updateQuery->execute([
        'title' => $values['title'],
        'type_id' => $categoryId,
        'direction' => $values['direction'],
        'sender_recipient' => $values['sender_recipient'],
        'date_received' => $values['date_received'],
        'document_id' => $documentId,
    ]);

    if ((int) $currentRecord['location_id'] !== $locationId) {
        $moveQuery = $connection->prepare('CALL sp_move_document(:document_id, :location_id, :user_id, :remarks)');
        $moveQuery->execute([
            'document_id' => $documentId,
            'location_id' => $locationId,
            'user_id' => $userId,
            'remarks' => 'Original paper location updated while editing document details.',
        ]);
        $moveQuery->fetchAll();
        $moveQuery->closeCursor();
    }

    $connection->commit();
    flash('success', 'Saved', 'Document details saved.');
    redirect('view.php?id=' . $documentId);
} catch (PDOException $exception) {
    if ($connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('B-TRACK document edit save error: ' . $exception->getMessage());
    $databaseMessage = (string) ($exception->errorInfo[2] ?? '');
    $safeBusinessMessages = [
        'Choose a storage location inside the cabinet for this document category.',
        'Only Active accounts can record or update documents.',
        'Document not found.',
    ];
    $formMessage = in_array($databaseMessage, $safeBusinessMessages, true)
        ? $databaseMessage
        : 'The document could not be saved. Your changes are still available in the form; please try again.';

    $_SESSION['_document_edit_draft'] = [
        'document_id' => $documentId,
        'values' => $values,
        'errors' => ['_form' => $formMessage],
    ];
    redirect($editUrl);
}
