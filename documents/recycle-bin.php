<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');
require __DIR__ . '/../includes/react-shell.php';

$canManage = user_has_role('Secretary');
$browseQuery = document_browse_query();
$browseSuffix = $browseQuery === [] ? '' : '?' . http_build_query($browseQuery);

if (is_post_request()) {
    $action = post_value('action');
    $documentId = filter_var($_POST['document_id'] ?? null, FILTER_VALIDATE_INT);
    $validDocument = is_int($documentId) && $documentId > 0;
    $moveToBin = $action === 'move';
    $returnUrl = $moveToBin && $validDocument
        ? 'view.php?' . http_build_query(['id' => $documentId] + $browseQuery)
        : 'recycle-bin.php';

    if (!$canManage || !verify_csrf_token(post_value('_token'))) {
        flash('error', 'Action blocked', 'Your session could not be verified. Reopen the record and try again.');
        redirect($returnUrl, 303);
    }

    if (!$validDocument || !in_array($action, ['move', 'restore'], true)) {
        flash('error', 'Invalid request', 'Open the record again and retry.');
        redirect($returnUrl, 303);
    }

    $connection = null;
    try {
        $connection = db();
        $connection->beginTransaction();

        $userId = (int) current_user()['id'];
        $userQuery = $connection->prepare('SELECT status FROM Users WHERE user_id = :user_id FOR UPDATE');
        $userQuery->execute(['user_id' => $userId]);
        if ($userQuery->fetchColumn() !== 'Active') {
            throw new DomainException('Only an active secretary can move or restore records.');
        }

        $recordQuery = $connection->prepare(
            'SELECT tracking_number FROM Documents WHERE document_id = :document_id FOR UPDATE'
        );
        $recordQuery->execute(['document_id' => $documentId]);
        $tracking = $recordQuery->fetchColumn();
        if ($tracking === false) {
            throw new DomainException('This record could not be found.');
        }

        $isRecycled = document_is_recycled($connection, $documentId);
        if ($moveToBin && !$isRecycled) {
            $statement = $connection->prepare(
                'INSERT INTO Document_Recycle_Bin (document_id, deleted_by) VALUES (:document_id, :user_id)'
            );
            $statement->execute(['document_id' => $documentId, 'user_id' => $userId]);
        } elseif (!$moveToBin && $isRecycled) {
            $statement = $connection->prepare('DELETE FROM Document_Recycle_Bin WHERE document_id = :document_id');
            $statement->execute(['document_id' => $documentId]);
        }

        $connection->commit();
        $message = $moveToBin
            ? $tracking . ' moved to the Recycle Bin.'
            : $tracking . ' restored to active records.';
        flash('success', $moveToBin ? 'Moved to Recycle Bin' : 'Record restored', $message);
        $browseParents = document_breadcrumb_parents($browseQuery);
        $browseReturnUrl = $browseParents[array_key_last($browseParents)][1];
        redirect($moveToBin ? $browseReturnUrl : 'recycle-bin.php', 303);
    } catch (DomainException | PDOException $exception) {
        if ($connection && $connection->inTransaction()) {
            $connection->rollBack();
        }

        if ($exception instanceof PDOException) {
            error_log('B-TRACK recycle bin action error: ' . $exception->getMessage());
        }

        $message = $exception instanceof DomainException
            ? $exception->getMessage()
            : 'The record could not be updated. Please try again.';
        flash('error', 'Record could not be updated', $message);
        redirect($returnUrl, 303);
    }
}

$records = [];
$loadError = false;
if ($canManage) {
    try {
        $records = db()->query(
            'SELECT d.document_id, d.tracking_number, d.title, b.deleted_at, u.full_name AS deleted_by
             FROM Document_Recycle_Bin b
             JOIN Documents d ON d.document_id = b.document_id
             JOIN Users u ON u.user_id = b.deleted_by
             ORDER BY b.deleted_at DESC, b.document_id DESC'
        )->fetchAll();
    } catch (PDOException $exception) {
        error_log('B-TRACK recycle bin load error: ' . $exception->getMessage());
        $loadError = true;
    }
} else {
    http_response_code(403);
}

$notice = pull_flash();
$recycleBinActive = true;
$breadcrumbItems = [['Overview', '../dashboard.php' . $browseSuffix], ['Recycle Bin']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin / B-TRACK</title>
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

        <main class="main-content" id="main-content">
            <div class="records-content">
                <header class="records-header">
                    <?php require dirname(__DIR__) . '/includes/menu-button.php'; ?>
                    <?php require dirname(__DIR__) . '/includes/breadcrumb.php'; ?>
                </header>

                <?php if ($notice): ?>
                    <div class="dashboard-toast dashboard-toast--<?= e($notice['type']) ?>" role="<?= $notice['type'] === 'error' ? 'alert' : 'status' ?>">
                        <span><?= e($notice['message']) ?></span>
                    </div>
                <?php endif; ?>

                <section class="record-list-section recycle-bin" aria-labelledby="recycle-bin-title">
                    <header class="page-heading">
                        <div class="page-title-copy">
                            <h1 id="recycle-bin-title">Recycle Bin</h1>
                        </div>
                    </header>

                    <?php if (!$canManage || $loadError): ?>
                        <div class="form-notice form-notice--error" role="alert">
                            <?= !$canManage ? 'Only the barangay secretary can access the Recycle Bin.' : 'The Recycle Bin could not be loaded. Refresh the page to try again.' ?>
                        </div>
                    <?php elseif ($records === []): ?>
                        <div class="list-state">
                            <strong>The Recycle Bin is empty</strong>
                            <p>Records you move to the bin will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="record-table-wrap" tabindex="0" aria-label="Records in the Recycle Bin">
                            <table class="record-table recycle-bin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Code</th>
                                        <th scope="col">Document title</th>
                                        <th scope="col">Moved to bin</th>
                                        <th scope="col">Moved by</th>
                                        <th scope="col">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($records as $record): ?>
                                        <tr>
                                            <td><strong><?= e($record['tracking_number']) ?></strong></td>
                                            <td><a class="record-title" href="view.php?id=<?= e($record['document_id']) ?>"><?= e($record['title']) ?></a></td>
                                            <td><time datetime="<?= e(str_replace(' ', 'T', $record['deleted_at'])) ?>"><?= e($record['deleted_at']) ?></time></td>
                                            <td><?= e($record['deleted_by']) ?></td>
                                            <td>
                                                <form action="recycle-bin.php" method="post">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="document_id" value="<?= e($record['document_id']) ?>">
                                                    <button class="button button--secondary" name="action" value="restore" type="submit" aria-label="Restore <?= e($record['tracking_number']) ?>">Restore</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
