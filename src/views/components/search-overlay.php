<style>
    .search-overlay {
        --search-accent: var(--primary-color, #2563eb);
        --search-accent-soft: color-mix(in srgb, var(--search-accent) 10%, white);
        --search-surface: #ffffff;
        --search-surface-muted: #f8fafc;
        --search-border: #e2e8f0;
        --search-text: #0f172a;
        --search-text-muted: #64748b;
    }

    .search-overlay .search-backdrop {
        background: rgba(15, 23, 42, 0.72);
        backdrop-filter: blur(8px);
    }

    .search-overlay .search-modal {
        width: min(1120px, calc(100% - 40px));
        max-width: 1120px;
        max-height: min(900px, calc(100vh - 48px));
        border: 1px solid rgba(255, 255, 255, 0.55);
        border-radius: 22px;
        background: var(--search-surface);
        box-shadow: 0 32px 90px rgba(15, 23, 42, 0.36);
    }

    .search-overlay .search-header {
        position: sticky;
        top: 0;
        z-index: 5;
        gap: 10px;
        padding: 18px 20px;
        background: rgba(255, 255, 255, 0.96);
        border-bottom: 1px solid var(--search-border);
        backdrop-filter: blur(14px);
    }

    .search-overlay .search-input-wrapper {
        min-height: 52px;
        padding: 0 16px;
        border: 1px solid var(--search-border);
        border-radius: 14px;
        background: var(--search-surface-muted);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.8);
    }

    .search-overlay .search-input-wrapper:focus-within {
        border-color: var(--search-accent);
        background: var(--search-surface);
        box-shadow: 0 0 0 4px color-mix(in srgb, var(--search-accent) 14%, transparent);
    }

    .search-overlay .search-icon {
        color: var(--search-text-muted);
    }

    .search-overlay .search-input {
        min-width: 0;
        font-size: 17px;
        font-weight: 500;
        color: var(--search-text);
    }

    .search-overlay .search-shortcut {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin-left: 10px;
        padding: 4px 8px;
        border: 1px solid var(--search-border);
        border-bottom-width: 2px;
        border-radius: 7px;
        background: #fff;
        color: var(--search-text-muted);
        font-size: 11px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .search-overlay .search-close-btn {
        width: 52px;
        height: 52px;
        border: 1px solid var(--search-border);
        border-radius: 14px;
        color: var(--search-text-muted);
    }

    .search-overlay .search-close-btn:hover {
        border-color: #cbd5e1;
        background: #f1f5f9;
        color: var(--search-text);
    }

    .search-overlay .search-filter-stack {
        background: var(--search-surface-muted);
        border-bottom: 1px solid var(--search-border);
    }

    .search-overlay .search-filter-row {
        display: grid;
        grid-template-columns: 96px minmax(0, 1fr);
        align-items: center;
        gap: 14px;
        padding: 10px 20px;
    }

    .search-overlay .search-filter-row + .search-filter-row {
        border-top: 1px solid rgba(226, 232, 240, 0.75);
    }

    .search-overlay .search-filter-label {
        display: flex;
        align-items: center;
        gap: 7px;
        color: #475569;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .search-overlay .search-categories,
    .search-overlay .search-filters {
        min-width: 0;
        min-height: auto;
        margin: 0;
        padding: 2px 0 7px;
        border: 0;
        gap: 8px;
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 transparent;
    }

    .search-overlay .search-categories::-webkit-scrollbar,
    .search-overlay .search-filters::-webkit-scrollbar {
        display: block;
        height: 5px;
    }

    .search-overlay .search-categories::-webkit-scrollbar-track,
    .search-overlay .search-filters::-webkit-scrollbar-track {
        background: transparent;
    }

    .search-overlay .search-categories::-webkit-scrollbar-thumb,
    .search-overlay .search-filters::-webkit-scrollbar-thumb {
        border-radius: 999px;
        background: #cbd5e1;
    }

    .search-overlay .category-pill,
    .search-overlay .filter-pill {
        min-height: 34px;
        padding: 7px 14px;
        border: 1px solid var(--search-border);
        border-radius: 999px;
        background: #fff;
        color: #475569;
        font-size: 13px;
        font-weight: 600;
        line-height: 1;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .search-overlay .category-pill:hover,
    .search-overlay .filter-pill:hover {
        border-color: var(--search-accent);
        background: var(--search-accent-soft);
        color: var(--search-accent);
    }

    .search-overlay .category-pill.active,
    .search-overlay .filter-pill.active {
        border-color: var(--search-accent);
        background: var(--search-accent);
        color: #fff;
        box-shadow: 0 6px 16px color-mix(in srgb, var(--search-accent) 24%, transparent);
    }

    .search-overlay .search-tabs {
        position: relative;
        z-index: 3;
        gap: 6px;
        padding: 10px 20px 0;
        background: #fff;
        border-bottom: 1px solid var(--search-border);
    }

    .search-overlay .search-tab {
        min-height: 52px;
        padding: 12px 18px;
        border-radius: 12px 12px 0 0;
        border-bottom-width: 2px;
        color: var(--search-text-muted);
    }

    .search-overlay .search-tab:hover {
        background: #f8fafc;
        color: var(--search-text);
    }

    .search-overlay .search-tab.active {
        color: var(--search-accent);
        border-bottom-color: var(--search-accent);
        background: var(--search-accent-soft);
    }

    .search-overlay .tab-count {
        min-width: 26px;
        padding: 3px 8px;
        text-align: center;
    }

    .search-overlay .search-content {
        padding: 22px 24px 28px;
        background: #fff;
        scrollbar-gutter: stable;
    }

    .search-overlay .search-results-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 16px;
    }

    .search-overlay .results-count {
        font-size: 14px;
        color: var(--search-text-muted);
    }

    .search-overlay .results-count strong {
        color: var(--search-text);
    }

    .search-overlay .search-results-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .search-overlay .search-result-card {
        border-color: var(--search-border);
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }

    .search-overlay .search-result-card:hover {
        border-color: color-mix(in srgb, var(--search-accent) 35%, var(--search-border));
        box-shadow: 0 18px 38px rgba(15, 23, 42, 0.12);
        transform: translateY(-3px);
    }

    .search-overlay .search-empty-state,
    .search-overlay .search-loading,
    .search-overlay .search-no-results {
        min-height: 340px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }

    .search-overlay .search-empty-state svg,
    .search-overlay .search-no-results svg {
        padding: 16px;
        border-radius: 20px;
        background: var(--search-surface-muted);
        color: #94a3b8;
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

        .search-overlay .search-input-wrapper {
            min-height: 48px;
            padding: 0 13px;
        }

        .search-overlay .search-close-btn {
            width: 48px;
            height: 48px;
        }

        .search-overlay .search-shortcut {
            display: none;
        }

        .search-overlay .search-filter-row {
            grid-template-columns: 1fr;
            gap: 7px;
            padding: 10px 14px;
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

    @media (prefers-reduced-motion: reduce) {
        .search-overlay .search-modal,
        .search-overlay .search-result-card,
        .search-overlay .category-pill,
        .search-overlay .filter-pill {
            animation: none;
            transition: none;
        }
    }
</style>

<div class="search-overlay" id="searchOverlay" aria-hidden="true">
    <div class="search-backdrop" onclick="toggleSearch()" aria-hidden="true"></div>

    <div class="search-modal" role="dialog" aria-modal="true" aria-label="Search site content">
        <div class="search-header">
            <label class="search-input-wrapper" for="searchInput">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input
                        type="search"
                        id="searchInput"
                        placeholder="Search articles, guides, reviews..."
                        class="search-input"
                        autocomplete="off"
                        aria-label="Search articles, guides, reviews, and products"
                >
                <span class="search-shortcut" aria-hidden="true">Ctrl K</span>
                <button type="button" class="search-clear-btn" id="searchClearBtn" style="display: none;" onclick="clearSearch()" aria-label="Clear search">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </label>
            <button type="button" class="search-close-btn" onclick="toggleSearch()" aria-label="Close search">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="search-filter-stack">
            <div class="search-filter-row" id="searchCategoryRow" style="display: none;">
                <div class="search-filter-label">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M4 4h6v6H4zM14 4h6v6h-6zM4 14h6v6H4zM14 14h6v6h-6z"></path>
                    </svg>
                    Topics
                </div>
                <div class="search-categories" id="searchCategories" style="display: none;">
                    <button type="button" class="category-pill active" data-category="" onclick="filterByCategory('')">All</button>
                </div>
            </div>

            <div class="search-filter-row" id="searchAuthorRow" style="display: none;">
                <div class="search-filter-label">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20 21a8 8 0 0 0-16 0"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    Authors
                </div>
                <div class="search-filters" id="searchAuthors" style="display: none;"></div>
            </div>

            <div class="search-filter-row" id="searchTagRow" style="display: none;">
                <div class="search-filter-label">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                        <path d="M20.59 13.41 11 3.83V3H4v7h.83l9.58 9.59a2 2 0 0 0 2.82 0l3.36-3.36a2 2 0 0 0 0-2.82Z"></path>
                        <circle cx="7.5" cy="6.5" r=".5" fill="currentColor"></circle>
                    </svg>
                    Tags
                </div>
                <div class="search-filters" id="searchTags" style="display: none;"></div>
            </div>
        </div>

        <div class="search-tabs" role="tablist" aria-label="Search result types">
            <button type="button" class="search-tab active" data-tab="explore" onclick="switchTab('explore')" role="tab" aria-selected="true" aria-controls="exploreContent">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
                Explore
                <span class="tab-count" id="exploreCount">0</span>
            </button>
            <button type="button" class="search-tab" data-tab="shop" onclick="switchTab('shop')" role="tab" aria-selected="false" aria-controls="shopContent">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Shop
                <span class="tab-count" id="shopCount">0</span>
            </button>
        </div>

        <div class="search-content" aria-live="polite">
            <div class="search-empty-state" id="searchEmptyState">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>Search everything</h3>
                <p>Find articles, guides, reviews, and products in one place.</p>
            </div>

            <div class="search-loading" id="searchLoading" style="display: none;" role="status">
                <div class="loading-spinner" aria-hidden="true"></div>
                <p>Searching...</p>
            </div>

            <div class="search-results-header" id="searchResultsHeader" style="display: none;">
                <p class="results-count">Found <strong id="totalResults">0</strong> results</p>
            </div>

            <div class="search-tab-content active" id="exploreContent" role="tabpanel">
                <div class="search-results-grid" id="searchResultsGrid"></div>
            </div>

            <div class="search-tab-content" id="shopContent" role="tabpanel" hidden>
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