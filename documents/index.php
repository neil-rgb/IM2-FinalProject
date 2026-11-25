<?php
require_once dirname(__DIR__) . '/includes/auth.php';
require_auth('../login.php');

$barangayName = 'BARANGAY NAME';
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

$allowedYears = ['2026', '2025'];
$allowedStatuses = ['Pending', 'In Process', 'Completed'];
$searchQuery = trim((string) ($_GET['search'] ?? ''));
$selectedYear = array_key_exists('year', $_GET) ? trim((string) $_GET['year']) : '2026';
$selectedStatus = trim((string) ($_GET['status'] ?? ''));

if ($selectedYear !== '' && !in_array($selectedYear, $allowedYears, true)) {
    $selectedYear = '';
}

if ($selectedStatus !== '' && !in_array($selectedStatus, $allowedStatuses, true)) {
    $selectedStatus = '';
}

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

$activeFilterCount = ($searchQuery !== '' ? 1 : 0)
    + ($selectedStatus !== '' ? 1 : 0)
    + ($filterWasSubmitted && $selectedYear !== '' ? 1 : 0);
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
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <div class="app-shell">
        <aside class="sidebar" id="app-sidebar" aria-label="Primary navigation" aria-hidden="true">
            <div class="brand-lockup">
                <span class="brand-mark" aria-hidden="true">BT</span>
                <div>
                    <p class="brand-name">B-TRACK</p>
                    <p class="brand-caption">Document control</p>
                </div>
            </div>

            <div class="operator-panel">
                <span>SECRETARY</span>
                <strong>Barangay Secretary</strong>
            </div>

            <form class="sign-out" action="../logout.php" method="post">
                <?= csrf_field() ?>
                <button type="submit">
                    <span>Sign out</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"></path></svg>
                </button>
            </form>
        </aside>

        <button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Close navigation" hidden></button>

        <main class="main-content" id="main-content">
            <header class="system-bar">
                <div>
                    <div class="topbar-brand" aria-label="B-TRACK">
                        <span class="topbar-brand-mark" aria-hidden="true">BT</span>
                        <span class="topbar-brand-name">B-TRACK</span>
                    </div>
                    <button class="menu-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                </div>
            </header>

            <section class="page-heading" aria-labelledby="page-title">
                <div>
                    <h1 id="page-title"><?= htmlspecialchars($barangayName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p>Santo Domingo, Albay, Philippines</p>
                </div>
            </section>

            <section class="workspace-toolbar" aria-label="Document list controls">
                <form class="filters" action="index.php" method="get" data-search-filters>
                    <div class="search-control">
                        <label class="search-field">
                            <span class="sr-only">Search documents</span>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 5 5"></path></svg>
                            <input type="search" name="search" value="<?= htmlspecialchars($searchQuery, ENT_QUOTES, 'UTF-8') ?>" placeholder="Search tracking number or title">
                        </label>
                        <button class="filter-toggle" type="button" aria-label="Open search filters" aria-controls="search-filter-panel" aria-expanded="false" data-filter-toggle>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                            </svg>
                            <?php if ($activeFilterCount > 0): ?>
                                <span class="filter-count" aria-label="<?= $activeFilterCount ?> active filters"><?= $activeFilterCount ?></span>
                            <?php endif; ?>
                        </button>
                        <button class="search-submit" type="submit" aria-label="Search documents" title="Search documents">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 5 5"></path></svg>
                        </button>
                    </div>

                    <div class="filter-popover" id="search-filter-panel" data-filter-panel hidden>
                        <div class="filter-popover__heading">
                            <span>FILTER SEARCH</span>
                            <span><?= $activeFilterCount ?> ACTIVE</span>
                        </div>
                        <div class="filter-options">
                            <label>
                                <span>Year</span>
                                <select name="year">
                                    <option value="">All years</option>
                                    <?php foreach ($allowedYears as $year): ?>
                                        <option value="<?= $year ?>"<?= $selectedYear === $year ? ' selected' : '' ?>>Year / <?= $year ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label>
                                <span>Status</span>
                                <select name="status">
                                    <option value="">All statuses</option>
                                    <?php foreach ($allowedStatuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>"<?= $selectedStatus === $status ? ' selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                        </div>
                        <div class="filter-actions">
                            <a href="index.php?year=">Clear filters</a>
                            <button type="submit">Apply filters</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="record-list-section" aria-labelledby="record-list-title" data-list-region>
                <header class="section-heading record-list-heading">
                    <div>
                        <span>DIGITAL RECORDS INDEX</span>
                        <h2 id="record-list-title">All documents</h2>
                    </div>
                    <div class="record-list-heading__right">
                        <p><strong><?= number_format(count($filteredDocuments)) ?></strong> records shown</p>
                        <div class="section-actions">
                            <a class="button button--primary" href="create.php" aria-label="Record document"><span aria-hidden="true">+</span> Record document</a>
                            <div class="view-tabs" aria-label="Document views">
                                <a href="../dashboard.php" aria-label="Grid View" title="Grid View">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
                                    <span class="sr-only">Grid View</span>
                                </a>
                                <a class="is-active" href="index.php" aria-current="page" aria-label="List View" title="List View">
                                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"></path><rect x="3" y="4" width="2" height="2"></rect><rect x="3" y="10" width="2" height="2"></rect><rect x="3" y="16" width="2" height="2"></rect></svg>
                                    <span class="sr-only">List view</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </header>

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
                                    <?php $statusClass = strtolower(str_replace(' ', '-', (string) ($document['status'] ?? ''))); ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars((string) ($document['tracking'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></strong></td>
                                        <td>
                                            <a class="record-title" href="view.php?id=<?= urlencode((string) ($document['id'] ?? '')) ?>"><?= htmlspecialchars((string) ($document['title'] ?? 'Untitled document'), ENT_QUOTES, 'UTF-8') ?></a>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($document['direction'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($document['category'] ?? 'Unfiled'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) ($document['recorded'] ?? $document['recorded_at'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><span class="status status--<?= htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string) ($document['status'] ?? 'Unknown'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                        <td><a class="row-action" href="view.php?id=<?= urlencode((string) ($document['id'] ?? '')) ?>" aria-label="Open <?= htmlspecialchars((string) ($document['tracking'] ?? 'document'), ENT_QUOTES, 'UTF-8') ?>">Open -&gt;</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="../assets/js/app.js" defer></script>
</body>
</html>
