<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Archive - Site Name</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #667eea;
            --primary-dark: #5568d3;
            --bg-light: #f8f9fa;
            --text: #2c3e50;
            --text-light: #718096;
            --border: #e2e8f0;
            --shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-light);
            color: var(--text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            margin-bottom: 40px;
        }

        .header h1 {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-light);
            font-size: 16px;
        }

        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
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
            color: var(--text);
        }

        .filter-group input,
        .filter-group select {
            padding: 10px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary);
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
            background: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: var(--border);
            color: var(--text);
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
            background: var(--bg-light);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            color: var(--text);
        }

        .filter-tag button {
            background: none;
            border: none;
            color: var(--text-light);
            cursor: pointer;
            font-size: 16px;
            padding: 0;
            line-height: 1;
        }

        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .results-count {
            font-size: 14px;
            color: var(--text-light);
        }

        .sort-select {
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: white;
        }

        .newsletters-grid {
            display: grid;
            gap: 20px;
        }

        .newsletter-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .newsletter-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        }

        .newsletter-date {
            font-size: 13px;
            color: var(--primary);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .newsletter-title {
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }

        .newsletter-excerpt {
            font-size: 15px;
            color: var(--text-light);
            margin-bottom: 16px;
            line-height: 1.6;
        }

        .newsletter-link {
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin-top: 40px;
        }

        .pagination button {
            padding: 8px 16px;
            border: 2px solid var(--border);
            background: white;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .pagination button:hover:not(:disabled) {
            border-color: var(--primary);
            color: var(--primary);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination button.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            font-size: 64px;
            margin-bottom: 16px;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--text-light);
        }

        @media (max-width: 768px) {
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
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📚 Newsletter Archive</h1>
        <p>Browse and search our complete newsletter history</p>
    </div>

    <div class="filters-section">
        <form id="filterForm">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search newsletters...">
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
                    <input type="date" id="date_from" name="date_from">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" id="date_to" name="date_to">
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear All</button>
            </div>

            <div class="applied-filters" id="appliedFilters"></div>
        </form>
    </div>

    <div class="results-header">
        <div class="results-count" id="resultsCount">Loading...</div>
        <select class="sort-select" id="sortBy" onchange="updateSort()">
            <option value="last_sent-desc">Newest First</option>
            <option value="last_sent-asc">Oldest First</option>
            <option value="title-asc">Title (A-Z)</option>
            <option value="title-desc">Title (Z-A)</option>
        </select>
    </div>

    <div class="newsletters-grid" id="newslettersGrid">
        <!-- Newsletters will be loaded here -->
    </div>

    <div class="pagination" id="pagination">
        <!-- Pagination will be loaded here -->
    </div>
</div>

<script>
    let currentPage = 1;
    let currentFilters = {};

    async function loadNewsletters() {
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        params.append('page', currentPage);

        const [sortBy, sortOrder] = document.getElementById('sortBy').value.split('-');
        params.append('sort_by', sortBy);
        params.append('sort_order', sortOrder);

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/archive/search?${params}`);
            const data = await response.json();

            displayNewsletters(data.newsletters);
            displayPagination(data.pagination);
            displayAppliedFilters(data.filters_applied);
            updateResultsCount(data.pagination.total);
        } catch (error) {
            console.error('Error loading newsletters:', error);
        }
    }

    function displayNewsletters(newsletters) {
        const grid = document.getElementById('newslettersGrid');

        if (newsletters.length === 0) {
            grid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📭</div>
                        <h3>No Newsletters Found</h3>
                        <p>Try adjusting your filters or search terms</p>
                    </div>
                `;
            return;
        }

        grid.innerHTML = newsletters.map(newsletter => `
                <div class="newsletter-card" onclick="window.location.href='/newsletters/${newsletter.id}'">
                    <div class="newsletter-date">${formatDate(newsletter.last_sent)}</div>
                    <h3 class="newsletter-title">${escapeHtml(newsletter.title)}</h3>
                    ${newsletter.content ? `<p class="newsletter-excerpt">${truncate(escapeHtml(newsletter.content), 150)}</p>` : ''}
                    <a href="/newsletters/${newsletter.id}" class="newsletter-link" onclick="event.stopPropagation()">
                        Read Newsletter →
                    </a>
                </div>
            `).join('');
    }

    function displayPagination(pagination) {
        const container = document.getElementById('pagination');

        if (pagination.total_pages <= 1) {
            container.innerHTML = '';
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

        container.innerHTML = html;
    }

    function displayAppliedFilters(filters) {
        const container = document.getElementById('appliedFilters');

        if (filters.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = filters.map(filter => `
                <div class="filter-tag">
                    <strong>${filter.label}:</strong> ${escapeHtml(filter.value)}
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
        return str.length > length ? str.substring(0, length) + '...' : str;
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

    loadNewsletters();
</script>
</body>
</html>