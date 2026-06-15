<?php if (!empty($territory)): ?>
<section class="region-context" aria-label="Regional content">
    <?php if (!empty($allTerritories) && count($allTerritories) > 1): ?>
        <nav class="region-selector" aria-label="Choose region">
            <?php foreach ($allTerritories as $item): ?>
                <a
                    href="/<?= htmlspecialchars($siteSlug) ?>/<?= htmlspecialchars($item->slug) ?>/<?= htmlspecialchars($page->slug) ?>"
                    class="region-selector__link <?= (int)$item->id === (int)$territory->id ? 'is-active' : '' ?>"
                    <?= (int)$item->id === (int)$territory->id ? 'aria-current="page"' : '' ?>
                >
                    <?= htmlspecialchars($item->name) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (!empty($pageGridHtml)): ?>
        <div class="region-page-grid">
            <?= $pageGridHtml ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($regionArticles) && count($regionArticles) > 0): ?>
        <section class="region-related-content">
            <h2>More from <?= htmlspecialchars($territory->name) ?></h2>
            <div class="region-related-content__grid">
                <?php foreach ($regionArticles as $article): ?>
                    <article class="region-related-content__card">
                        <h3>
                            <a href="/<?= htmlspecialchars($siteSlug) ?>/<?= htmlspecialchars($territory->slug) ?>/<?= htmlspecialchars($article->slug) ?>">
                                <?= htmlspecialchars($article->title) ?>
                            </a>
                        </h3>
                        <?php if (!empty($article->meta_description)): ?>
                            <p><?= htmlspecialchars($article->meta_description) ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php endif; ?>
