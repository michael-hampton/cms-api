<div class="search-overlay" id="searchOverlay">
    <div class="search-backdrop" onclick="toggleSearch()"></div>

    <div class="search-modal">
        <!-- Search Header -->
        <div class="search-header">
            <div class="search-input-wrapper">
                <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <input
                        type="search"
                        id="searchInput"
                        placeholder="Search articles, guides, reviews..."
                        class="search-input"
                        autocomplete="off"
                >
                <button class="search-clear-btn" id="searchClearBtn" style="display: none;" onclick="clearSearch()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <button class="search-close-btn" onclick="toggleSearch()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <!-- Category Pills -->
        <div class="search-categories" id="searchCategories" style="display: none;">
            <button class="category-pill active" data-category="" onclick="filterByCategory('')">
                All
            </button>
            <!-- Dynamic categories will be inserted here -->
        </div>

        <!-- NEW: Author Pills -->
        <div class="search-filters" id="searchAuthors" style="display: none;">
            <!-- Dynamic authors will be inserted here -->
        </div>

        <!-- NEW: Tag Pills -->
        <div class="search-filters" id="searchTags" style="display: none;">
            <!-- Dynamic tags will be inserted here -->
        </div>

        <!-- Search Tabs -->
        <div class="search-tabs">
            <button class="search-tab active" data-tab="explore" onclick="switchTab('explore')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                    <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                </svg>
                Explore
                <span class="tab-count" id="exploreCount">0</span>
            </button>
            <button class="search-tab" data-tab="shop" onclick="switchTab('shop')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"></circle>
                    <circle cx="20" cy="21" r="1"></circle>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                </svg>
                Shop
                <span class="tab-count" id="shopCount">0</span>
            </button>
        </div>

        <!-- Search Results -->
        <div class="search-content">
            <!-- Empty State -->
            <div class="search-empty-state" id="searchEmptyState">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="11" cy="11" r="8"></circle>
                    <path d="m21 21-4.35-4.35"></path>
                </svg>
                <h3>Start searching</h3>
                <p>Type to find articles, guides, and products</p>
            </div>

            <!-- Loading State -->
            <div class="search-loading" id="searchLoading" style="display: none;">
                <div class="loading-spinner"></div>
                <p>Searching...</p>
            </div>

            <!-- Results Total -->
            <div class="search-results-header" id="searchResultsHeader" style="display: none;">
                <p class="results-count">
                    Found <strong id="totalResults">0</strong> results
                </p>
            </div>

            <!-- Explore Tab Content -->
            <div class="search-tab-content active" id="exploreContent">
                <div class="search-results-grid" id="searchResultsGrid">
                    <!-- Results will be dynamically inserted here -->
                </div>
            </div>

            <!-- Shop Tab Content -->
            <div class="search-tab-content" id="shopContent">
                <div class="search-results-grid" id="shopResultsGrid">
                    <!-- Product/deal results will be dynamically inserted here -->
                </div>
            </div>

            <!-- Load More Button -->
            <div class="search-load-more" id="searchLoadMore" style="display: none;">
                <button class="btn-load-more" onclick="loadMoreResults()">
                    Load More Results
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </div>

            <!-- No Results State -->
            <div class="search-no-results" id="searchNoResults" style="display: none;">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <h3>No results found</h3>
                <p>Try adjusting your search or filters</p>
            </div>
        </div>
    </div>
</div>