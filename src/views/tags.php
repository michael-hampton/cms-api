<style>
    .featured-badge {
        display: inline-block;
        background-color: #fbbf24;
        color: #78350f;
        font-size: 0.75rem;
        font-weight: 600;
        padding: 2px 8px;
        border-radius: 4px;
        margin-left: 8px;
    }

    .featured-tag {
        border: 2px solid #fbbf24;
        background-color: #fef3c7;
    }

    .featured-icon {
        color: #fbbf24;
        margin-right: 4px;
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
</style>

<?php if ($page->tags): ?>
    <div class="page-tags">
        <?php foreach ($page->tags as $tag): ?>
            <span class="tag-badge <?= $tag->is_featured ? 'featured-tag' : '' ?>">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/tags/<?= urlencode($tag->slug) ?>"
                   class="tag-link">
                    <?php if ($tag->is_featured): ?>
                        <span class="featured-icon">★</span>
                    <?php endif; ?>
                    <?= htmlspecialchars($tag->name) ?>
                </a>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>