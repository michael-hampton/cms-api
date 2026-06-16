<?php if (!empty($territory)): ?>
<section class="region-context" aria-label="Regional content">
    <?php if (!empty($allTerritories) && count($allTerritories) > 1): ?>
        <div class="region-selector">
            <label for="region-select">Region:</label>
            <select
                id="region-select"
                class="region-selector__select"
                onchange="window.location.href='/' + <?= json_encode((string)$siteSlug) ?> + '/' + this.value"
            >
                <?php foreach ($allTerritories as $item): ?>
                    <option
                        value="<?= htmlspecialchars((string)$item->slug, ENT_QUOTES, 'UTF-8') ?>"
                        <?= (int)$item->id === (int)$territory->id ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars((string)$item->name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    <?php endif; ?>

    <?php if (!empty($pageGridHtml)): ?>
        <div class="region-page-grid">
            <?= $pageGridHtml ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($regionArticles) && count($regionArticles) > 0): ?>
        <section class="region-related-content">
            <h2>More from <?= htmlspecialchars((string)$territory->name, ENT_QUOTES, 'UTF-8') ?></h2>
            <div class="region-related-content__grid">
                <?php foreach ($regionArticles as $article): ?>
                    <article class="region-related-content__card">
                        <h3>
                            <a href="/<?= htmlspecialchars((string)$siteSlug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string)$territory->slug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string)$article->slug, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars((string)$article->title, ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </h3>
                        <?php if (!empty($article->meta_description)): ?>
                            <p><?= htmlspecialchars((string)$article->meta_description, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>
</section>

<style>
    .region-context { margin:0 0 2rem; }
    .region-selector {
        display:flex;
        align-items:center;
        justify-content:flex-end;
        gap:.75rem;
        margin-bottom:1.5rem;
        padding:1rem 1.25rem;
        border:1px solid #e2e8f0;
        border-radius:14px;
        background:#fff;
        box-shadow:0 8px 24px rgba(15,23,42,.05);
    }
    .region-selector label {
        color:#475569;
        font-size:.9rem;
        font-weight:700;
    }
    .region-selector__select {
        min-width:180px;
        padding:.7rem 2.25rem .7rem .9rem;
        border:1px solid #cbd5e1;
        border-radius:10px;
        background:#fff;
        color:#0f172a;
        font:inherit;
        font-weight:600;
        cursor:pointer;
    }
    .region-selector__select:focus {
        outline:3px solid rgba(37,99,235,.18);
        border-color:#2563eb;
    }
    @media (max-width:560px) {
        .region-selector { align-items:stretch; flex-direction:column; }
        .region-selector__select { width:100%; }
    }
</style>
<?php endif; ?>
