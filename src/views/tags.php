<?php if ($page->tags): ?>
    <div class="page-tags">
        <?php foreach ($page->tags as $tag): ?>
            <span class="tag-badge">
                                        <a href="/tags/<?= urlencode($tag->slug) ?>" class="tag-link">
                                             <?= htmlspecialchars($tag->name) ?>
                                        </a>
                                    </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>