<?php
/**
 * @var \App\Models\Site $site
 * @var \App\Framework\Support\Collection $newsletters
 * @var array $pagination
 * @var array $filters_applied
 * @var array $filterOptions
 * @var array $currentFilters
 */
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletters - <?= htmlspecialchars($site->name) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fa;
            color: #2c3e50;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 42px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 16px;
        }

        .header p {
            font-size: 18px;
            color: #718096;
        }

        /* Filter Section */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }

        .filter-actions {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: #667eea;
            color: white;
        }

        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2c3e50;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
        }

        .applied-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 16px;
        }

        .filter-tag {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #f8f9fa;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: #2c3e50;
        }

        .filter-tag button {
            background: none;
            border: none;
            color: #718096;
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            line-height: 1;
        }

        /* Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .results-count {
            font-size: 14px;
            color: #718096;
        }

        .sort-select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 32px;
            margin-bottom: 60px;
        }

        .newsletter-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .newsletter-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .newsletter-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
        }

        .newsletter-content {
            padding: 24px;
        }

        .newsletter-date {
            font-size: 13px;
            color: #667eea;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 12px;
        }

        .newsletter-title {
            font-size: 22px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 12px;
            line-height: 1.3;
        }

        .newsletter-excerpt {
            font-size: 15px;
            color: #718096;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .newsletter-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid #e2e8f0;
        }

        .read-more {
            color: #667eea;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .read-more:hover {
            color: #5a67d8;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
        }

        .pagination button {
            padding: 8px 16px;
            border: 2px solid #e2e8f0;
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination button:hover:not(:disabled) {
            border-color: #667eea;
            color: #667eea;
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 24px;
            opacity: 0.5;
        }

        .empty-state h2 {
            font-size: 24px;
            color: #4a5568;
            margin-bottom: 12px;
        }

        .empty-state p {
            font-size: 16px;
            color: #718096;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #718096;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 32px;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .results-header {
                flex-direction: column;
                gap: 16px;
                align-items: flex-start;
            }

            .newsletters-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📰 Our Newsletters</h1>
        <p>Stay updated with our latest news and insights</p>
    </div>

    <!-- Filters Section -->
    <div class="filters-section">
        <form id="filterForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search newsletters..."
                           value="<?= htmlspecialchars($currentFilters['search'] ?? '') ?>">
                </div>

                <div class="filter-group">
                    <label for="interval">Frequency</label>
                    <select id="interval" name="interval">
                        <option value="">All Frequencies</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($currentFilters['date_from'] ?? '') ?>"
                           min="<?= $filterOptions['date_range']['min'] ?>"
                           max="<?= $filterOptions['date_range']['max'] ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to"
                           value="<?= htmlspecialchars($currentFilters['date_to'] ?? '') ?>"
                           min="<?= $filterOptions['date_range']['min'] ?>"
                           max="<?= $filterOptions['date_range']['max'] ?>">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear All</button>
            </div>

            <?php if (!empty($filters_applied)): ?>
                <div class="applied-filters" id="appliedFilters">
                    <?php foreach ($filters_applied as $filter): ?>
                        <div class="filter-tag">
                            <strong><?= htmlspecialchars($filter['label']) ?>:</strong>
                            <?= htmlspecialchars($filter['value']) ?>
                            <button type="button" onclick="removeFilter('<?= htmlspecialchars($filter['type']) ?>')">×
                            </button>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- Results Header -->
    <div class="results-header">
        <div class="results-count" id="resultsCount">
            <?php
            $total = $pagination['total'] ?? $newsletters->count();
            echo $total . ' newsletter' . ($total !== 1 ? 's' : '') . ' found';
            ?>
        </div>
        <select class="sort-select" id="sortBy" onchange="updateSort()">
            <option value="last_sent-desc" <?= ($currentFilters['sort_by'] ?? 'last_sent') === 'last_sent' && ($currentFilters['sort_order'] ?? 'desc') === 'desc' ? 'selected' : '' ?>>
                Newest First
            </option>
            <option value="last_sent-asc" <?= ($currentFilters['sort_by'] ?? '') === 'last_sent' && ($currentFilters['sort_order'] ?? '') === 'asc' ? 'selected' : '' ?>>
                Oldest First
            </option>
            <option value="title-asc" <?= ($currentFilters['sort_by'] ?? '') === 'title' && ($currentFilters['sort_order'] ?? '') === 'asc' ? 'selected' : '' ?>>
                Title (A-Z)
            </option>
            <option value="title-desc" <?= ($currentFilters['sort_by'] ?? '') === 'title' && ($currentFilters['sort_order'] ?? '') === 'desc' ? 'selected' : '' ?>>
                Title (Z-A)
            </option>
        </select>
    </div>

    <!-- Newsletters Grid -->
    <div id="newslettersContainer">
        <?php if ($newsletters->count() > 0): ?>
            <div class="newsletters-grid" id="newslettersGrid">
                <?php foreach ($newsletters as $newsletter): ?>
                    <div class="newsletter-card"
                         onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>'">
                        <div class="newsletter-image">
                            📧
                        </div>
                        <div class="newsletter-content">
                            <div class="newsletter-date">
                                <?= $newsletter->last_sent?->format('F d, Y') ?? $newsletter->created_at?->format('F d, Y') ?>
                            </div>
                            <h2 class="newsletter-title">
                                <?= htmlspecialchars($newsletter->title ?? 'Untitled Newsletter') ?>
                            </h2>
                            <?php if (!empty($newsletter->content)): ?>
                                <p class="newsletter-excerpt">
                                    <?= htmlspecialchars(substr($newsletter->content, 0, 120)) ?>...
                                </p>
                            <?php endif; ?>
                            <div class="newsletter-footer">
                                <div style="display: flex; gap: 12px;">
                                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>"
                                       class="read-more">
                                        Read More →
                                    </a>
                                    <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->id ?>/download"
                                       style="color: #667eea; font-size: 14px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px;"
                                       onclick="event.stopPropagation()">
                                        📥 PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">🔭</div>
                <h2>No Newsletters Found</h2>
                <p>Try adjusting your filters or search terms</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if (isset($pagination) && $pagination['total_pages'] > 1): ?>
        <div class="pagination" id="pagination">
            <button onclick="goToPage(<?= $pagination['current_page'] - 1 ?>)"
                    <?= $pagination['current_page'] === 1 ? 'disabled' : '' ?>>
                ← Previous
            </button>

            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++):
                if ($i === 1 || $i === $pagination['total_pages'] ||
                        ($i >= $pagination['current_page'] - 2 && $i <= $pagination['current_page'] + 2)): ?>
                    <button class="<?= $i === $pagination['current_page'] ? 'active' : '' ?>"
                            onclick="goToPage(<?= $i ?>)">
                        <?= $i ?>
                    </button>
                <?php elseif ($i === $pagination['current_page'] - 3 || $i === $pagination['current_page'] + 3): ?>
                    <span>...</span>
                <?php endif;
            endfor; ?>

            <button onclick="goToPage(<?= $pagination['current_page'] + 1 ?>)"
                    <?= !$pagination['has_more'] ? 'disabled' : '' ?>>
                Next →
            </button>
        </div>
    <?php endif; ?>
</div>

<script>
    let currentPage = <?= $pagination['current_page'] ?? 1 ?>;

    async function loadNewsletters() {
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        params.append('page', currentPage);

        const [sortBy, sortOrder] = document.getElementById('sortBy').value.split('-');
        params.append('sort_by', sortBy);
        params.append('sort_order', sortOrder);

        const container = document.getElementById('newslettersContainer');
        container.innerHTML = '<div class="loading">Loading newsletters...</div>';

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/search?${params}`);
            const data = await response.json();

            if (data.success) {
                displayNewsletters(data.newsletters);
                displayPagination(data.pagination);
                displayAppliedFilters(data.filters_applied);
                updateResultsCount(data.pagination.total);
            }
        } catch (error) {
            console.error('Error loading newsletters:', error);
            container.innerHTML = '<div class="empty-state"><h2>Error loading newsletters</h2><p>Please try again</p></div>';
        }
    }

    function displayNewsletters(newsletters) {
        const container = document.getElementById('newslettersContainer');

        if (newsletters.length === 0) {
            container.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">🔭</div>
                    <h2>No Newsletters Found</h2>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            `;
            return;
        }

        const grid = newsletters.map(newsletter => `
            <div class="newsletter-card" onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/${newsletter.id}'">
                <div class="newsletter-image">📧</div>
                <div class="newsletter-content">
                    <div class="newsletter-date">${formatDate(newsletter.last_sent || newsletter.created_at)}</div>
                    <h2 class="newsletter-title">${escapeHtml(newsletter.title || 'Untitled Newsletter')}</h2>
                    ${newsletter.content ? `<p class="newsletter-excerpt">${escapeHtml(truncate(newsletter.content, 120))}...</p>` : ''}
                    <div class="newsletter-footer">
                        <div style="display: flex; gap: 12px;">
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/${newsletter.id}" class="read-more" onclick="event.stopPropagation()">
                                Read More →
                            </a>
                            <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/${newsletter.id}/download"
                               style="color: #667eea; font-size: 14px; font-weight: 600; text-decoration: none; display: flex; align-items: center; gap: 6px;"
                               onclick="event.stopPropagation()">
                                📥 PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');

        container.innerHTML = `<div class="newsletters-grid">${grid}</div>`;
    }

    function displayPagination(pagination) {
        const existing = document.getElementById('pagination');
        if (existing) {
            existing.remove();
        }

        if (pagination.total_pages <= 1) {
            return;
        }

        let html = `
            <button onclick="goToPage(${pagination.current_page - 1})" ${pagination.current_page === 1 ? 'disabled' : ''}>
                ← Previous
            </button>
        `;

        for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === 1 || i === pagination.total_pages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                html += `
                    <button class="${i === pagination.current_page ? 'active' : ''}" onclick="goToPage(${i})">
                        ${i}
                    </button>
                `;
            } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                html += '<span>...</span>';
            }
        }

        html += `
            <button onclick="goToPage(${pagination.current_page + 1})" ${!pagination.has_more ? 'disabled' : ''}>
                Next →
            </button>
        `;

        const paginationDiv = document.createElement('div');
        paginationDiv.className = 'pagination';
        paginationDiv.id = 'pagination';
        paginationDiv.innerHTML = html;
        document.querySelector('.container').appendChild(paginationDiv);
    }

    function displayAppliedFilters(filters) {
        const container = document.getElementById('appliedFilters');
        if (!container) return;

        if (filters.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = filters.map(filter => `
            <div class="filter-tag">
                <strong>${escapeHtml(filter.label)}:</strong> ${escapeHtml(filter.value)}
                <button onclick="removeFilter('${filter.type}')">×</button>
            </div>
        `).join('');
    }

    function updateResultsCount(total) {
        document.getElementById('resultsCount').textContent =
            `${total} newsletter${total !== 1 ? 's' : ''} found`;
    }

    function goToPage(page) {
        currentPage = page;
        loadNewsletters();
        window.scrollTo({top: 0, behavior: 'smooth'});
    }

    function updateSort() {
        loadNewsletters();
    }

    function clearFilters() {
        document.getElementById('filterForm').reset();
        currentPage = 1;
        loadNewsletters();
    }

    function removeFilter(type) {
        const form = document.getElementById('filterForm');
        if (type === 'date_range') {
            form.date_from.value = '';
            form.date_to.value = '';
        } else {
            form[type].value = '';
        }
        currentPage = 1;
        loadNewsletters();
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    }

    function truncate(str, length) {
        return str.length > length ? str.substring(0, length) : str;
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    document.getElementById('filterForm').addEventListener('submit', (e) => {
        e.preventDefault();
        currentPage = 1;
        loadNewsletters();
    });
</script>
</body>
</html>