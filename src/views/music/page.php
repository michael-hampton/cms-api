@include('music/header', ['menu' => $menu])

<?php
use App\Framework\Support\SiteContext;

// Prepare page data
$title = $page->title ?? SiteContext::name();
$description = $page->meta_description ?? '';

// Separate sidebar and main content blocks
$sidebarBlocks = [];
$mainBlocks = [];

if ($page && $page->blocks) {
    foreach ($page->blocks as $block) {
        $blockData = $block->data ?? [];
        if (isset($blockData['context']) && $blockData['context'] === 'sidebar') {
            $sidebarBlocks[] = $block;
        } else {
            $mainBlocks[] = $block;
        }
    }
}

$hasSidebar = !empty($sidebarBlocks);
?>

<main class="page-main" id="main-content">
    <div class="container">
        <?php if ($page): ?>
            <div class="page-layout <?= $hasSidebar ? 'has-sidebar' : 'full-width' ?>">

                <!-- Main Content Area -->
                <article class="main-content">

                    <!-- Page Header -->
                    <?php if ($page->page_type !== 'custom'): ?>
                        <header class="page-header">
                            <h1 class="page-title"><?= htmlspecialchars($page->title) ?></h1>

                            <div class="page-meta">
                                <!-- Categories -->
                                <?php if (!empty($page->categories)): ?>
                                    <div class="page-categories">
                                        <?php foreach ($page->categories as $index => $category): ?>
                                            <a href="/category/<?= urlencode($category->slug) ?>" class="category-badge">
                                                <?= htmlspecialchars($category->name) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Tags -->
                                <?php if (!empty($page->tags)): ?>
                                    <div class="page-tags">
                                        <?php foreach ($page->tags as $tag): ?>
                                            <a href="/tags/<?= urlencode($tag->slug) ?>" class="tag-badge">
                                                #<?= htmlspecialchars($tag->name) ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </header>
                    <?php endif; ?>

                    <!-- Main Content Blocks -->
                    <div class="page-content">
                        <?php foreach ($mainBlocks as $block): ?>
                            <?= $blockParserService->buildBlock(
                                $page->id,
                                $block->data + ['type' => $block->type],
                                $block->order
                            ) ?>
                        <?php endforeach; ?>
                    </div>

                    <!-- Author Bio -->
                    <?php if ($page->author_id): ?>
                        <?php
                        $author = \App\Models\Author::find($page->author_id);
                        if ($author):
                            ?>
                            <section class="author-bio">
                                <div class="author-bio-content">
                                    <?php if ($author->avatar): ?>
                                        <div class="author-avatar">
                                            <img src="<?= htmlspecialchars($author->avatar) ?>"
                                                 alt="<?= htmlspecialchars($author->name) ?>">
                                        </div>
                                    <?php endif; ?>

                                    <div class="author-info">
                                        <h3 class="author-name">
                                            About <?= htmlspecialchars($author->name) ?>
                                        </h3>

                                        <?php if ($author->bio): ?>
                                            <p class="author-bio-text">
                                                <?php
                                                $bio = $author->bio;
                                                $truncated = strlen($bio) > 200 ? substr($bio, 0, 200) . '...' : $bio;
                                                echo htmlspecialchars($truncated);
                                                ?>
                                            </p>
                                        <?php endif; ?>

                                        <a href="/authors/<?= htmlspecialchars($author->slug) ?>"
                                           class="author-link">
                                            View Full Profile →
                                        </a>
                                    </div>
                                </div>
                            </section>
                        <?php endif; ?>
                    <?php endif; ?>

                    <!-- Blog Comments -->
                    <?php if ($page->page_type === 'blog'): ?>
                        <section class="comments-section">
                            <h3 class="comments-title">Comments</h3>

                            <div class="comments-list">
                                <?php if (isset($comments) && !empty($comments)): ?>
                                    <?php foreach ($comments as $comment): ?>
                                        <div class="comment" data-comment-id="<?= $comment->id ?>">
                                            <div class="comment-header">
                                                <strong class="comment-author">
                                                    <?= htmlspecialchars($comment->name) ?>
                                                </strong>
                                                <span class="comment-date">
                                                    <?= date('M j, Y', strtotime($comment->created_at)) ?>
                                                </span>
                                            </div>
                                            <div class="comment-content">
                                                <?= nl2br(htmlspecialchars($comment->content)) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="no-comments">
                                        No comments yet. Be the first to share your thoughts!
                                    </p>
                                <?php endif; ?>
                            </div>

                            <!-- Comment Form -->
                            <div class="comment-form-wrapper">
                                <h4 class="comment-form-title">Leave a Comment</h4>
                                <form method="POST" action="/comments" class="comment-form">
                                    <input type="hidden" name="page_id" value="<?= $page->id ?>">

                                    <div class="form-row">
                                        <div class="form-group">
                                            <label for="comment-name">Name *</label>
                                            <input type="text"
                                                   id="comment-name"
                                                   name="name"
                                                   required
                                                   class="form-control">
                                        </div>
                                        <div class="form-group">
                                            <label for="comment-email">Email *</label>
                                            <input type="email"
                                                   id="comment-email"
                                                   name="email"
                                                   required
                                                   class="form-control">
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <label for="comment-content">Comment *</label>
                                        <textarea id="comment-content"
                                                  name="content"
                                                  required
                                                  class="form-control"
                                                  rows="5"></textarea>
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Post Comment
                                    </button>
                                </form>
                            </div>
                        </section>
                    <?php endif; ?>

                    <!-- Social Sharing -->
                    <?php if (isset($page->social) && $page->social->enable_sharing): ?>
                        <div class="social-sharing">
                            <h4 class="social-sharing-title">Share this article</h4>
                            <div class="social-buttons">
                                <?php
                                $platforms = $page->social->platforms ?? [];
                                $shareText = urlencode($page->social->share_text ?? $page->title);
                                $currentUrl = urlencode("https://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
                                ?>

                                <?php if (in_array('facebook', $platforms)): ?>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $currentUrl ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="social-btn social-facebook">
                                        <span class="social-icon">f</span>
                                        Facebook
                                    </a>
                                <?php endif; ?>

                                <?php if (in_array('twitter', $platforms)): ?>
                                    <a href="https://twitter.com/intent/tweet?text=<?= $shareText ?>&url=<?= $currentUrl ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="social-btn social-twitter">
                                        <span class="social-icon">𝕏</span>
                                        Twitter
                                    </a>
                                <?php endif; ?>

                                <?php if (in_array('linkedin', $platforms)): ?>
                                    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= $currentUrl ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="social-btn social-linkedin">
                                        <span class="social-icon">in</span>
                                        LinkedIn
                                    </a>
                                <?php endif; ?>

                                <?php if (in_array('email', $platforms)): ?>
                                    <a href="mailto:?subject=<?= $shareText ?>&body=<?= $currentUrl ?>"
                                       class="social-btn social-email">
                                        <span class="social-icon">✉</span>
                                        Email
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </article>

                <!-- Sidebar -->
                <?php if ($hasSidebar): ?>
                    <aside class="page-sidebar">
                        <?php foreach ($sidebarBlocks as $block): ?>
                            <?= $blockParserService->buildBlock(
                                $page->id,
                                $block->data + ['type' => $block->type],
                                $block->order
                            ) ?>
                        <?php endforeach; ?>
                    </aside>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="page-not-found">
                <h1>404 - Page Not Found</h1>
                <p>The page you're looking for doesn't exist.</p>
                <a href="<?= SiteContext::getUrl() ?>" class="btn btn-primary">
                    Go Home
                </a>
            </div>
        <?php endif; ?>
    </div>
</main>

@js('carousel.js');
@include('music/footer')