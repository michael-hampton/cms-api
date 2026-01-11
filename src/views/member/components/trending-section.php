<style>
    .content-section {
        margin-bottom: 3rem;
    }

    .section-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1.5rem;
    }

    .nav-arrow {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        border: 2px solid var(--border-color);
        background: white;
        color: var(--text-primary);
        font-size: 1.25rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .nav-arrow:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateX(4px);
    }

    .content-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .content-card,
    .conversation-card {
        background: white;
        border-radius: 1rem;
        overflow: hidden;
        box-shadow: var(--shadow);
        transition: all 0.3s ease;
        cursor: pointer;
    }

    .content-card:hover,
    .conversation-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
    }

    .content-image {
        position: relative;
        width: 100%;
        height: 200px;
        overflow: hidden;
        background: var(--bg-light);
    }

    .content-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .content-badge {
        position: absolute;
        top: 1rem;
        left: 1rem;
        background: var(--primary-color);
        color: white;
        padding: 0.375rem 0.875rem;
        border-radius: 0.25rem;
        font-size: 0.8125rem;
        font-weight: 600;
        text-transform: capitalize;
    }

    .content-title {
        padding: 1.25rem;
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        line-height: 1.5;
    }

    .conversation-text {
        padding: 1.25rem;
        padding-bottom: 0.75rem;
        font-size: 0.9375rem;
        color: var(--text-primary);
        line-height: 1.6;
    }

    .conversation-stats {
        display: flex;
        gap: 1rem;
        padding: 0 1.25rem 1.25rem;
    }

    .stat-btn {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        background: none;
        border: none;
        color: var(--text-secondary);
        font-size: 0.9375rem;
        cursor: pointer;
        transition: color 0.2s ease;
    }

    .stat-btn:hover {
        color: var(--primary-color);
    }

    .stat-btn span:first-child {
        font-size: 1.125rem;
    }

    .back-to-top {
        padding: 0.875rem 2.5rem;
        background: white;
        color: var(--text-primary);
        border: 2px solid var(--border-color);
        border-radius: 2rem;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .back-to-top:hover {
        border-color: var(--primary-color);
        color: var(--primary-color);
        transform: translateY(-2px);
    }

    @media (max-width: 640px) {
        .header-content {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }

        .main-content {
            padding: 1rem;
        }

        .promo-banner {
            min-height: 220px;
        }

        .banner-content {
            max-width: 100%;
            padding: 1.5rem;
        }

        .banner-content h2 {
            font-size: 1.5rem;
        }

        .banner-content p {
            font-size: 1rem;
            margin-bottom: 1.25rem;
        }

        .banner-image {
            opacity: 0.2;
            width: 100%;
        }

        .overview-grid {
            grid-template-columns: 1fr;
        }

        .content-grid {
            grid-template-columns: 1fr;
        }

        .section-header {
            flex-direction: column;
            align-items: flex-start;
            gap: 1rem;
        }
    }
</style>

<?php
/** @var \App\Framework\Support\Collection $trendingConversations */
if (empty($trendingConversations) || $trendingConversations->count() === 0) {
    return;
}
?>
<div class="content-section">
    <div class="section-header">
        <div>
            <h2 class="section-title">Trending Conversations</h2>
            <p style="color: var(--text-secondary); font-size: 0.9375rem;">Join the most popular discussions</p>
        </div>
        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/pages" class="nav-arrow"
           style="text-decoration: none;">→</a>
    </div>

    <div class="content-grid">
        <?php foreach ($trendingConversations as $page): ?>
            <article class="conversation-card"
                     onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/<?= htmlspecialchars($page->slug) ?>'">
                <div class="content-image">
                    <?php if ($page->categories && $page->categories->count() > 0): ?>
                        <span class="content-badge"><?= htmlspecialchars($page->categories->first()->name) ?></span>
                    <?php endif; ?>
                    <?php if ($page->metadata && $page->metadata->featured_image): ?>
                        <img src="<?= htmlspecialchars($page->metadata->featured_image) ?>"
                             alt="<?= htmlspecialchars($page->title) ?>">
                    <?php else: ?>
                        <img src="https://via.placeholder.com/300x200" alt="<?= htmlspecialchars($page->title) ?>">
                    <?php endif; ?>
                </div>
                <p class="conversation-text">
                    <?= htmlspecialchars(strlen($page->title) > 150 ? substr($page->title, 0, 150) . '...' : $page->title) ?>
                </p>
                <div class="conversation-stats">
                    <button class="stat-btn" onclick="event.stopPropagation();">
                        <span>👍</span>
                        <span><?= number_format($page->like_count_24h ?? 0) ?></span>
                    </button>
                    <button class="stat-btn" onclick="event.stopPropagation();">
                        <span>💬</span>
                        <span><?= number_format($page->comment_count_24h ?? 0) ?></span>
                    </button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</div>