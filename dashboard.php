<?php
require_once __DIR__ . '/includes/auth.php';
require_auth();

$activeUser = current_user();
$profileValues = [
    'barangay_name' => '',
    'location' => '',
    'office_address' => '',
    'contact_number' => '',
];
$barangayName = 'BARANGAY SAN ROQUE';
try {
    $savedProfile = db()->query('SELECT barangay_name, location, office_address, contact_number FROM Barangay_Profile WHERE profile_id = 1')->fetch();
    if (is_array($savedProfile)) {
        foreach (array_keys($profileValues) as $field) {
            $profileValues[$field] = (string) ($savedProfile[$field] ?? '');
        }
        if ($profileValues['barangay_name'] !== '') {
            $barangayName = $profileValues['barangay_name'];
        }
    }
} catch (PDOException $exception) {
    error_log('B-TRACK barangay profile load error: ' . $exception->getMessage());
}
$profileErrors = [];
$profileEditorOpen = isset($_GET['profile']);
$formErrors = [];
$formValues = [
    'title' => '',
    'type_id' => '',
    'direction' => '',
    'sender_recipient' => '',
    'date_received' => date('Y-m-d'),
    'location_id' => '',
];
$showAddModal = isset($_GET['add']);
$addNotice = pull_flash();
$profileSavedNotice = $profileEditorOpen && ($addNotice['title'] ?? '') === 'Barangay profile saved';

if (is_post_request() && post_value('_action') === 'save_barangay_profile') {
    $profileEditorOpen = true;
    foreach (array_keys($profileValues) as $field) {
        $profileValues[$field] = post_value($field);
    }

    if (!verify_csrf_token(post_value('_token'))) {
        $profileErrors['_form'] = 'Your form session expired. Refresh the page and try again.';
    }
    if ($profileValues['barangay_name'] === '' || mb_strlen($profileValues['barangay_name']) > 150) {
        $profileErrors['barangay_name'] = 'Enter a barangay name of 1 to 150 characters.';
    }
    if ($profileValues['location'] === '' || mb_strlen($profileValues['location']) > 180) {
        $profileErrors['location'] = 'Enter the municipality or city and province (up to 180 characters).';
    }
    if ($profileValues['office_address'] === '' || mb_strlen($profileValues['office_address']) > 255) {
        $profileErrors['office_address'] = 'Enter an office address of 1 to 255 characters.';
    }
    if ($profileValues['contact_number'] === '' || mb_strlen($profileValues['contact_number']) > 40 || preg_match('/^[0-9+().\-\s]+$/', $profileValues['contact_number']) !== 1) {
        $profileErrors['contact_number'] = 'Enter a valid contact number (up to 40 characters).';
    }

    if ($profileErrors === []) {
        try {
            $statement = db()->prepare(
                'INSERT INTO Barangay_Profile (profile_id, barangay_name, location, office_address, contact_number, updated_by)
                 VALUES (1, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    barangay_name = VALUES(barangay_name),
                    location = VALUES(location),
                    office_address = VALUES(office_address),
                    contact_number = VALUES(contact_number),
                    updated_by = VALUES(updated_by)'
            );
            $statement->execute([
                $profileValues['barangay_name'],
                $profileValues['location'],
                $profileValues['office_address'],
                $profileValues['contact_number'],
                (int) ($activeUser['id'] ?? 0),
            ]);
            flash('success', 'Barangay profile saved', 'The barangay details have been updated.');
            redirect('dashboard.php?profile=1');
        } catch (PDOException $exception) {
            error_log('B-TRACK barangay profile save error: ' . $exception->getMessage());
            $profileErrors['_form'] = 'Could not save the barangay profile. Check the database connection and try again.';
        }
    }
}

try {
    $documentTypes = db()->query('SELECT type_id, type_name FROM Document_Types ORDER BY type_name')->fetchAll();
    $storageLocations = db()->query(
        'SELECT location_id, type_id, cabinet, drawer, folder FROM Storage_Locations WHERE type_id IS NOT NULL ORDER BY type_id, cabinet, drawer, folder'
    )->fetchAll();
} catch (PDOException $exception) {
    error_log('B-TRACK dashboard form options error: ' . $exception->getMessage());
    $documentTypes = [];
    $storageLocations = [];
    $formErrors['_form'] = 'Could not load document types and storage locations from MySQL.';
}
if (is_post_request() && post_value('_action') === 'create_document') {
    $showAddModal = true;
    foreach (array_keys($formValues) as $field) {
        $formValues[$field] = post_value($field);
    }

    if (!verify_csrf_token(post_value('_token'))) {
        $formErrors['_form'] = 'Your form session expired. Refresh the page and try again.';
    }

    if ($formValues['title'] === '' || mb_strlen($formValues['title']) > 150) {
        $formErrors['title'] = 'Enter a title of 1 to 150 characters.';
    }
    if (!in_array($formValues['type_id'], array_map(static fn (array $type): string => (string) $type['type_id'], $documentTypes), true)) {
        $formErrors['type_id'] = 'Select a document type.';
    }
    if (!in_array($formValues['direction'], ['Incoming', 'Outgoing'], true)) {
        $formErrors['direction'] = 'Select Incoming or Outgoing.';
    }
    if ($formValues['sender_recipient'] === '' || mb_strlen($formValues['sender_recipient']) > 100) {
        $formErrors['sender_recipient'] = 'Enter the sender or recipient (up to 100 characters).';
    }
    $receivedDate = DateTime::createFromFormat('Y-m-d', $formValues['date_received']);
    if (!$receivedDate || $receivedDate->format('Y-m-d') !== $formValues['date_received'] || $formValues['date_received'] > date('Y-m-d')) {
        $formErrors['date_received'] = 'Choose a valid date that is not in the future.';
    }
    $validLocationIdsForType = [];
    foreach ($storageLocations as $location) {
        if ((string) $location['type_id'] === $formValues['type_id']) {
            $validLocationIdsForType[] = (string) $location['location_id'];
        }
    }
    if (!in_array($formValues['location_id'], $validLocationIdsForType, true)) {
        $formErrors['location_id'] = 'Choose a storage location in the cabinet for the selected document type.';
    }

    if ($formErrors === []) {
        try {
            $statement = db()->prepare('CALL sp_add_document(?, ?, ?, NULL, ?, ?, ?, ?)');
            $statement->execute([
                (int) $activeUser['id'],
                $formValues['title'],
                (int) $formValues['type_id'],
                $formValues['direction'],
                $formValues['sender_recipient'],
                $formValues['date_received'],
                (int) $formValues['location_id'],
            ]);
            $savedDocument = $statement->fetch();
            $statement->closeCursor();
            flash('success', 'Document saved', ($savedDocument['tracking_number'] ?? 'Document') . ' was recorded as Pending.');
            redirect('dashboard.php');
        } catch (PDOException $exception) {
            error_log('B-TRACK dashboard document save error: ' . $exception->getMessage());
            $formErrors['_form'] = ($exception->errorInfo[0] ?? '') === '45000'
                ? ($exception->errorInfo[2] ?? 'The database rejected the document.')
                : 'Could not save the document. Check the database connection and try again.';
        }
    }
}

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

$databaseError = '';

try {
    $storedDocuments = fetch_documents();
} catch (PDOException $exception) {
    error_log('B-TRACK dashboard database error: ' . $exception->getMessage());
    $storedDocuments = [];
    $databaseError = 'Unable to load records from the database.';
}

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

$viewQuery = array_intersect_key($_GET, array_flip(['search', 'year', 'status']));
$querySuffix = $viewQuery === [] ? '' : '?' . http_build_query($viewQuery);
$boardUrl = 'dashboard.php' . $querySuffix;
$listUrl = 'documents/index.php' . $querySuffix;
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
    <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400..700&amp;display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body<?= $profileEditorOpen ? ' class="sidebar-open"' : '' ?>>
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <div class="app-shell records-workspace">
        <aside class="sidebar<?= $profileEditorOpen ? ' is-editing-profile' : '' ?>" id="app-sidebar" aria-label="Primary navigation" aria-hidden="<?= $profileEditorOpen ? 'false' : 'true' ?>">
            <div class="brand-lockup">
                <span class="brand-mark" aria-hidden="true">BT</span>
                <div>
                    <p class="brand-name">B-TRACK</p>
                    <p class="brand-caption">Barangay records</p>
                </div>
            </div>

            <form class="filters sidebar-filters" action="dashboard.php" method="get" role="search" data-search-filters>
                <div class="search-control">
                    <button class="search-submit" type="submit" aria-label="Search records" title="Search records">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><path d="m16 16 5 5"></path></svg>
                    </button>
                    <label class="search-field">
                        <span class="sr-only">Search by tracking number or title</span>
                        <input type="search" name="search" value="<?= e($searchQuery) ?>" placeholder="Search">
                    </label>
                    <button class="filter-toggle" type="button" aria-label="Filters" title="Filters" aria-controls="search-filter-panel" aria-expanded="false" data-filter-toggle>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 12h10m-7 5h4"></path></svg>
                        <?php if ($activeFilterCount > 0): ?><span class="filter-count" aria-hidden="true"><?= $activeFilterCount ?></span><?php endif; ?>
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
                        <button class="button button--primary" type="submit">Apply filters</button>
                    </div>
                </div>
            </form>

            <section class="sidebar-profile-editor" id="sidebar-profile-editor" aria-labelledby="sidebar-profile-title"<?= $profileEditorOpen ? '' : ' hidden' ?>>
                <header class="sidebar-profile-editor__heading">
                    <div>
                        <span>OFFICE PROFILE</span>
                        <h2 id="sidebar-profile-title">Barangay info</h2>
                    </div>
                    <button class="sidebar-profile-back" type="button" data-profile-back>Back</button>
                </header>

                <?php if ($profileSavedNotice): ?>
                    <p class="sidebar-profile-success" role="status">Profile saved successfully.</p>
                <?php endif; ?>

                <?php if ($profileErrors !== []): ?>
                    <div class="sidebar-profile-error" role="alert" tabindex="-1">
                        <strong>Check these details</strong>
                        <?php if (isset($profileErrors['_form'])): ?><span><?= e($profileErrors['_form']) ?></span><?php endif; ?>
                        <?php foreach (['barangay_name' => 'Barangay name', 'location' => 'Location', 'office_address' => 'Office address', 'contact_number' => 'Contact number'] as $field => $label): ?>
                            <?php if (isset($profileErrors[$field])): ?><a href="#profile-<?= e($field) ?>"><?= e($label) ?>: <?= e($profileErrors[$field]) ?></a><?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <form class="sidebar-profile-form" action="dashboard.php?profile=1" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="_action" value="save_barangay_profile">

                    <label class="sidebar-profile-field" for="profile-barangay_name">
                        <span>Barangay name <b>*</b></span>
                        <input id="profile-barangay_name" name="barangay_name" type="text" maxlength="150" required value="<?= e($profileValues['barangay_name']) ?>" aria-invalid="<?= isset($profileErrors['barangay_name']) ? 'true' : 'false' ?>">
                        <?php if (isset($profileErrors['barangay_name'])): ?><small><?= e($profileErrors['barangay_name']) ?></small><?php endif; ?>
                    </label>

                    <label class="sidebar-profile-field" for="profile-location">
                        <span>Location <b>*</b></span>
                        <input id="profile-location" name="location" type="text" maxlength="180" placeholder="City / Municipality, Province" required value="<?= e($profileValues['location']) ?>" aria-invalid="<?= isset($profileErrors['location']) ? 'true' : 'false' ?>">
                        <?php if (isset($profileErrors['location'])): ?><small><?= e($profileErrors['location']) ?></small><?php endif; ?>
                    </label>

                    <label class="sidebar-profile-field" for="profile-office_address">
                        <span>Office address <b>*</b></span>
                        <textarea id="profile-office_address" name="office_address" rows="2" maxlength="255" required aria-invalid="<?= isset($profileErrors['office_address']) ? 'true' : 'false' ?>"><?= e($profileValues['office_address']) ?></textarea>
                        <?php if (isset($profileErrors['office_address'])): ?><small><?= e($profileErrors['office_address']) ?></small><?php endif; ?>
                    </label>

                    <label class="sidebar-profile-field" for="profile-contact_number">
                        <span>Contact number <b>*</b></span>
                        <input id="profile-contact_number" name="contact_number" type="tel" maxlength="40" autocomplete="tel" required value="<?= e($profileValues['contact_number']) ?>" aria-invalid="<?= isset($profileErrors['contact_number']) ? 'true' : 'false' ?>">
                        <?php if (isset($profileErrors['contact_number'])): ?><small><?= e($profileErrors['contact_number']) ?></small><?php endif; ?>
                    </label>

                    <button class="sidebar-profile-save" type="submit">Save barangay info <span aria-hidden="true">-&gt;</span></button>
                </form>
            </section>

            <p class="sidebar-menu-label">Menu</p>
            <nav class="primary-nav" aria-label="Main navigation">
                <a class="nav-link is-active" href="<?= e($boardUrl) ?>" aria-current="page">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect></svg>
                    <span>Cabinet board</span>
                </a>
                <a class="nav-link" href="<?= e($listUrl) ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l5 5v13H5z"></path><path d="M15 3v6h5M8 13h8M8 17h8"></path></svg>
                    <span>All records</span>
                </a>
                <button class="nav-link sidebar-profile-toggle<?= $profileEditorOpen ? ' is-active' : '' ?>" type="button" data-profile-toggle aria-controls="sidebar-profile-editor" aria-expanded="<?= $profileEditorOpen ? 'true' : 'false' ?>">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 10h1M14 10h1M9 14h1M14 14h1M11 21v-4h2v4"></path></svg>
                    <span>Barangay info</span>
                </button>
            </nav>

            <div class="operator-panel">
                <span>SECRETARY</span>
                <strong title="<?= e($activeUser['full_name'] ?? 'Barangay Secretary') ?>"><?= e($activeUser['full_name'] ?? 'Barangay Secretary') ?></strong>
            </div>

            <form class="sign-out" action="logout.php" method="post">
                <?= csrf_field() ?>
                <button type="submit">
                    <span>Sign out</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"></path></svg>
                </button>
            </form>
        </aside>

        <button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Close navigation" tabindex="-1"<?= $profileEditorOpen ? '' : ' hidden' ?>></button>

        <main class="main-content" id="main-content">
            <div class="records-content">
                <nav class="page-breadcrumb" aria-label="Breadcrumb">
                    <svg class="page-breadcrumb__mark" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l5 5v13H5z"></path><path d="M15 3v6h5M8 13h8M8 17h8"></path></svg>
                    <span aria-hidden="true">›</span><a href="dashboard.php">Board</a><span aria-hidden="true">›</span><strong aria-current="page">Overview</strong>
                </nav>
                <header class="system-bar">
                    <div>
                        <div class="topbar-brand" aria-label="B-TRACK">
                            <span class="topbar-brand-mark" aria-hidden="true">BT</span>
                            <span class="topbar-brand-name">B-TRACK</span>
                        </div>
                        <button class="menu-toggle" type="button" aria-label="Open navigation" data-sidebar-toggle aria-controls="app-sidebar" aria-expanded="<?= $profileEditorOpen ? 'true' : 'false' ?>">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"></path></svg>
                        </button>
                    </div>
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
                        <a class="is-active" aria-current="page" href="<?= e($boardUrl) ?>">Board</a>
                        <a href="<?= e($listUrl) ?>">List</a>
                    </nav>
                    <div class="dashboard-toolbar__actions">
                        <button class="button button--primary dashboard-record-button" type="button" data-add-document-open aria-haspopup="dialog"><span aria-hidden="true">+</span> Record document</button>
                    </div>
                </div>

                <section class="cabinet-section" aria-labelledby="cabinet-title" data-list-region>
                    <h2 class="sr-only" id="cabinet-title">Record categories</h2>
                    <div class="list-loading" role="status" data-list-loading hidden><span class="loading-indicator" aria-hidden="true"></span>Loading records</div>

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
                                    <h3 title="<?= e($cabinet['name']) ?>"><?= e($cabinet['name']) ?></h3>
                                    <span class="cabinet-count" aria-label="<?= $documentCount ?> records"><?= $documentCount ?></span>
                                    <span class="cabinet-number"><?= e($cabinet['code']) ?></span>
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
                                            <a class="document-folder"
                                               href="documents/view.php?id=<?= urlencode((string) $document['id']) ?>"
                                               aria-label="Open document <?= htmlspecialchars($document['title'], ENT_QUOTES, 'UTF-8') ?>, code <?= htmlspecialchars($document['tracking'], ENT_QUOTES, 'UTF-8') ?>">
                                                <span class="document-code"><?= htmlspecialchars($document['tracking'], ENT_QUOTES, 'UTF-8') ?></span>
                                                <h4 title="<?= e($document['title']) ?>"><?= e($document['title']) ?></h4>
                                                <span class="document-recorded">
                                                    <span>Recorded</span>
                                                    <time datetime="<?= htmlspecialchars($document['recorded'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($document['recorded'], ENT_QUOTES, 'UTF-8') ?></time>
                                                </span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>
            </div>

            <?php if ($addNotice && !$profileSavedNotice): ?>
                <div class="dashboard-toast" role="status">
                    <strong><?= e($addNotice['title'] ?? 'Saved') ?></strong>
                    <span><?= e($addNotice['message'] ?? '') ?></span>
                </div>
            <?php endif; ?>

            <div class="document-modal-backdrop<?= $showAddModal ? ' is-open' : '' ?>" data-add-document-modal<?= $showAddModal ? '' : ' hidden' ?>>
                <section class="document-modal" role="dialog" aria-modal="true" aria-labelledby="add-document-title" aria-describedby="add-document-description" tabindex="-1" data-add-document-dialog>
                    <header class="document-modal__header">
                        <div class="modal-heading-lockup">
                            <div class="modal-heading-meta">
                                <span class="modal-kicker">DOCUMENT REGISTRY</span>
                                <span class="modal-step">NEW / 01</span>
                            </div>
                            <h2 id="add-document-title">Record document</h2>
                            <p id="add-document-description">Enter the details and choose its cabinet folder.</p>
                        </div>
                        <button class="modal-close" type="button" aria-label="Close form" data-add-document-close>
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"></path></svg>
                        </button>
                    </header>

                    <?php if ($formErrors !== []): ?>
                        <div class="modal-error-summary" role="alert" tabindex="-1">
                            <strong>Please check the form</strong>
                            <span><?= e($formErrors['_form'] ?? 'Correct the highlighted fields, then save again.') ?></span>
                        </div>
                    <?php endif; ?>

                    <form class="modal-document-form" action="dashboard.php" method="post" data-modal-document-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="_action" value="create_document">

                        <section class="modal-form-section" aria-labelledby="modal-record-details-title">
                            <header class="modal-section-heading">
                                <span>01</span>
                                <h3 id="modal-record-details-title">Document details</h3>
                            </header>
                            <div class="modal-form-grid">
                            <label class="modal-field modal-field--wide">
                                <span>Document title <b>*</b></span>
                                <input name="title" type="text" maxlength="150" required value="<?= e($formValues['title']) ?>" aria-invalid="<?= isset($formErrors['title']) ? 'true' : 'false' ?>">
                                <?php if (isset($formErrors['title'])): ?><small><?= e($formErrors['title']) ?></small><?php endif; ?>
                            </label>

                            <label class="modal-field">
                                <span>Document type <b>*</b></span>
                                <select name="type_id" required aria-invalid="<?= isset($formErrors['type_id']) ? 'true' : 'false' ?>" data-modal-type>
                                    <option value="">Select type</option>
                                    <?php foreach ($documentTypes as $type): ?>
                                        <option value="<?= e($type['type_id']) ?>"<?= $formValues['type_id'] === (string) $type['type_id'] ? ' selected' : '' ?>><?= e($type['type_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($formErrors['type_id'])): ?><small><?= e($formErrors['type_id']) ?></small><?php endif; ?>
                            </label>

                            <label class="modal-field">
                                <span>Direction <b>*</b></span>
                                <select name="direction" required data-modal-direction aria-invalid="<?= isset($formErrors['direction']) ? 'true' : 'false' ?>">
                                    <option value="">Select direction</option>
                                    <option<?= $formValues['direction'] === 'Incoming' ? ' selected' : '' ?>>Incoming</option>
                                    <option<?= $formValues['direction'] === 'Outgoing' ? ' selected' : '' ?>>Outgoing</option>
                                </select>
                                <?php if (isset($formErrors['direction'])): ?><small><?= e($formErrors['direction']) ?></small><?php endif; ?>
                            </label>

                            <label class="modal-field">
                                <span data-modal-party-label><?= $formValues['direction'] === 'Incoming' ? 'Received from / Sender' : ($formValues['direction'] === 'Outgoing' ? 'Sent to / Recipient' : 'Sender / Recipient') ?> <b>*</b></span>
                                <input name="sender_recipient" type="text" maxlength="100" required value="<?= e($formValues['sender_recipient']) ?>" aria-invalid="<?= isset($formErrors['sender_recipient']) ? 'true' : 'false' ?>">
                                <?php if (isset($formErrors['sender_recipient'])): ?><small><?= e($formErrors['sender_recipient']) ?></small><?php endif; ?>
                            </label>

                            <label class="modal-field">
                                <span data-modal-date-label><?= $formValues['direction'] === 'Incoming' ? 'Date received' : ($formValues['direction'] === 'Outgoing' ? 'Date released' : 'Date received / released') ?> <b>*</b></span>
                                <input name="date_received" type="date" max="<?= e(date('Y-m-d')) ?>" required value="<?= e($formValues['date_received']) ?>" aria-invalid="<?= isset($formErrors['date_received']) ? 'true' : 'false' ?>">
                                <?php if (isset($formErrors['date_received'])): ?><small><?= e($formErrors['date_received']) ?></small><?php endif; ?>
                            </label>
                            </div>
                        </section>

                        <section class="modal-form-section" aria-labelledby="modal-location-title">
                            <header class="modal-section-heading">
                                <span>02</span>
                                <h3 id="modal-location-title">Storage location</h3>
                            </header>
                            <fieldset class="modal-field modal-field--wide modal-location-field" data-location-group aria-invalid="<?= isset($formErrors['location_id']) ? 'true' : 'false' ?>">
                                <legend>Choose one folder <b>*</b></legend>
                                <div class="storage-choice-list" data-location-choices>
                                    <?php foreach ($storageLocations as $location): ?>
                                        <?php
                                        $locationTypeId = (string) $location['type_id'];
                                        ?>
                                        <label class="storage-choice" data-storage-choice<?= $locationTypeId !== $formValues['type_id'] ? ' hidden' : '' ?>>
                                            <input type="radio" name="location_id" value="<?= e($location['location_id']) ?>" data-location-choice data-type-id="<?= e($locationTypeId) ?>" required aria-invalid="<?= isset($formErrors['location_id']) ? 'true' : 'false' ?>"<?= $formValues['type_id'] === '' ? ' disabled' : '' ?><?= $formValues['location_id'] === (string) $location['location_id'] ? ' checked' : '' ?>>
                                            <span class="storage-choice__path"><?= e($location['cabinet']) ?> / <?= e($location['drawer']) ?> / <?= e($location['folder']) ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                                <small class="modal-field__hint" data-location-empty<?= $formValues['type_id'] !== '' ? ' hidden' : '' ?>>Choose a document category to display matching folders.</small>
                                <?php if (isset($formErrors['location_id'])): ?><small><?= e($formErrors['location_id']) ?></small><?php endif; ?>
                            </fieldset>
                        </section>

                        <div class="modal-form-meta">
                            <span>RECORDED BY <strong><?= e($activeUser['full_name'] ?? 'Secretary') ?></strong></span>
                            <span>INITIAL STATUS <strong>PENDING</strong></span>
                            <span>DATE RECORDED <strong><?= e(date('M d, Y')) ?></strong></span>
                        </div>

                        <footer class="modal-form-actions">
                            <button class="modal-cancel" type="button" data-add-document-close>Cancel</button>
                            <button class="modal-save" type="submit"><span>Save document</span><span aria-hidden="true">-&gt;</span></button>
                        </footer>
                    </form>
                </section>
            </div>
        </main>
    </div>
    <script src="assets/js/app.js" defer></script>
</body>
</html>
