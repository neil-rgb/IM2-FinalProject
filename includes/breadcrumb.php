<nav class="page-breadcrumb" aria-label="Breadcrumb">
    <svg class="page-breadcrumb__mark" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h10l5 5v13H5z"></path><path d="M15 3v6h5M8 13h8M8 17h8"></path></svg>
    <ol class="page-breadcrumb__trail" role="list">
        <?php foreach ($breadcrumbItems as $item): ?>
            <li>
                <span aria-hidden="true">›</span>
                <?php if (isset($item[1])): ?>
                    <a href="<?= e($item[1]) ?>"><?= e($item[0]) ?></a>
                <?php else: ?>
                    <strong aria-current="page"><?= e($item[0]) ?></strong>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
