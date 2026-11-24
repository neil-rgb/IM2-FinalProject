<?php
$barangayName = 'BARANGAY NAME';

$summary = [
    ['label' => 'Total records', 'value' => 0],
    ['label' => 'Unfiled', 'value' => 0],
    ['label' => 'Pending', 'value' => 0],
    ['label' => 'In process', 'value' => 0],
    ['label' => 'Completed', 'value' => 0],
];

$cabinets = [
    [
        'code' => 'INTAKE',
        'name' => 'Unfiled documents',
        'description' => 'Recorded documents waiting for a category.',
        'intake' => true,
        'documents' => [],
    ],
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

            <a class="sign-out" href="logout.php"><span>Sign out</span><span aria-hidden="true">-&gt;</span></a>
        </aside>

        <button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Close navigation" hidden></button>

        <main class="main-content" id="main-content">
            <header class="system-bar">
                <div>
                    <button class="menu-toggle" type="button" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="false">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        <span>Menu</span>
                    </button>
                    <div class="topbar-brand" aria-label="B-TRACK">
                        <span class="topbar-brand-mark" aria-hidden="true">BT</span>
                        <span class="topbar-brand-name">B-TRACK</span>
                    </div>
                    <span class="topbar-context">BARANGAY RECORDS</span>
                </div>
            </header>

            <section class="page-heading" aria-labelledby="page-title">
                <div>
                    <h1 id="page-title"><?= htmlspecialchars($barangayName, ENT_QUOTES, 'UTF-8') ?></h1>
                    <p>Document Registry and Processing Overview</p>
                </div>
                <div class="page-actions">
                    <a class="button button--primary" href="documents/create.php"><span aria-hidden="true">+</span> Record document</a>
                </div>
            </section>

            <section class="summary-strip" aria-label="Document summary">
                <?php foreach ($summary as $item): ?>
                    <article>
                        <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <strong><?= number_format($item['value']) ?></strong>
                    </article>
                <?php endforeach; ?>
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

                <form class="filters" action="documents/index.php" method="get">
                    <label class="search-field">
                        <span class="sr-only">Search documents</span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 5 5"></path></svg>
                        <input type="search" name="search" placeholder="Search tracking number or title">
                    </label>
                    <label>
                        <span class="sr-only">Filter by year</span>
                        <select name="year">
                            <option value="2026">Year / 2026</option>
                            <option value="2025">Year / 2025</option>
                        </select>
                    </label>
                    <label>
                        <span class="sr-only">Filter by status</span>
                        <select name="status">
                            <option value="">All statuses</option>
                            <option>Pending</option>
                            <option>In Process</option>
                            <option>Completed</option>
                        </select>
                    </label>
                    <button type="submit">Search</button>
                </form>
            </section>

            <section class="cabinet-section" aria-labelledby="cabinet-title">
                <header class="section-heading">
                    <div>
                        <span>DIGITAL RECORDS INDEX</span>
                        <h2 id="cabinet-title">Record categories</h2>
                    </div>
                    <p>Organized by document type</p>
                </header>

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
                                                <span>PHYSICAL LOCATION</span>
                                                <strong><?= htmlspecialchars($document['location'], ENT_QUOTES, 'UTF-8') ?></strong>
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
            </section>
        </main>
    </div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
