<?php
$tagBase = $directoryBase ?? ('/' . \App\Framework\Support\SiteContext::slug());
?>

<?php if ($page->tags): ?>
    <div class="page-tags">
        <div class="tags-header">
            <svg class="tags-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            <span class="tags-label">Tags</span>
        </div>
        <div class="tags-list">
            <?php foreach ($page->tags as $tag): ?>
                <a href="<?= htmlspecialchars($tagBase . '/tags/' . rawurlencode($tag->slug), ENT_QUOTES, 'UTF-8') ?>"
                   class="tag-badge <?= $tag->is_featured ? 'featured-tag' : '' ?>">
                    <?php if ($tag->is_featured): ?>
                        <svg class="featured-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                    <?php endif; ?>
                    <span class="tag-hash">#</span><?= htmlspecialchars($tag->name) ?>
                    <?php if ($tag->is_featured): ?>
                        <span class="featured-badge">Featured</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<style>
    .page-tags {
        margin: 1.5rem 0;
        padding: 1.25rem;
        background: #f8f9fa;
        border-radius: 12px;
        border: 2px solid #e9ecef;
    }

    .tags-header {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
    }

    .tags-icon {
        stroke-width: 2;
        color: #6c757d;
    }

    .tags-label {
        font-weight: 600;
        font-size: 0.875rem;
        color: #495057;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .tags-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .tag-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        padding: 0.5rem 1rem;
        background: white;
        color: #495057;
        text-decoration: none;
        border-radius: 20px;
        font-size: 0.875rem;
        font-weight: 500;
        border: 2px solid #dee2e6;
        transition: all 0.3s ease;
    }

    .tag-badge:hover {
        background: #ffffff;
        border-color: #adb5bd;
        color: #212529;
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .tag-hash {
        color: #adb5bd;
        font-weight: 600;
    }

    .tag-badge:hover .tag-hash {
        color: #6c757d;
    }

    .featured-tag {
        background: #fff9e6;
        border: 2px solid #ffe59d;
        font-weight: 600;
    }

    .featured-tag:hover {
        background: #fffbf0;
        border-color: #ffd666;
    }

    .featured-icon {
        color: #f59e0b;
        flex-shrink: 0;
    }

    .featured-badge {
        display: inline-flex;
        align-items: center;
        background: #fef3c7;
        color: #92400e;
        font-size: 0.7rem;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        margin-left: 0.25rem;
    }

    .checkbox-label {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        user-select: none;
    }

    .checkbox-label input[type="checkbox"] {
        cursor: pointer;
    }

    @media (max-width: 768px) {
        .page-tags {
            padding: 1rem;
        }

        .tags-list {
            gap: 0.5rem;
        }

        .tag-badge {
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
        }

        .featured-badge {
            font-size: 0.65rem;
            padding: 2px 5px;
        }
    }
</style>
