<div class="article-card" style="border: 1px solid #eee; padding: 1.5rem; border-radius: 8px; margin-bottom: 1rem;">
    <div style="display: flex; justify-content: space-between; align-items: start;">
        <h3><?= htmlspecialchars($article->title) ?></h3>
        <?php if ($article->is_paid): ?>
            <span class="badge" style="background: #ffd700; padding: 0.2rem 0.5rem; font-size: 0.8rem;">PAID</span>
        <?php endif; ?>
    </div>
    <p style="color: #666;"><?= htmlspecialchars(substr($article->content, 0, 150)) ?>...</p>
    <div style="margin-top: 1rem; display: flex; justify-content: space-between; align-items: center;">
        <span class="status-tag"><?= strtoupper($article->status) ?></span>
        <a href="/articles/edit/<?= $article->id ?>" class="btn-link">Edit Article</a>
    </div>
</div>