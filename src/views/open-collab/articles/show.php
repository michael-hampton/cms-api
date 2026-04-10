<article style="max-width: 800px; margin: 0 auto; line-height: 1.6;">
    <h1><?= htmlspecialchars($article->title) ?></h1>
    <p class="meta">By <?= htmlspecialchars($article->author_name) ?> | <?= $article->published_at ?></p>

    <div class="content">
        <?php if ($accessGranted): ?>
            <?= $article->content ?>
        <?php else: ?>
            <div class="preview-content" style="mask-image: linear-gradient(to bottom, black 50%, transparent 100%);">
                <?= $article->preview_text ?>
            </div>
            <?php include 'partials/paywall.php'; ?>
        <?php endif; ?>
    </div>
</article>