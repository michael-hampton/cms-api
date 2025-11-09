<?php if (!empty($page->categories)): ?>
    <div class="page-categories">
        <span class="categories-label">Categories:</span>
        <?php foreach ($page->categories as $index => $category): ?>
        <a href="/category/<?= urlencode($category->slug) ?>" class="category-link">
            <?= htmlspecialchars($category->name) ?>
            </a><?= $index < count($page->categories) - 1 ? ', ' : '' ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
