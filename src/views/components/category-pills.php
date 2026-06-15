<?php if ($page->categories): ?>
    <div class="page-categories">
        <div class="categories-header">
            <svg class="categories-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            <span class="categories-label">Categories</span>
        </div>
        <div class="categories-list">
            <?php foreach ($page->categories as $category): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/categories/<?= urlencode($category->slug) ?>"
                   class="category-badge">
                    <?php if ($category->icon): ?>
                        <span class="category-badge-icon"><?= $category->icon ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($category->name) ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
    .page-categories {
        margin: 1.5rem 0;
        padding: 1.25rem;
        background: #f8f9fa;
        border-radius: 12px;
        border: 2px solid #e9ecef;
    }

    .categories-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .categories-icon {
        stroke-width: 2;
        color: #6c757d;
    }

    .categories-label {
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .categories-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .category-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        background: white;
        color: #495057;
        text-decoration: none;
        border-radius: 8px;
        font-size: 0.9rem;
        font-weight: 500;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
    }

    .category-badge:hover {
        background: #ffffff;
        border-color: #adb5bd;
        color: #212529;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .category-badge-icon {
        font-size: 1.1rem;
        line-height: 1;
    }

    @media (max-width: 768px) {
        .page-categories {
            padding: 1rem;
        }

        .categories-list {
            gap: 0.5rem;
        }

        .category-badge {
            padding: 0.4rem 0.8rem;
            font-size: 0.85rem;
        }
    }
</style>
