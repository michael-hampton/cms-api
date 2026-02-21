<?php
/**
 * Page Card Partial View
 *
 * @var object|array $page - The page object or array
 * @var bool $showToolbar - Whether to show the toolbar (default: false)
 */

$showToolbar = $showToolbar ?? false;


// Extract access control information
$accessLevel = $page->access['access_level'] ?? 'free';
$canView = $page->access['can_view'] ?? true;
$denialReason = $page->access['denial_reason'] ?? null;
$isLocked = !$canView;

// Image resolution logic - handle both array and object
$imageUrl = '';
$cropOverrides = $page->crop_overrides ?? null;
$resolvedImages = $page->resolved_images ?? null;
$useAsHero = ($page->listing_use_as_hero === true || $page->listing_use_as_hero === 1);

if ($useAsHero) {
    if (isset($cropOverrides['hero-banner']['imageUrl'])) {
        $imageUrl = $cropOverrides['hero-banner']['imageUrl'];
    } elseif (isset($resolvedImages['hero-banner']['image_url'])) {
        $imageUrl = $resolvedImages['hero-banner']['image_url'];
    }
} else {
    if (isset($cropOverrides['listing-card']['imageUrl'])) {
        $imageUrl = $cropOverrides['listing-card']['imageUrl'];
    } elseif (isset($resolvedImages['listing-card']['image_url'])) {
        $imageUrl = $resolvedImages['listing-card']['image_url'];
    }
}

if (!$imageUrl && isset($page->image->url)) {
    $imageUrl = $page->image->url;
}

// Extract common fields
$pageId = $page->id;
$pageTitle = $page->title;
$pageUrl = $page->getUrlAttribute();
$metaDescription = $page->meta_description ?? '';
$publishedAt = $page->published_at ?? null;
$categories = $page->categories ?? [];
$authors = $page->authors ?? [];
$tags = $page->tags ?? [];
?>

<article class="page-card <?= $isLocked ? 'page-card-locked' : '' ?>">
    <?php if ($imageUrl): ?>
        <div class="page-card-image">
            <?php if ($isLocked): ?>
                <div class="access-overlay">
                    <div class="lock-icon-wrapper">
                        <svg class="lock-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($canView): ?>
                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?>">
            <?php else: ?>
                <img src="<?= htmlspecialchars($imageUrl) ?>" alt="<?= htmlspecialchars($pageTitle) ?>"
                     style="filter: blur(2px);">
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="page-card-image">
            <?php if ($isLocked): ?>
                <div class="access-overlay">
                    <div class="lock-icon-wrapper">
                        <svg class="lock-icon" width="48" height="48" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                </div>
            <?php endif; ?>
            📄
        </div>
    <?php endif; ?>

    <?php if ($showToolbar && $canView): ?>
        @include('components/utility-bar', ['page' => $page])
    <?php endif; ?>

    <div class="page-card-content <?= $isLocked ? 'content-locked' : '' ?>">
        <!-- Access Level Badge -->
        <?php if ($accessLevel !== 'free'): ?>
            <div class="access-badge-wrapper">
                <span class="access-badge access-<?= $accessLevel ?>">
                    <?php if ($accessLevel === 'premium'): ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                        </svg>
                        Premium
                    <?php else: ?>
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                        </svg>
                        Members Only
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!empty($categories) && (is_array($categories) || (is_object($categories) && count($categories) > 0))): ?>
            <div class="page-meta">
                <?php
                $categoryList = is_array($categories) ? array_slice($categories, 0, 2) : $categories->take(2);
                foreach ($categoryList as $category):
                    $catSlug = is_array($category) ? $category['slug'] : $category->slug;
                    $catName = is_array($category) ? $category['name'] : $category->name;
                    ?>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/category/<?= htmlspecialchars($catSlug) ?>"
                       class="tags-badge">
                        <?= htmlspecialchars($catName) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3 class="page-card-title <?= $isLocked ? 'title-locked' : '' ?>">
            <?php if ($canView): ?>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>">
                    <?= htmlspecialchars($pageTitle) ?>
                </a>
            <?php else: ?>
                <?= htmlspecialchars($pageTitle) ?>
            <?php endif; ?>
        </h3>

        <?php if ($metaDescription): ?>
            <p class="page-excerpt <?= $isLocked ? 'excerpt-blurred' : '' ?>">
                <?= htmlspecialchars(substr($metaDescription, 0, 150)) ?>
                <?= strlen($metaDescription) > 150 ? '...' : '' ?>
            </p>
        <?php endif; ?>

        <div class="page-footer">
            <?php if ($canView): ?>
                <div class="page-author-date">
                    <?php if (!empty($authors) && (is_array($authors) || (is_object($authors) && count($authors) > 0))): ?>
                        <div class="page-authors">
                            <span class="page-author">
                                By
                                <?php
                                $authorList = is_array($authors) ? array_slice($authors, 0, 3) : $authors->take(3)->toArray();
                                $authorNames = array_map(function ($author) {
                                    $authorSlug = is_array($author) ? $author['slug'] : $author->slug;
                                    $authorName = is_array($author) ? $author['name'] : $author->name;
                                    return '<a href="/' . \App\Framework\Support\SiteContext::slug() . '/authors/' . $authorSlug . '">' . htmlspecialchars($authorName) . '</a>';
                                }, $authorList);

                                $authorCount = is_array($authors) ? count($authors) : $authors->count();
                                if ($authorCount > 3) {
                                    echo implode(', ', $authorNames) . ' +' . ($authorCount - 3);
                                } else {
                                    echo implode(', ', $authorNames);
                                }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>
                    <?php if ($publishedAt): ?>
                        <span class="page-date">
                            <?= is_object($publishedAt) ? $publishedAt->format('M j, Y') : date('M j, Y', strtotime($publishedAt)) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>"
                   class="page-link">
                    Read More
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            <?php else: ?>
                <button class="btn-upgrade-card" onclick="showSubscriptionModal('<?= $denialReason ?>')">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <?php
                    if ($denialReason === 'member_required') {
                        echo 'Sign Up to Read';
                    } elseif ($denialReason === 'published_after_subscription') {
                        echo 'Resubscribe to Access';
                    } elseif ($denialReason === 'published_before_subscription') {
                        echo 'Subscribe to Access';
                    } else {
                        echo 'Subscribe to Read';
                    }
                    ?>
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($tags) && (is_array($tags) || (is_object($tags) && count($tags) > 0))): ?>
            <div class="page-tags">
                <?php
                $tagList = is_array($tags) ? array_slice($tags, 0, 3) : $tags->take(3);
                foreach ($tagList as $tag):
                    $tagSlug = is_array($tag) ? $tag['slug'] : $tag->slug;
                    $tagName = is_array($tag) ? $tag['name'] : $tag->name;
                    ?>
                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/tags/<?= htmlspecialchars($tagSlug) ?>"
                       class="tag-mini">
                        #<?= htmlspecialchars($tagName) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</article>