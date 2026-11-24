<?php
require_once __DIR__ . '/includes/bootstrap.php';

$barangayName = 'BARANGAY NAME';

$cabinets = [
    [
        'code' => 'CABINET 01',
        'name' => 'Finance',
        'description' => 'Financial reports, collections, budgets and expenditure records.',
        'documents' => [],
    ],
    [
        'code' => 'CABINET 02',
        'name' => 'Resolutions',
        'description' => 'Barangay resolutions, ordinances and approved proposals.',
        'documents' => [],
    ],
    [
        'code' => 'CABINET 03',
        'name' => 'Requests & Endorsements',
        'description' => 'Requests, endorsements, referrals and assistance documents.',
        'documents' => [],
    ],
    [
        'code' => 'CABINET 04',
        'name' => 'Notices & Communications',
        'description' => 'Notices, memoranda, invitations and official correspondence.',
        'documents' => [],
    ],
    [
        'code' => 'CABINET 05',
        'name' => 'Administrative Records',
        'description' => 'Employee, meeting and general barangay office records.',
        'documents' => [],
    ],
];

$storedDocuments = is_array($_SESSION['documents'] ?? null) ? $_SESSION['documents'] : [];

foreach ($storedDocuments as $storedDocument) {
    foreach ($cabinets as &$cabinet) {
        if (($storedDocument['category'] ?? '') === $cabinet['name']) {
            $cabinet['documents'][] = $storedDocument;
            break;
        }
    }
    unset($cabinet);
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
$visibleDocuments = [];

foreach ($cabinets as &$cabinet) {
    $cabinet['documents'] = array_values(array_filter(
        $cabinet['documents'],
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

    $visibleDocuments = array_merge($visibleDocuments, $cabinet['documents']);
}
unset($cabinet);

$activeFilterCount = ($searchQuery !== '' ? 1 : 0)
    + ($selectedStatus !== '' ? 1 : 0)
    + ($filterWasSubmitted && $selectedYear !== '' ? 1 : 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="B-TRACK barangay records dashboard">
    <title>B-TRACK / Barangay Records</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
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

            <nav class="primary-nav" aria-label="B-TRACK sections">
                <a class="nav-link is-active" href="dashboard.php" aria-current="page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span><span class="nav-code">01</span>
                </a>
                <a class="nav-link" href="documents/index.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 3h11l5 5v13H4z"></path><path d="M15 3v6h5M8 13h8M8 17h8"></path></svg>
                    <span>Documents</span><span class="nav-code">02</span>
                </a>
                <a class="nav-link" href="history/index.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.3-5.7L4 8.6"></path><path d="M4 4v4.6h4.6M12 7v5l3 2"></path></svg>
                    <span>Processing history</span><span class="nav-code">03</span>
                </a>
                <a class="nav-link" href="reports/pending.php">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"></path></svg>
                    <span>Reports</span><span class="nav-code">04</span>
                </a>
            </nav>

            <div class="operator-panel">
                <span>ACTIVE OPERATOR</span>
                <strong>Barangay Secretary</strong>
                <span class="online-status"><i aria-hidden="true"></i> Session active</span>
            </div>

            <form class="sign-out" action="logout.php" method="post">
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
                    <p>Document Registry and Processing Overview</p>
                </div>
                <div class="page-actions">
                    <a class="button button--primary" href="documents/create.php" aria-label="Record document"><span aria-hidden="true">+</span> Record document</a>
                </div>
            </section>

            <section class="workspace-toolbar" aria-label="Cabinet controls">
                <div class="view-tabs" aria-label="Document views">
                    <a class="is-active" href="dashboard.php" aria-current="page" aria-label="Cabinet view" title="Cabinet view">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
                        <span class="sr-only">Cabinet view</span>
                    </a>
                    <a href="documents/index.php" aria-label="List view" title="List view">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 6h12M9 12h12M9 18h12"></path><rect x="3" y="4" width="2" height="2"></rect><rect x="3" y="10" width="2" height="2"></rect><rect x="3" y="16" width="2" height="2"></rect></svg>
                        <span class="sr-only">List view</span>
                    </a>
                </div>

                <form class="filters" action="dashboard.php" method="get" data-search-filters>
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
                            <a href="dashboard.php?year=" data-clear-filters>Clear filters</a>
                            <button type="submit">Apply filters</button>
                        </div>
                    </div>
                </form>
            </section>

            <section class="cabinet-section" aria-labelledby="cabinet-title">
                <header class="section-heading">
                    <div>
                        <span>DIGITAL RECORDS INDEX</span>
                        <h2 id="cabinet-title">Record categories</h2>
                    </div>
                </header>

                <?php if ($filterWasSubmitted && count($visibleDocuments) === 0): ?>
                    <div class="no-results" role="status">
                        <span>[ NO MATCHES ]</span>
                        <h3>No records match your search</h3>
                        <p>Try another keyword or remove one of the selected filters.</p>
                        <a href="dashboard.php?year=">Clear search and filters</a>
                    </div>
                <?php else: ?>
                <div class="cabinet-board" tabindex="0" aria-label="Document cabinets. Scroll horizontally to view additional cabinets.">
                    <?php foreach ($cabinets as $cabinet): ?>
                        <?php $documentCount = count($cabinet['documents']); ?>
                        <article class="cabinet<?= !empty($cabinet['intake']) ? ' cabinet--intake' : '' ?>">
                            <header class="cabinet-header">
                                <div class="cabinet-label">
                                    <div><span><?= htmlspecialchars($cabinet['code'], ENT_QUOTES, 'UTF-8') ?></span><span><?= str_pad((string) $documentCount, 2, '0', STR_PAD_LEFT) ?> FILES</span></div>
                                    <h3><?= htmlspecialchars($cabinet['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                </div>
                                <p><?= htmlspecialchars($cabinet['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            </header>

                            <div class="cabinet-body">
                                <?php if ($documentCount === 0): ?>
                                    <div class="empty-cabinet">
                                        <span>[ EMPTY ]</span>
                                        <strong>No records in this category</strong>
                                        <p>New records assigned to this category will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($cabinet['documents'] as $document): ?>
                                        <?php $statusClass = strtolower(str_replace(' ', '-', $document['status'])); ?>
                                        <article class="document-folder">
                                            <div class="folder-tab" aria-hidden="true"></div>
                                            <div class="document-topline">
                                                <span><?= htmlspecialchars($document['tracking'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <span class="status status--<?= $statusClass ?>"><?= htmlspecialchars($document['status'], ENT_QUOTES, 'UTF-8') ?></span>
                                            </div>
                                            <h4><?= htmlspecialchars($document['title'], ENT_QUOTES, 'UTF-8') ?></h4>
                                            <dl>
                                                <div><dt>Direction</dt><dd><?= htmlspecialchars($document['direction'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                                                <div><dt>Recorded</dt><dd><?= htmlspecialchars($document['recorded'], ENT_QUOTES, 'UTF-8') ?></dd></div>
                                            </dl>
                                            <div class="location-label">
                                                <span>DOCUMENT CATEGORY</span>
                                                <strong><?= htmlspecialchars($document['category'], ENT_QUOTES, 'UTF-8') ?></strong>
                                            </div>
                                            <a href="documents/view.php?id=<?= $document['id'] ?>">Open record -&gt;</a>
                                        </article>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <footer class="cabinet-footer">
                                <span>Record group / <?= htmlspecialchars($cabinet['code'], ENT_QUOTES, 'UTF-8') ?></span>
                                <a href="documents/index.php?cabinet=<?= urlencode($cabinet['code']) ?>">Open category -&gt;</a>
                            </footer>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
