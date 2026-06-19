<style>
    .search-overlay {
        --search-accent: var(--primary-color, #2563eb);
        --search-border: #e2e8f0;
        --search-muted: #64748b;
        --search-text: #0f172a;
        --search-soft: color-mix(in srgb, var(--search-accent) 8%, white);
    }

    .search-overlay .search-backdrop {
        background: rgba(15, 23, 42, .72);
        backdrop-filter: blur(8px);
    }

    .search-overlay .search-modal {
        width: min(1120px, calc(100% - 40px));
        max-width: 1120px;
        max-height: min(900px, calc(100vh - 48px));
        border: 1px solid rgba(255, 255, 255, .55);
        border-radius: 22px;
        box-shadow: 0 32px 90px rgba(15, 23, 42, .36);
    }

    .search-overlay .search-header {
        gap: 10px;
        padding: 18px 20px;
        background: rgba(255, 255, 255, .96);
        border-bottom-color: var(--search-border);
        backdrop-filter: blur(14px);
    }

    .search-overlay .search-input-wrapper {
        min-height: 52px;
        padding: 0 16px;
        border: 1px solid var(--search-border);
        border-radius: 14px;
        background: #f8fafc;
    }

    .search-overlay .search-input-wrapper:focus-within {
        border-color: var(--search-accent);
        background: #fff;
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--search-accent) 14%, transparent);
    }

    .search-overlay .search-input {
        min-width: 0;
        font-size: 17px;
        font-weight: 500;
        color: var(--search-text);
    }

    .search-overlay .search-close-btn {
        width: 52px;
        height: 52px;
        border: 1px solid var(--search-border);
        border-radius: 14px;
    }

    .search-overlay .search-categories,
    .search-overlay .search-filters {
        position: relative;
        flex-wrap: wrap;
        align-items: center;
        gap: 8px;
        width: 100%;
        min-height: 58px;
        max-height: 132px;
        padding: 12px 20px 12px 118px;
        overflow-x: hidden;
        overflow-y: auto;
        box-sizing: border-box;
        background: #f8fafc;
        border-bottom: 1px solid var(--search-border);
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .search-overlay .search-categories::before,
    .search-overlay .search-filters::before {
        position: absolute;
        top: 18px;
        left: 20px;
        display: inline-flex;
        align-items: center;
        color: #475569;
        font-size: 11px;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .search-overlay #searchCategories::before {
        content: 'Topics';
    }

    .search-overlay #searchAuthors::before {
        content: 'Authors';
    }

    .search-overlay #searchTags::before {
        content: 'Tags';
    }

    .search-overlay .search-categories::-webkit-scrollbar,
    .search-overlay .search-filters::-webkit-scrollbar {
        display: block;
        width: 6px;
        height: 6px;
    }

    .search-overlay .search-categories::-webkit-scrollbar-track,
    .search-overlay .search-filters::-webkit-scrollbar-track {
        background: transparent;
    }

    .search-overlay .search-categories::-webkit-scrollbar-thumb,
    .search-overlay .search-filters::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 999px;
    }

    .search-overlay .category-pill,
    .search-overlay .filter-pill {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        flex: 0 0 auto;
        width: auto;
        height: auto !important;
        min-height: 34px;
        margin: 0;
        padding: 8px 14px;
        border: 1px solid var(--search-border);
        border-radius: 999px;
        background: #fff;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
        line-height: 1.15;
        white-space: nowrap;
        box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
    }

    .search-overlay .category-pill:hover,
    .search-overlay .filter-pill:hover {
        border-color: var(--search-accent);
        color: var(--search-accent);
        background: var(--search-soft);
    }

    .search-overlay .category-pill.active,
    .search-overlay .filter-pill.active {
        border-color: var(--search-accent);
        background: var(--search-accent);
        color: #fff;
        box-shadow: 0 6px 16px color-mix(in srgb, var(--search-accent) 24%, transparent);
    }

    .search-overlay .search-tabs {
        gap: 6px;
        padding: 10px 20px 0;
        background: #fff;
        border-bottom-color: var(--search-border);
    }

    .search-overlay .search-tab {
        min-height: 52px;
        padding: 12px 18px;
        border-radius: 12px 12px 0 0;
        border-bottom-width: 2px;
    }

    .search-overlay .search-tab.active {
        color: var(--search-accent);
        border-bottom-color: var(--search-accent);
        background: var(--search-soft);
    }

    .search-overlay .search-content {
        padding: 22px 24px 28px;
        scrollbar-gutter: stable;
    }

    .search-overlay .search-results-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .search-overlay .search-result-card {
        border-color: var(--search-border);
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, .05);
    }

    .search-overlay .search-result-card:hover {
        border-color: color-mix(in srgb, var(--search-accent) 35%, var(--search-border));
        box-shadow: 0 18px 38px rgba(15, 23, 42, .12);
        transform: translateY(-3px);
    }

    .search-overlay button:focus-visible,
    .search-overlay input:focus-visible {
        outline: 3px solid color-mix(in srgb, var(--search-accent) 24%, transparent);
        outline-offset: 2px;
    }

    @media (max-width: 768px) {
        .search-overlay .search-modal {
            width: 100%;
            max-height: 94vh;
            border-radius: 22px 22px 0 0;
        }

        .search-overlay .search-header {
            padding: 12px;
        }

        .search-overlay .search-input-wrapper,
        .search-overlay .search-close-btn {
            min-height: 48px;
            height: 48px;
        }

        .search-overlay .search-categories,
        .search-overlay .search-filters {
            min-height: 0;
            max-height: 150px;
            padding: 38px 14px 12px;
        }

        .search-overlay .search-categories::before,
        .search-overlay .search-filters::before {
            top: 12px;
            left: 14px;
        }

        .search-overlay .search-tabs {
            padding: 8px 12px 0;
        }

        .search-overlay .search-content {
            padding: 16px 14px 24px;
        }

        .search-overlay .search-results-grid {
            grid-template-columns: 1fr;
            gap: 14px;
        }
    }
</style>

<div class="search-overlay" id="searchOverlay">
    <div class="search-backdrop" onclick="toggleSearch()"></div>

    <div class="search-modal" role="dialog" aria-modal="true" aria-label="Search site content">
        <div class="search-header">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input type="search" id="searchInput" placeholder="Search articles, guides, reviews..." class="search-input" autocomplete="off" aria-label="Search articles, guides, reviews, and products">
                <button type="button" class="search-clear-btn" id="searchClearBtn" style="display: none;" onclick="clearSearch()" aria-label="Clear search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <button type="button" class="search-close-btn" onclick="toggleSearch()" aria-label="Close search">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="search-categories" id="searchCategories" style="display: none;">
            <button type="button" class="category-pill active" data-category="" onclick="filterByCategory('')">All</button>
        </div>

        <div class="search-filters" id="searchAuthors" style="display: none;"></div>
        <div class="search-filters" id="searchTags" style="display: none;"></div>

        <div class="search-tabs">
            <button type="button" class="search-tab active" data-tab="explore" onclick="switchTab('explore')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
                Explore
                <span class="tab-count" id="exploreCount">0</span>
            </button>
            <button type="button" class="search-tab" data-tab="shop" onclick="switchTab('shop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Shop
                <span class="tab-count" id="shopCount">0</span>
            </button>
        </div>

        <div class="search-content">
            <div class="search-empty-state" id="searchEmptyState">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>Search everything</h3>
                <p>Find articles, guides, reviews, and products in one place.</p>
            </div>

            <div class="search-loading" id="searchLoading" style="display: none;">
                <div class="loading-spinner"></div>
                <p>Searching...</p>
            </div>

            <div class="search-results-header" id="searchResultsHeader" style="display: none;">
                <p class="results-count">Found <strong id="totalResults">0</strong> results</p>
            </div>

            <div class="search-tab-content active" id="exploreContent">
                <div class="search-results-grid" id="searchResultsGrid"></div>
            </div>

            <div class="search-tab-content" id="shopContent">
                <div class="search-results-grid" id="shopResultsGrid"></div>
            </div>

            <div class="search-load-more" id="searchLoadMore" style="display: none;">
                <button type="button" class="btn-load-more" onclick="loadMoreResults()">
                    Load more results
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>

            <div class="search-no-results" id="searchNoResults" style="display: none;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>No results found</h3>
                <p>Try a broader search or remove one of the filters.</p>
            </div>
        </div>
    </div>
</div>