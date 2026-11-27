<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');

if (!is_post_request()) {
    redirect('index.php');
}

$documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
if (!is_int($documentId) || $documentId < 1) {
    flash('error', 'Record not found', 'Open a document from the records list.');
    redirect('index.php', 303);
}

$recordUrl = 'view.php?id=' . $documentId;
$expectedStatus = post_value('current_status');
$nextStatus = next_document_status($expectedStatus);
$remarks = post_value('remarks');
$userId = filter_var(current_user()['id'] ?? null, FILTER_VALIDATE_INT);

$reject = static function (string $message) use ($recordUrl): void {
    flash('error', 'Status not changed', $message);
    redirect($recordUrl, 303);
};

if (!user_has_role('Secretary') || !is_int($userId) || $userId < 1) {
    $reject('Only an active secretary can change document status.');
}

if (!verify_csrf_token(post_value('_token'))) {
    $reject('Your form expired. Open Change status and try again.');
}

if ($nextStatus === null) {
    $reject('Review the current status before trying again.');
}

$reopenForm = static function (string $message, bool $invalidRemarks) use ($documentId, $recordUrl, $expectedStatus, $remarks): void {
    $_SESSION['_document_status_draft'] = [
        'document_id' => $documentId,
        'status' => $expectedStatus,
        'remarks' => mb_substr($remarks, 0, 255, 'UTF-8'),
        'message' => $message,
        'invalid_remarks' => $invalidRemarks,
    ];
    redirect($recordUrl . '&change-status=1', 303);
};

if ($remarks === '') {
    $reopenForm('Enter a remark explaining the status change.', true);
}

if (mb_strlen($remarks, 'UTF-8') > 255) {
    $reopenForm('Keep the remark to 255 characters or fewer.', true);
}

$connection = null;
try {
    $connection = db();
    $connection->beginTransaction();

    $userQuery = $connection->prepare('SELECT status FROM Users WHERE user_id = :user_id FOR UPDATE');
    $userQuery->execute(['user_id' => $userId]);
    if ($userQuery->fetchColumn() !== 'Active') {
        $connection->rollBack();
        $reject('Only an active secretary can change document status.');
    }

    $recordQuery = $connection->prepare('SELECT status FROM Documents WHERE document_id = :document_id FOR UPDATE');
    $recordQuery->execute(['document_id' => $documentId]);
    $currentStatus = $recordQuery->fetchColumn();

    if ($currentStatus === false) {
        $connection->rollBack();
        flash('error', 'Record not found', 'This document could not be found.');
        redirect('index.php', 303);
    }

    if ($currentStatus !== $expectedStatus) {
        $connection->rollBack();
        $reject('The status changed since you opened the form. Review the current status before continuing.');
    }

    $statement = $connection->prepare(
        'CALL sp_update_document_status(:document_id, :new_status, :user_id, :remarks)'
    );
    $statement->execute([
        'document_id' => $documentId,
        'new_status' => $nextStatus,
        'user_id' => $userId,
        'remarks' => $remarks,
    ]);
    $statement->closeCursor();
    $connection->commit();
} catch (PDOException $exception) {
    if ($connection && $connection->inTransaction()) {
        $connection->rollBack();
    }

    error_log('B-TRACK document status save error: ' . $exception->getMessage());
    $reopenForm('The status could not be saved. Review your remark and try again.', false);
}

flash('success', 'Status updated', 'Status updated to ' . $nextStatus . '.');
redirect($recordUrl, 303);
