<div class="categories-widget">
    <div class="categories-widget-header">
        <h2 class="categories-widget-title">
            <svg class="categories-icon" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
            </svg>
            Explore Categories
        </h2>
        <p class="categories-widget-subtitle">Discover content by topic</p>
    </div>

    <div class="categories-grid">
        <?php if (!empty($categories) && count($categories) > 0): ?>
            <?php foreach ($categories as $category): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/category/<?= urlencode($category->slug) ?>"
                   class="category-card">
                    <?php if ($category->icon): ?>
                        <span class="category-icon"><?= $category->icon ?></span>
                    <?php endif; ?>
                    <div class="category-info">
                        <h3 class="category-name"><?= htmlspecialchars($category->name) ?></h3>
                        <?php if ($category->description): ?>
                            <p class="category-description">
                                <?= htmlspecialchars(substr($category->description, 0, 80)) ?><?= strlen($category->description) > 80 ? '...' : '' ?>
                            </p>
                        <?php endif; ?>
                        <span class="category-count"><?= $category->pages_count ?? 0 ?> <?= ($category->pages_count ?? 0) === 1 ? 'page' : 'pages' ?></span>
                    </div>
                    <svg class="category-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="no-categories">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                <p>No categories available yet</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .categories-widget {
        background: white;
        border-radius: 16px;
        padding: 2rem;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        margin: 2rem 0;
    }

    .categories-widget-header {
        margin-bottom: 1.5rem;
        text-align: center;
    }

    .categories-widget-title {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.75rem;
        font-size: 1.5rem;
        font-weight: 700;
        color: #111827;
        margin-bottom: 0.5rem;
    }

    .categories-icon {
        stroke-width: 2;
        color: #3b82f6;
    }

    .categories-widget-subtitle {
        color: #6b7280;
        font-size: 0.95rem;
    }

    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1rem;
    }

    .category-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.25rem;
        background: #f9fafb;
        border: 2px solid #e5e7eb;
        border-radius: 12px;
        text-decoration: none;
        color: inherit;
        transition: all 0.3s ease;
    }

    .category-card:hover {
        background: #eff6ff;
        border-color: #3b82f6;
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(59, 130, 246, 0.15);
    }

    .category-icon {
        font-size: 2rem;
        flex-shrink: 0;
    }

    .category-info {
        flex: 1;
        min-width: 0;
    }

    .category-name {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
        margin-bottom: 0.25rem;
    }

    .category-description {
        font-size: 0.875rem;
        color: #6b7280;
        line-height: 1.4;
        margin: 0 0 0.5rem 0;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
    }

    .category-count {
        font-size: 0.75rem;
        color: #3b82f6;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .category-arrow {
        flex-shrink: 0;
        stroke-width: 2;
        color: #9ca3af;
        transition: all 0.3s ease;
    }

    .category-card:hover .category-arrow {
        color: #3b82f6;
        transform: translateX(4px);
    }

    .no-categories {
        grid-column: 1 / -1;
        text-align: center;
        padding: 3rem 1rem;
        color: #9ca3af;
    }

    .no-categories svg {
        stroke-width: 1.5;
        margin-bottom: 1rem;
    }

    .no-categories p {
        font-size: 1rem;
        color: #6b7280;
    }

    @media (max-width: 768px) {
        .categories-widget {
            padding: 1.5rem;
        }

        .categories-grid {
            grid-template-columns: 1fr;
        }

        .category-card {
            padding: 1rem;
        }
    }
</style>