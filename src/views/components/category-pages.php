<style>
    .category-pages-section {
        max-width: 1200px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .category-block {
        margin-bottom: 4rem;
    }

    .category-header {
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 2px solid #e5e7eb;
    }

    .category-title {
        font-size: 2rem;
        font-weight: 700;
        margin: 0 0 0.5rem 0;
        color: #111827;
    }

    .category-title a {
        color: inherit;
        text-decoration: none;
        transition: color 0.2s;
    }

    .category-title a:hover {
        color: #2563eb;
    }

    .category-description {
        color: #6b7280;
        margin: 0.5rem 0;
        font-size: 1rem;
        line-height: 1.5;
    }

    .view-all-link {
        display: inline-block;
        margin-top: 0.5rem;
        color: #2563eb;
        text-decoration: none;
        font-weight: 500;
        font-size: 0.875rem;
        transition: color 0.2s;
    }

    .view-all-link:hover {
        color: #1d4ed8;
    }

    .category-pages-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 1.5rem;
    }


    .page-card:hover .page-card-image img {
        transform: scale(1.05);
    }


    .page-card-title a {
        color: #111827;
        text-decoration: none;
        transition: color 0.2s;
    }

    .page-card-title a:hover {
        color: #2563eb;
    }

    .page-card-excerpt {
        color: #6b7280;
        font-size: 0.875rem;
        line-height: 1.6;
        margin: 0 0 1rem 0;
    }

    .page-card-meta {
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.75rem;
        color: #9ca3af;
    }

    .page-date,
    .page-authors {
        display: flex;
        align-items: center;
    }

    .no-pages-message,
    .no-categories-message {
        text-align: center;
        color: #6b7280;
        padding: 2rem;
        font-size: 1rem;
    }

    @media (max-width: 768px) {
        .category-pages-grid {
            grid-template-columns: 1fr;
        }

        .category-title {
            font-size: 1.5rem;
        }
    }
</style>

<?php
/**
 * Category Pages Component
 * Displays categories with their associated pages
 *
 * @var Collection $categories - Categories with their pages
 * @var string $site - Current site slug
 */

use App\Framework\Support\Collection;

?>

<div class="category-pages-section">
    <?php if (!empty($categories) && count($categories) > 0): ?>
        <?php foreach ($categories as $category): ?>
            <div class="category-block">
                <div class="category-header">
                    <h2 class="category-title">
                        <a href="/<?= $site ?>/category/<?= $category['category']->slug ?>">
                            <?= htmlspecialchars($category['category']->name) ?>
                        </a>
                    </h2>
                    <?php if (!empty($category['category']->description)): ?>
                        <p class="category-description"><?= htmlspecialchars($category['category']->description) ?></p>
                    <?php endif; ?>
                    <a href="/<?= $site ?>/category/<?= $category['category']->slug ?>" class="view-all-link">
                        View All →
                    </a>
                </div>

                <?php if (!empty($category['pages']) && count($category['pages']) > 0): ?>
                    <div class="category-pages-grid">
                        <?php foreach ($category['pages']->take(6) as $page): ?>
                            @include('components.page-card', ['page' => $page])
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-pages-message">No pages in this category yet.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p class="no-categories-message">No categories available.</p>
    <?php endif; ?>
</div>