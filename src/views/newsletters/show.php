<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Newsletter Archive - <?= $site->name ?></title>
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

        /* App Store Links */
        .app-links {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .app-links-content h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .app-links-content p {
            opacity: 0.9;
            font-size: 14px;
        }

        .app-links-buttons {
            display: flex;
            gap: 12px;
        }

        .app-link-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: white;
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: transform 0.2s;
        }

        .app-link-btn:hover {
            transform: translateY(-2px);
        }

        /* Featured Latest Newsletter */
        .featured-newsletter {
            background: white;
            border-radius: 16px;
            padding: 32px;
            margin-bottom: 40px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border: 3px solid var(--primary);
        }

        .featured-badge {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 16px;
        }

        .featured-newsletter h2 {
            font-size: 32px;
            margin-bottom: 12px;
            color: var(--text);
        }

        .featured-date {
            color: var(--text-light);
            font-size: 15px;
            margin-bottom: 16px;
        }

        .featured-excerpt {
            font-size: 16px;
            line-height: 1.7;
            color: var(--text);
            margin-bottom: 24px;
        }

        .featured-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
            text-decoration: none;
            display: inline-block;
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

        /* Filters Section */
        .filters-section {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
            box-shadow: var(--shadow);
        }

        .filters-toggle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            margin-bottom: 16px;
        }

        .filters-toggle h3 {
            font-size: 18px;
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

        /* Year Sections */
        .year-section {
            margin-bottom: 48px;
        }

        .year-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 24px;
            padding-bottom: 12px;
            border-bottom: 3px solid var(--primary);
        }

        .year-header h2 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .year-count {
            background: var(--primary);
            color: white;
            padding: 4px 12px;
            border-radius: 16px;
            font-size: 14px;
            font-weight: 600;
        }

        /* Newsletter Grid */
        .newsletters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
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
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
        }

        .newsletter-excerpt {
            font-size: 14px;
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
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
            .app-links {
                flex-direction: column;
                text-align: center;
            }

            .app-links-buttons {
                flex-direction: column;
                width: 100%;
            }

            .app-link-btn {
                justify-content: center;
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .newsletters-grid {
                grid-template-columns: 1fr;
            }

            .featured-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>📚 Newsletter Archive</h1>
        <p>Browse our complete collection of newsletters</p>
    </div>

    <?php if ($hasApp && $appLinks): ?>
        <div class="app-links">
            <div class="app-links-content">
                <h3>📱 Read on the Go</h3>
                <p>Download our app for easier access to all newsletters</p>
            </div>
            <div class="app-links-buttons">
                <?php if (isset($appLinks['ios'])): ?>
                    <a href="<?= $appLinks['ios'] ?>" class="app-link-btn" target="_blank">
                        <span>📱</span> App Store
                    </a>
                <?php endif; ?>
                <?php if (isset($appLinks['android'])): ?>
                    <a href="<?= $appLinks['android'] ?>" class="app-link-btn" target="_blank">
                        <span>🤖</span> Google Play
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($latestNewsletter): ?>
        <div class="featured-newsletter">
            <div class="featured-badge">Latest Edition</div>
            <h2><?= htmlspecialchars($latestNewsletter->newsletter->title) ?></h2>
            <div class="featured-date">
                Published <?= $latestNewsletter->sent_at?->format('F d, Y') ?? $latestNewsletter->newsletter?->last_sent->format('F d, Y') ?>
            </div>
            <?php if ($latestNewsletter->newsletter->content): ?>
                <div class="featured-excerpt">
                    <?= htmlspecialchars(substr(strip_tags($latestNewsletter->newsletter->content), 0, 200)) ?>...
                </div>
            <?php endif; ?>
            <div class="featured-actions">
                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $latestNewsletter->newsletter->id ?>/view"
                   class="btn btn-primary">
                    Read Full Newsletter →
                </a>
                <a href="/newsletters/<?= $latestNewsletter->newsletter->id ?>/pdf" class="btn btn-secondary">
                    Download PDF
                </a>
            </div>
        </div>
    <?php endif; ?>

    <div class="filters-section">
        <form id="filterForm">
            <div class="filters-toggle" onclick="toggleFilters()">
                <h3>🔍 Search & Filter</h3>
                <span id="filterToggleIcon">▼</span>
            </div>

            <div id="filtersContent">
                <div class="filters-grid">
                    <div class="filter-group">
                        <label for="search">Search</label>
                        <input type="text" id="search" name="search" placeholder="Search newsletters...">
                    </div>

                    <div class="filter-group">
                        <label for="year">Year</label>
                        <select id="year" name="year">
                            <option value="">All Years</option>
                            <?php foreach ($years as $year): ?>
                                <option value="<?= $year ?>"><?= $year ?></option>
                            <?php endforeach; ?>
                        </select>
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
                        <label for="sort_by">Sort By</label>
                        <select id="sort_by" name="sort_by">
                            <option value="last_sent-desc">Newest First</option>
                            <option value="last_sent-asc">Oldest First</option>
                            <option value="title-asc">Title (A-Z)</option>
                            <option value="title-desc">Title (Z-A)</option>
                        </select>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <button type="button" class="btn btn-secondary" onclick="clearFilters()">Clear All</button>
                </div>

                <div class="applied-filters" id="appliedFilters"></div>
            </div>
        </form>
    </div>

    <div id="archiveContent">
        <?php if (empty($newslettersByYear)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">📭</div>
                <h3>No Newsletters Yet</h3>
                <p>Check back soon for our newsletter archive</p>
            </div>
        <?php else: ?>
            <?php foreach ($newslettersByYear as $year => $newsletters): ?>
                <div class="year-section">
                    <div class="year-header">
                        <h2><?= $year ?></h2>
                        <div class="year-count"><?= count($newsletters) ?>
                            newsletter<?= count($newsletters) !== 1 ? 's' : '' ?></div>
                    </div>
                    <div class="newsletters-grid">
                        <?php foreach ($newsletters as $newsletter): ?>
                            <div class="newsletter-card"
                                 onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->newsletter->id ?>'">
                                <div class="newsletter-date"><?= $newsletter->sent_at->format('F d, Y') ?? $newsletter->newsletter->last_sent->format('F d, Y') ?></div>
                                <h3 class="newsletter-title"><?= htmlspecialchars($newsletter->newsletter->title) ?></h3>
                                <?php if ($newsletter->newsletter->content): ?>
                                    <p class="newsletter-excerpt">
                                        <?= htmlspecialchars(substr(strip_tags($newsletter->newsletter->content), 0, 100)) ?>
                                        ...
                                    </p>
                                <?php endif; ?>
                                <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/<?= $newsletter->newsletter->id ?>/view"
                                   class="newsletter-link" onclick="event.stopPropagation()">
                                    Read Newsletter →
                                </a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<script>
    let filtersExpanded = true;

    function toggleFilters() {
        filtersExpanded = !filtersExpanded;
        document.getElementById('filtersContent').style.display = filtersExpanded ? 'block' : 'none';
        document.getElementById('filterToggleIcon').textContent = filtersExpanded ? '▼' : '▶';
    }

    async function loadFilteredNewsletters() {
        const formData = new FormData(document.getElementById('filterForm'));
        const params = new URLSearchParams(formData);
        params.append('exclude_latest', 'true'); // Always exclude latest since it's featured

        // Handle sort_by field which contains both sortBy and sortOrder
        const sortValue = document.getElementById('sort_by').value;
        const [sortBy, sortOrder] = sortValue.split('-');
        params.delete('sort_by');
        params.append('sort_by', sortBy);
        params.append('sort_order', sortOrder);

        try {
            const response = await fetch(`/<?= \App\Framework\Support\SiteContext::slug() ?>/newsletters/archive/search?${params}`);
            const data = await response.json();

            if (data.success) {
                displayFilteredResults(data.newsletters);
                displayAppliedFilters(data.filters_applied);
            }
        } catch (error) {
            console.error('Error loading newsletters:', error);
        }
    }

    function displayFilteredResults(newsletters) {
        const content = document.getElementById('archiveContent');

        if (newsletters.length === 0) {
            content.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <h3>No Newsletters Found</h3>
                    <p>Try adjusting your filters or search terms</p>
                </div>
            `;
            return;
        }

        // Group by year
        const byYear = {};
        newsletters.forEach(newsletter => {
            const year = new Date(newsletter.last_sent).getFullYear();
            if (!byYear[year]) byYear[year] = [];
            byYear[year].push(newsletter);
        });

        // Sort years descending
        const years = Object.keys(byYear).sort((a, b) => b - a);

        content.innerHTML = years.map(year => `
            <div class="year-section">
                <div class="year-header">
                    <h2>${year}</h2>
                    <div class="year-count">${byYear[year].length} newsletter${byYear[year].length !== 1 ? 's' : ''}</div>
                </div>
                <div class="newsletters-grid">
                    ${byYear[year].map(newsletter => `
                        <div class="newsletter-card" onclick="window.location.href='/<?= \App\Framework\Support\SiteContext::slug()?>/newsletters/${newsletter.id}/view'">
                            <div class="newsletter-date">${formatDate(newsletter.last_sent)}</div>
                            <h3 class="newsletter-title">${escapeHtml(newsletter.title)}</h3>
                            ${newsletter.content ? `<p class="newsletter-excerpt">${truncate(escapeHtml(newsletter.content), 100)}</p>` : ''}
                            <a href="/<?= \App\Framework\Support\SiteContext::slug()?>/newsletters/${newsletter.id}/view" class="newsletter-link" onclick="event.stopPropagation()">
                                Read Newsletter →
                            </a>
                        </div>
                    `).join('')}
                </div>
            </div>
        `).join('');
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

    function clearFilters() {
        document.getElementById('filterForm').reset();
        location.reload();
    }

    function removeFilter(type) {
        const form = document.getElementById('filterForm');
        if (type === 'date_range') {
            form.date_from.value = '';
            form.date_to.value = '';
        } else {
            form[type].value = '';
        }
        loadFilteredNewsletters();
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
        loadFilteredNewsletters();
    });
</script>
</body>
</html>