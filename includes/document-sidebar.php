<?php
[$allowedYears, $allowedStatuses, $searchQuery, $selectedYear, $selectedStatus] = $listFilters ?? document_list_filters();
$activeFilterCount = ($searchQuery !== '' ? 1 : 0)
    + ($selectedStatus !== '' ? 1 : 0)
    + (array_key_exists('year', $_GET) && $selectedYear !== '' ? 1 : 0);
$sidebarQuery = document_browse_query();
unset($sidebarQuery['from'], $sidebarQuery['inventory_location']);
$sidebarQuerySuffix = $sidebarQuery === [] ? '' : '?' . http_build_query($sidebarQuery);
$sidebarListUrl = 'index.php' . $sidebarQuerySuffix;
$activeUser = current_user();
?>
<aside class="sidebar" id="app-sidebar" aria-label="Primary navigation" aria-hidden="true">
    <div class="brand-lockup">
        <span class="brand-mark" aria-hidden="true">BT</span>
        <div>
            <p class="brand-name">B-TRACK</p>
            <p class="brand-caption">Barangay records</p>
        </div>
    </div>

    <div class="sidebar-content">
        <form class="filters sidebar-filters" action="index.php" method="get" role="search" data-search-filters>
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
            <div class="filter-popover" id="search-filter-panel" data-filter-panel role="group" aria-label="Record filters" hidden>
                <div class="filter-options">
                    <label>
                        <span>Year</span>
                        <select name="year">
                            <option value="">All years</option>
                            <?php foreach ($allowedYears as $year): ?>
                                <option value="<?= $year ?>"<?= $selectedYear === $year ? ' selected' : '' ?>><?= $year ?></option>
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
                    <a href="index.php?year=" aria-label="Clear filters">Clear</a>
                    <button class="button button--primary" type="submit" aria-label="Apply filters">Apply</button>
                </div>
            </div>
        </form>

        <p class="sidebar-menu-label">Menu</p>
        <nav class="primary-nav" aria-label="Main navigation">
            <a class="nav-link<?= ($documentListActive ?? false) ? ' is-active' : '' ?>" href="<?= e($sidebarListUrl) ?>"<?= ($documentListActive ?? false) ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l5 5v13H5z"></path><path d="M15 3v6h5M8 13h8M8 17h8"></path></svg>
                <span>All records</span>
            </a>
            <a class="nav-link<?= ($inventoryPageActive ?? false) ? ' is-active' : '' ?>" href="inventory.php"<?= ($inventoryPageActive ?? false) ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h7l2 2h9v10H3zM3 7V5h7l2 2h9v2M8 14h8"></path></svg>
                <span>Folder inventory</span>
            </a>
            <a class="nav-link" href="../dashboard.php?profile=1">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 21h18M5 21V8l7-4 7 4v13M9 10h1M14 10h1M9 14h1M14 14h1M11 21v-4h2v4"></path></svg>
                <span>Barangay info</span>
            </a>
            <a class="nav-link<?= ($recycleBinActive ?? false) ? ' is-active' : '' ?>" href="recycle-bin.php"<?= ($recycleBinActive ?? false) ? ' aria-current="page"' : '' ?>>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 10v7m4-7v7"></path></svg>
                <span>Recycle Bin</span>
            </a>
        </nav>
    </div>

    <footer class="sidebar-footer">
        <div class="operator-panel">
            <span>SECRETARY</span>
            <strong title="<?= e($activeUser['full_name'] ?? 'Barangay Secretary') ?>"><?= e($activeUser['full_name'] ?? 'Barangay Secretary') ?></strong>
        </div>

        <form class="sign-out" action="../logout.php" method="post">
            <?= csrf_field() ?>
            <button type="submit">
                <span>Sign out</span>
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 4H5v16h5M14 8l4 4-4 4M18 12H9"></path></svg>
            </button>
        </form>
    </footer>
</aside>

<button class="sidebar-backdrop" type="button" data-sidebar-backdrop aria-label="Close navigation" tabindex="-1" hidden></button>
