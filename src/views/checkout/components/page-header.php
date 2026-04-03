<?php
/**
 * Page header with title and breadcrumb.
 *
 * @var string $title Page title.
 * @var array $breadcrumbs Array of ['label' => '...', 'href' => '...'] (last item has no href).
 */
$breadcrumbs = $breadcrumbs ?? [];
?>
<div class="page-header">
    <nav class="container">
        <h1 class="page-title"><?= htmlspecialchars($title) ?></h1>
        <?php if (!empty($breadcrumbs)): ?>
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <?php foreach ($breadcrumbs ?? [] as $i => $crumb): ?>
                    <?php if ($i > 0): ?><span aria-hidden="true">/</span><?php endif; ?>
                    <?php if (!empty($crumb['href'])): ?>
                        <a href="<?= htmlspecialchars($crumb['href']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                    <?php else: ?>
                        <span aria-current="page"><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
</div>