<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_auth('../login.php');
require __DIR__ . '/../includes/react-shell.php';

$barangayName = 'BARANGAY SAN ROQUE';
try {
    $savedBarangayName = db()->query('SELECT barangay_name FROM Barangay_Profile WHERE profile_id = 1')->fetchColumn();
    if (is_string($savedBarangayName) && $savedBarangayName !== '') {
        $barangayName = $savedBarangayName;
    }
} catch (PDOException $exception) {
    error_log('B-TRACK barangay name load error: ' . $exception->getMessage());
}

$databaseError = '';
$notice = pull_flash();

try {
    $documents = fetch_documents();
} catch (PDOException $exception) {
    error_log('B-TRACK document list database error: ' . $exception->getMessage());
    $documents = [];
    $databaseError = 'Unable to load records from the database. Check that MySQL is running.';
}

$listFilters = document_list_filters();
[, , $searchQuery, $selectedYear, $selectedStatus] = $listFilters;

$filterWasSubmitted = $searchQuery !== ''
    || $selectedStatus !== ''
    || (array_key_exists('year', $_GET) && $selectedYear !== '');

$filteredDocuments = array_values(array_filter(
    $documents,
    static function (array $document) use ($searchQuery, $selectedYear, $selectedStatus): bool {
        $matchesSearch = $searchQuery === ''
            || stripos((string) ($document['tracking'] ?? ''), $searchQuery) !== false
            || stripos((string) ($document['title'] ?? ''), $searchQuery) !== false;

        $recordedDate = (string) ($document['recorded'] ?? $document['recorded_at'] ?? '');
        $timestamp = $recordedDate !== '' ? strtotime($recordedDate) : false;
        $recordYear = $timestamp !== false ? date('Y', $timestamp) : '';
        $matchesYear = $selectedYear === '' || $recordYear === $selectedYear;
        $matchesStatus = $selectedStatus === ''
            || strcasecmp((string) ($document['status'] ?? ''), $selectedStatus) === 0;

        return $matchesSearch && $matchesYear && $matchesStatus;
    }
));

usort(
    $filteredDocuments,
    static function (array $first, array $second): int {
        $firstDate = strtotime((string) ($first['recorded'] ?? $first['recorded_at'] ?? '')) ?: 0;
        $secondDate = strtotime((string) ($second['recorded'] ?? $second['recorded_at'] ?? '')) ?: 0;

        return $secondDate <=> $firstDate;
    }
);

$viewQuery = array_intersect_key($_GET, array_flip(['search', 'year', 'status']));
$querySuffix = $viewQuery === [] ? '' : '?' . http_build_query($viewQuery);
$boardUrl = '../dashboard.php' . $querySuffix;
$listUrl = 'index.php' . $querySuffix;
$documentListActive = true;
$breadcrumbItems = [['Overview', $boardUrl], ['All records']];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="B-TRACK barangay document list">
    <title>B-TRACK / Document List</title>
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

                <section class="page-heading" aria-labelledby="page-title">
                    <div class="page-title-lockup">
                        <svg class="barangay-mark" viewBox="14 4 36 54" aria-hidden="true" focusable="false">
                            <path d="M30 4h2v15h-2zM32 5h13l-3 6H32z"></path>
                            <path d="M17 28 31 20l16 8v3H17zM16 32h32v3H16z"></path>
                            <path d="M19 36h4v12h-4zM27 36h4v12h-4zM35 36h4v12h-4zM43 36h4v12h-4z"></path>
                            <path d="M15 49h34v4H15zM14 54h36v4H14z"></path>
                        </svg>
                        <div class="page-title-copy">
                            <h1 id="page-title" title="<?= e($barangayName) ?>"><?= e($barangayName) ?></h1>
                            <p>Santo Domingo, Albay, Philippines</p>
                        </div>
                    </div>
                </section>

                <div class="workspace-toolbar records-toolbar" aria-label="Document controls">
                    <nav class="dashboard-tabs" aria-label="Record views">
                        <a href="<?= e($boardUrl) ?>">Board</a>
                        <a class="is-active" aria-current="page" href="<?= e($listUrl) ?>">List</a>
                    </nav>
                    <div class="dashboard-toolbar__actions">
                        <a class="button button--primary dashboard-record-button" href="create.php"><span aria-hidden="true">+</span> Record document</a>
                    </div>
                </div>

                <section class="record-list-section" aria-labelledby="record-list-title" data-list-region>
                    <h2 class="sr-only" id="record-list-title">All documents</h2>

                    <?php if ($notice): ?>
                        <div class="form-notice form-notice--success" role="status">
                            <span>[ OK ]</span>
                            <div><strong><?= e($notice['title'] ?? 'Saved') ?></strong><p><?= e($notice['message'] ?? '') ?></p></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($databaseError !== ''): ?>
                        <div class="form-notice" role="alert"><strong><?= e($databaseError) ?></strong></div>
                    <?php endif; ?>

                    <div class="list-loading" role="status" data-list-loading hidden>
                        <span class="loading-indicator" aria-hidden="true"></span>
                        <span>Loading records</span>
                    </div>

                    <?php if (count($filteredDocuments) === 0): ?>
                        <div class="list-state" role="status">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h11l5 5v13H4z"></path><path d="M15 3v6h5M8 13h8M8 17h5"></path></svg>
                            <?php if ($filterWasSubmitted): ?>
                                <span>[ NO MATCHES ]</span>
                                <h3>No records match your search</h3>
                                <p>Try another keyword or remove one of the selected filters.</p>
                                <a href="index.php?year=">Clear search and filters</a>
                            <?php else: ?>
                                <span>[ EMPTY LIST ]</span>
                                <h3>No documents recorded yet</h3>
                                <p>Recorded incoming and outgoing documents will appear here.</p>
                                <a href="create.php">Record the first document</a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="record-table-wrap" tabindex="0" aria-label="Document records table. Scroll horizontally to view all columns.">
                            <table class="record-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Tracking no.</th>
                                        <th scope="col">Document</th>
                                        <th scope="col">Direction</th>
                                        <th scope="col">Category</th>
                                        <th scope="col">Recorded</th>
                                        <th scope="col">Status</th>
                                        <th scope="col"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filteredDocuments as $document): ?>
                                        <?php
                                        $statusClass = strtolower(str_replace(' ', '-', (string) ($document['status'] ?? '')));
                                        $documentTitle = (string) ($document['title'] ?? 'Untitled document');
                                        $documentCategory = (string) ($document['category'] ?? 'Unfiled');
                                        $recordUrl = 'view.php?' . http_build_query(['id' => $document['id'], 'from' => 'list'] + $viewQuery);
                                        ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars((string) ($document['tracking'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                            <td><a class="record-title" href="<?= e($recordUrl) ?>" title="<?= e($documentTitle) ?>"><?= e($documentTitle) ?></a></td>
                                            <td><?= htmlspecialchars((string) ($document['direction'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td title="<?= e($documentCategory) ?>"><?= e($documentCategory) ?></td>
                                            <td><?= htmlspecialchars((string) ($document['recorded'] ?? $document['recorded_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><span class="record-status record-status--<?= e($statusClass) ?>"><?= e($document['status'] ?? 'Unknown') ?></span></td>
                                            <td><a class="row-action" href="<?= e($recordUrl) ?>" aria-label="Open <?= htmlspecialchars((string) ($document['tracking'] ?? 'document'), ENT_QUOTES, 'UTF-8') ?>">Open -&gt;</a></td>
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
