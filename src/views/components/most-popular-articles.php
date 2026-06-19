<?php if (!empty($popularArticles) && count($popularArticles) > 0): ?>
<section class="popular-articles" aria-labelledby="popular-articles-title">
    <div class="popular-articles__header">
        <h2 id="popular-articles-title">Most popular</h2>
    </div>

    <ol class="popular-articles__list">
        <?php foreach ($popularArticles as $index => $item): ?>
            <?php $article = $item['page']; ?>
            <li class="popular-articles__item">
                <span class="popular-articles__rank" aria-hidden="true"><?= $index + 1 ?></span>
                <div class="popular-articles__content">
                    <a class="popular-articles__link" href="/<?= rawurlencode((string) $siteSlug) ?>/<?= rawurlencode((string) $article->slug) ?>">
                        <?= htmlspecialchars((string) $article->title, ENT_QUOTES, 'UTF-8') ?>
                    </a>
                    <?php if (!empty($article->listing_synopsis)): ?>
                        <p class="popular-articles__summary">
                            <?= htmlspecialchars((string) $article->listing_synopsis, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                    <span class="popular-articles__views">
                        <?= number_format((int) $item['view_count']) ?> views
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ol>
</section>
<?php endif; ?>
