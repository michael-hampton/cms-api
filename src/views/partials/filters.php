<?php
$currentSort = $currentSort ?? 'latest';
$currentCategory = $_GET['category'] ?? '';
$currentAuthor = $_GET['author'] ?? '';
$baseUrl = strtok($_SERVER["REQUEST_URI"], '?');

// Get unique categories and authors from pages
$categories = [];
$authors = [];
if ($pages->count() > 0) {
    foreach ($pages as $page) {

        if ($page->categories->count() > 0) {
            foreach ($page->categories as $cat) {
                $categories[$cat->id] = $cat->name;
            }
        }
        if ($page->authors->count() > 0) {
            foreach ($page->authors as $author) {
                $authors[$author->id] = $author->name;
            }
        }
    }
}
?>

<div class="filters-section">
    <div class="filters-header">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="4" y1="21" x2="4" y2="14"></line>
            <line x1="4" y1="10" x2="4" y2="3"></line>
            <line x1="12" y1="21" x2="12" y2="12"></line>
            <line x1="12" y1="8" x2="12" y2="3"></line>
            <line x1="20" y1="21" x2="20" y2="16"></line>
            <line x1="20" y1="12" x2="20" y2="3"></line>
            <line x1="1" y1="14" x2="7" y2="14"></line>
            <line x1="9" y1="8" x2="15" y2="8"></line>
            <line x1="17" y1="16" x2="23" y2="16"></line>
        </svg>
        <span>Filter Reviews</span>
    </div>
    <div class="filters-grid">
        <?php if (is_array($categories) && count($categories) > 1): ?>
            <div class="filter-group">
                <label for="category-filter">Category</label>
                <select id="category-filter" onchange="applyFilters()">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $currentCategory == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <?php if (!empty($authors)): ?>
            <div class="filter-group">
                <label for="author-filter">Author</label>
                <select id="author-filter" onchange="applyFilters()">
                    <option value="">All Authors</option>
                    <?php foreach ($authors as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $currentAuthor == $id ? 'selected' : '' ?>>
                            <?= htmlspecialchars($name) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>

        <div class="filter-group">
            <label for="sort-filter">Sort By</label>
            <select id="sort-filter" onchange="applyFilters()">
                <option value="latest" <?= $currentSort === 'latest' ? 'selected' : '' ?>>Latest</option>
                <option value="oldest" <?= $currentSort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                <option value="title" <?= $currentSort === 'title' ? 'selected' : '' ?>>Title (A-Z)</option>
            </select>
        </div>
    </div>
</div>

<script>
    function applyFilters() {
        const category = document.getElementById('category-filter')?.value || '';
        const author = document.getElementById('author-filter')?.value || '';
        const sort = document.getElementById('sort-filter')?.value || 'latest';

        const params = new URLSearchParams();
        if (category) params.set('category', category);
        if (author) params.set('author', author);
        if (sort) params.set('sort', sort);

        window.location.href = '<?= $baseUrl ?>' + (params.toString() ? '?' + params.toString() : '');
    }
</script>