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
        <div class="page-card-actions-ribbon">
            <button class="action-btn" onclick="toggleShareDropdown(this)" title="Share">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="18" cy="5" r="3"/>
                    <circle cx="6" cy="12" r="3"/>
                    <circle cx="18" cy="19" r="3"/>
                    <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"/>
                    <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"/>
                </svg>

                <div class="share-dropdown" onclick="event.stopPropagation()">
                    <div class="share-option"
                         onclick="shareToFacebook('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#1877f2">
                                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                            </svg>
                        </div>
                        <span class="share-option-text">Facebook</span>
                    </div>

                    <div class="share-option"
                         onclick="shareToTwitter('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#1da1f2">
                                <path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/>
                            </svg>
                        </div>
                        <span class="share-option-text">Twitter</span>
                    </div>

                    <div class="share-option"
                         onclick="shareToLinkedIn('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#0077b5">
                                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
                            </svg>
                        </div>
                        <span class="share-option-text">LinkedIn</span>
                    </div>

                    <div class="share-option"
                         onclick="shareToWhatsApp('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#25d366">
                                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                            </svg>
                        </div>
                        <span class="share-option-text">WhatsApp</span>
                    </div>

                    <div class="share-option"
                         onclick="shareToReddit('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>', '<?= htmlspecialchars($pageTitle) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="#ff4500">
                                <path d="M12 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0zm5.01 4.744c.688 0 1.25.561 1.25 1.249a1.25 1.25 0 0 1-2.498.056l-2.597-.547-.8 3.747c1.824.07 3.48.632 4.674 1.488.308-.309.73-.491 1.207-.491.968 0 1.754.786 1.754 1.754 0 .716-.435 1.333-1.01 1.614a3.111 3.111 0 0 1 .042.52c0 2.694-3.13 4.87-7.004 4.87-3.874 0-7.004-2.176-7.004-4.87 0-.183.015-.366.043-.534A1.748 1.748 0 0 1 4.028 12c0-.968.786-1.754 1.754-1.754.463 0 .898.196 1.207.49 1.207-.883 2.878-1.43 4.744-1.487l.885-4.182a.342.342 0 0 1 .14-.197.35.35 0 0 1 .238-.042l2.906.617a1.214 1.214 0 0 1 1.108-.701zM9.25 12C8.561 12 8 12.562 8 13.25c0 .687.561 1.248 1.25 1.248.687 0 1.248-.561 1.248-1.249 0-.688-.561-1.249-1.249-1.249zm5.5 0c-.687 0-1.248.561-1.248 1.25 0 .687.561 1.248 1.249 1.248.688 0 1.249-.561 1.249-1.249 0-.687-.562-1.249-1.25-1.249zm-5.466 3.99a.327.327 0 0 0-.231.094.33.33 0 0 0 0 .463c.842.842 2.484.913 2.961.913.477 0 2.105-.056 2.961-.913a.361.361 0 0 0 .029-.463.33.33 0 0 0-.464 0c-.547.533-1.684.73-2.512.73-.828 0-1.979-.196-2.512-.73a.326.326 0 0 0-.232-.095z"/>
                            </svg>
                        </div>
                        <span class="share-option-text">Reddit</span>
                    </div>

                    <div class="share-option"
                         onclick="copyLink('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>')">
                        <div class="share-option-icon">
                            <svg viewBox="0 0 24 24" width="18" height="18" stroke="currentColor" fill="none"
                                 stroke-width="2">
                                <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/>
                                <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/>
                            </svg>
                        </div>
                        <span class="share-option-text">Copy Link</span>
                    </div>
                </div>
            </button>

            <button class="action-btn"
                    onclick="openCommentModal('/<?= \App\Framework\Support\SiteContext::slug() ?><?= htmlspecialchars($pageUrl) ?>', '<?= $pageId ?>')"
                    title="Comment">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                </svg>
            </button>

            <button class="action-btn" onclick="openNewsletterModal()" title="Newsletter">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                    <line x1="16" y1="2" x2="16" y2="6"/>
                    <line x1="8" y1="2" x2="8" y2="6"/>
                    <line x1="3" y1="10" x2="21" y2="10"/>
                </svg>
            </button>

            <button class="action-btn" title="Bookmark">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/>
                </svg>
            </button>
        </div>
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