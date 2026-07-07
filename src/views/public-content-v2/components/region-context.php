<?php
if (!empty($territory)): ?>
    <section class="region-context" aria-label="Regional content">
        <?php if (!empty($allTerritories) && count($allTerritories) > 1): ?>
            <div class="region-selector">
                <label for="region-select">Region:</label>
                <select
                        id="region-select"
                        class="region-selector__select"
                        data-region-select
                >
                    <?php foreach ($allTerritories as $item): ?>
                        <option
                                value="/<?= rawurlencode((string) $siteSlug) ?>/<?= rawurlencode((string) $item->slug) ?>"
                                <?= (int) $item->id === (int) $territory->id ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars((string) $item->name, ENT_QUOTES, 'UTF-8') ?>
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
                <h2 class="region-related-title">More from <?= htmlspecialchars((string) $territory->name, ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="region-related-content__grid">
                    <?php foreach ($regionArticles as $article): ?>
                        <article class="region-related-content__card">
                            <div class="card-inner">
                                <h3>
                                    <a href="/<?= htmlspecialchars((string) $siteSlug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $territory->slug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $article->slug, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $article->title, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h3>
                                <?php if (!empty($article->meta_description)): ?>
                                    <p><?= htmlspecialchars((string) $article->meta_description, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                                <a href="/<?= htmlspecialchars((string) $siteSlug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $territory->slug, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $article->slug, ENT_QUOTES, 'UTF-8') ?>" class="read-more-link">
                                    Read Article &rarr;
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </section>

    <style>
        /* Region Section Container */
        .region-context {
            margin: 0 0 3.5rem;
        }

        /* Region Dropdown Selector Box */
        .region-selector {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: .75rem;
            margin-bottom: 2rem;
            padding: 1rem 1.5rem;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
        }
        .region-selector label {
            color: #475569;
            font-size: .9rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .region-selector__select {
            min-width: 200px;
            padding: .6rem 2.25rem .6rem .9rem;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #0f172a;
            font: inherit;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .region-selector__select:focus {
            outline: 3px solid rgba(217, 119, 6, 0.15);
            border-color: #d97706; /* Sunset Orange Accent */
        }

        /* Related Content Header */
        .region-related-content {
            margin-top: 3rem;
        }
        .region-related-title {
            font-family: "Playfair Display", Georgia, serif;
            font-size: 1.85rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 1.75rem;
            position: relative;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
        .region-related-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background-color: #d97706;
        }

        /* Article Cards Grid Architecture */
        .region-related-content__grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.75rem;
        }

        /* Individual Magazine Cards */
        .region-related-content__card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            display: flex;
            flex-direction: column;
        }
        .region-related-content__card:hover {
            transform: translateY(-4px);
            border-color: #cbd5e1;
            box-shadow: 0 12px 24px -10px rgba(15, 23, 42, 0.1);
        }
        .card-inner {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        /* Card Typography */
        .region-related-content__card h3 {
            font-family: "Playfair Display", Georgia, serif;
            font-size: 1.35rem;
            line-height: 1.4;
            margin-bottom: 0.75rem;
            font-weight: 700;
        }
        .region-related-content__card h3 a {
            color: #0f172a;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .region-related-content__card h3 a:hover {
            color: #d97706;
        }
        .region-related-content__card p {
            color: #475569;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
            display: -webkit-box;
            -webkit-line-clamp: 3; /* Safely clips text at 3 lines */
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Action Link inside Card */
        .read-more-link {
            margin-top: auto; /* Pushes link cleanly to the bottom */
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #d97706;
            letter-spacing: 0.5px;
            transition: color 0.2s ease;
        }
        .read-more-link:hover {
            color: #b45309;
        }

        /* Responsive Breakdown */
        @media (max-width: 560px) {
            .region-selector {
                align-items: stretch;
                flex-direction: column;
                gap: 0.5rem;
            }
            .region-selector__select {
                width: 100%;
            }
            .region-related-title {
                font-size: 1.5rem;
            }
        }
    </style>
<?php endif; ?>