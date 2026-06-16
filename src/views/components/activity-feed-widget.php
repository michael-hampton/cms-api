<?php
/**
 * activity-feed-widget.php
 *
 * Expected variables:
 *   $feedPages – Collection of fully-loaded Page models
 *   $siteSlug  – current site slug
 */

use App\Framework\Support\Collection;

if (empty($feedPages) || $feedPages->isEmpty()) return;

$feedTitle = $feedTitle ?? 'Activity Feed';
?>

<section class="activity-feed-card">
    <div class="activity-feed-header">
        <h2 class="activity-feed-title">
            <span class="pulse-icon"></span>
            <?= htmlspecialchars($feedTitle) ?>
        </h2>
    </div>

    <div class="activity-feed-scroll-area">
        <div class="activity-feed-items">
            <?php foreach ($feedPages as $page):
                $url = '/' . htmlspecialchars($siteSlug) . '/' . htmlspecialchars($page->slug);
                $image = $page->metadata?->featured_image ?? null;
                $categories = $page->categories instanceof Collection
                    ? $page->categories
                    : new Collection();
                $authors = $page->authors instanceof Collection
                    ? $page->authors
                    : new Collection();
                $comments = $page->comments instanceof Collection
                    ? $page->comments
                    : new Collection();
                $primaryCategory = $categories->first();
                ?>
                <div class="feed-card">
                    <?php if ($image): ?>
                        <div class="feed-card-thumb">
                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($page->title) ?>">
                        </div>
                    <?php endif; ?>

                    <div class="feed-card-content">
                        <div class="feed-card-meta">
                            <?php if ($primaryCategory): ?>
                                <span class="feed-category"><?= htmlspecialchars($primaryCategory->name) ?></span>
                            <?php endif; ?>
                            <span class="feed-time"><?= diffForHumans($page->created_at) ?></span>
                        </div>

                        <h3 class="feed-card-title">
                            <a href="<?= $url ?>"><?= htmlspecialchars($page->title) ?></a>
                        </h3>

                        <div class="feed-card-footer">
                            <div class="feed-authors">
                                <?php foreach ($authors->take(2) as $author): ?>
                                    <span class="feed-author-tag">@<?= htmlspecialchars($author->name) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php if ($comments->count() > 0): ?>
                                <span class="feed-comments">💬 <?= $comments->count() ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<style>
    .activity-feed-card { background:#fff; border-radius:16px; border:1px solid #e5e7eb; display:flex; flex-direction:column; max-height:700px; box-shadow:0 4px 20px -2px rgba(0,0,0,.05); overflow:hidden; }
    .activity-feed-header { padding:1.25rem; background:#fff; border-bottom:1px solid #f3f4f6; z-index:10; }
    .activity-feed-title { display:flex; align-items:center; gap:.75rem; font-size:1.25rem; font-weight:700; margin:0; color:#111827; }
    .pulse-icon { width:8px; height:8px; background:#10b981; border-radius:50%; box-shadow:0 0 0 0 rgba(16,185,129,.7); animation:pulse 2s infinite; }
    .activity-feed-scroll-area { flex:1; overflow-y:auto; padding:1rem; scrollbar-width:thin; scrollbar-color:#d1d5db transparent; }
    .activity-feed-scroll-area::-webkit-scrollbar { width:6px; }
    .activity-feed-scroll-area::-webkit-scrollbar-thumb { background:#d1d5db; border-radius:10px; }
    .activity-feed-items { display:flex; flex-direction:column; gap:1rem; }
    .feed-card { display:flex; gap:1rem; padding:1rem; border-radius:12px; background:#f9fafb; transition:all .2s ease; border:1px solid transparent; }
    .feed-card:hover { background:#fff; border-color:#3b82f6; transform:translateY(-2px); box-shadow:0 4px 12px rgba(0,0,0,.05); }
    .feed-card-thumb { width:80px; height:80px; flex-shrink:0; border-radius:8px; overflow:hidden; }
    .feed-card-thumb img { width:100%; height:100%; object-fit:cover; }
    .feed-card-content { flex:1; min-width:0; }
    .feed-card-meta { display:flex; justify-content:space-between; margin-bottom:.25rem; font-size:.75rem; }
    .feed-category { color:#3b82f6; font-weight:600; text-transform:uppercase; }
    .feed-time { color:#9ca3af; }
    .feed-card-title { font-size:.95rem; font-weight:600; margin:0 0 .5rem; line-height:1.4; }
    .feed-card-title a { text-decoration:none; color:#1f2937; }
    .feed-card-footer { display:flex; justify-content:space-between; align-items:center; font-size:.8rem; }
    .feed-author-tag { color:#6b7280; margin-right:.5rem; }
    .feed-comments { color:#6b7280; font-weight:500; }
    @keyframes pulse { 0%{transform:scale(.95);box-shadow:0 0 0 0 rgba(16,185,129,.7)} 70%{transform:scale(1);box-shadow:0 0 0 6px rgba(16,185,129,0)} 100%{transform:scale(.95);box-shadow:0 0 0 0 rgba(16,185,129,0)} }
</style>
