<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_auth('../login.php');
require __DIR__ . '/../includes/react-shell.php';

$locations = [];
$records = [];
$location = null;
$error = '';
$barangayName = '';
$selectionRequested = array_key_exists('location_id', $_GET);
$locationId = filter_input(INPUT_GET, 'location_id', FILTER_VALIDATE_INT);

if (!user_has_role('Secretary')) {
    http_response_code(403);
    $error = 'Only the barangay secretary can open the folder inventory.';
} else {
    try {
        $connection = db();
        $locations = $connection->query(
            'SELECT l.location_id, l.cabinet, l.drawer, l.folder
             FROM Storage_Locations l
             WHERE l.type_id IS NOT NULL OR EXISTS (
                 SELECT 1 FROM Documents d
                 WHERE d.location_id = l.location_id
                   AND NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = d.document_id)
             )
             ORDER BY l.cabinet, l.drawer, l.folder'
        )->fetchAll(PDO::FETCH_UNIQUE);

        if ($selectionRequested) {
            $location = is_int($locationId) ? ($locations[$locationId] ?? null) : null;
            if ($location === null) {
                http_response_code(404);
                $error = 'That folder could not be found. Choose another folder.';
            } else {
                $statement = $connection->prepare(
                    'SELECT d.document_id, d.tracking_number, d.title, d.created_at
                     FROM Documents d
                     WHERE d.location_id = ?
                       AND NOT EXISTS (SELECT 1 FROM Document_Recycle_Bin b WHERE b.document_id = d.document_id)
                     ORDER BY d.tracking_number'
                );
                $statement->execute([$locationId]);
                $records = $statement->fetchAll();
                $barangayName = (string) $connection->query(
                    'SELECT barangay_name FROM Barangay_Profile WHERE profile_id = 1'
                )->fetchColumn();
            }
        }
    } catch (PDOException $exception) {
        http_response_code(503);
        error_log('B-TRACK folder inventory load error: ' . $exception->getMessage());
        $error = 'The folder inventory could not be loaded. Refresh the page to try again.';
    }
}

$inventoryPageActive = true;
$breadcrumbItems = [['Overview', '../dashboard.php'], ['Folder inventory']];
$path = $location ? storage_location_code($location['cabinet'], $location['drawer'], $location['folder']) : '';
$generated = new DateTimeImmutable();
$inventoryReady = $location !== null && $error === '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Folder inventory / B-TRACK</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <script src="../assets/js/page-transition.js" defer blocking="render"></script>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <link rel="stylesheet" href="../assets/css/ui-system.css">
</head>
<body class="folder-inventory-page">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <div class="app-shell records-workspace">
        <?php require dirname(__DIR__) . '/includes/document-sidebar.php'; ?>

        <main class="main-content" id="main-content">
            <div class="records-content">
                <header class="records-header">
                    <?php require dirname(__DIR__) . '/includes/menu-button.php'; ?>
                    <?php require dirname(__DIR__) . '/includes/breadcrumb.php'; ?>
                </header>

                <header class="record-detail-heading inventory-heading">
                    <div class="record-detail-title">
                        <h1>Folder inventory</h1>
                    </div>
                    <?php if ($inventoryReady): ?>
                        <button class="button button--primary inventory-print" type="button" data-print-inventory hidden>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 9V4h12v5M6 17H4V9h16v8h-2M6 14h12v6H6zM16 11h1"></path></svg>
                            Print inventory
                        </button>
                    <?php endif; ?>
                </header>

                <?php if ($locations !== []): ?>
                    <form class="record-detail-panel inventory-selection" action="inventory.php" method="get" data-inventory-selection>
                        <label class="modal-field" hidden>
                            <span>Cabinet</span>
                            <select data-inventory-cabinet disabled></select>
                        </label>
                        <label class="modal-field" hidden>
                            <span>Drawer</span>
                            <select data-inventory-drawer disabled></select>
                        </label>
                        <label class="modal-field">
                            <span>Folder</span>
                            <select name="location_id" required data-inventory-folder>
                                <option value="">Choose a folder</option>
                                <?php foreach ($locations as $id => $folder): ?>
                                    <option value="<?= e($id) ?>" data-cabinet="<?= e($folder['cabinet']) ?>" data-drawer="<?= e($folder['drawer']) ?>" data-folder="<?= e($folder['folder']) ?>"<?= $id === $locationId ? ' selected' : '' ?>><?= e(storage_location_code($folder['cabinet'], $folder['drawer'], $folder['folder'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <button class="button <?= $inventoryReady ? 'button--secondary' : 'button--primary' ?>" type="submit">View inventory</button>
                    </form>
                <?php endif; ?>

                <?php if ($error !== ''): ?>
                    <p class="inventory-message form-notice--error" role="alert"><?= e($error) ?></p>
                <?php elseif ($location === null): ?>
                    <p class="inventory-message"><?= $locations === [] ? 'No storage folders are configured.' : 'Choose a folder to view its inventory.' ?></p>
                <?php else: ?>
                    <section class="inventory-results" aria-labelledby="inventory-location-title">
                        <header class="inventory-results__header">
                            <div>
                                <?php if ($barangayName !== ''): ?><p><?= e($barangayName) ?></p><?php endif; ?>
                                <h2 id="inventory-location-title"><?= e($path) ?></h2>
                            </div>
                            <p><?= count($records) ?> <?= count($records) === 1 ? 'document' : 'documents' ?> · <time datetime="<?= e($generated->format(DATE_ATOM)) ?>"><?= e($generated->format('M j, Y g:i A')) ?></time></p>
                        </header>

                        <?php if ($records === []): ?>
                            <p class="inventory-message">No active documents are filed in this folder.</p>
                        <?php else: ?>
                            <div class="record-table-wrap" tabindex="0" aria-label="Folder inventory records">
                                <table class="record-table inventory-table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Tracking no.</th>
                                            <th scope="col">Document title</th>
                                            <th scope="col">Date recorded</th>
                                            <th scope="col" class="inventory-found">Found</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($records as $record): ?>
                                            <?php $detailQuery = http_build_query(['id' => $record['document_id'], 'from' => 'inventory', 'inventory_location' => $locationId]); ?>
                                            <tr>
                                                <td><?= e($record['tracking_number']) ?></td>
                                                <td><a class="record-title" href="view.php?<?= e($detailQuery) ?>"><?= e($record['title']) ?></a></td>
                                                <td><time datetime="<?= e(substr($record['created_at'], 0, 10)) ?>"><?= e(substr($record['created_at'], 0, 10)) ?></time></td>
                                                <td class="inventory-found"><span class="inventory-check" aria-hidden="true"></span></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            </div>
        </main>
    </div>
    <script src="../assets/js/app.js" defer></script>
</body>
</html>
