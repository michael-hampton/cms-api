<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Shop - YourStore</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #2563eb;
            --primary-dark: #1e40af;
            --success-color: #10b981;
            --border-color: #e2e8f0;
            --bg-light: #f8fafc;
            --text-primary: #1e293b;
            --text-secondary: #64748b;
            --shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--text-primary);
            background-color: var(--bg-light);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .header {
            background: white;
            box-shadow: var(--shadow);
            padding: 1rem 0;
            margin-bottom: 2rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .hero {
            text-align: center;
            padding: 3rem 0;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            margin-bottom: 2rem;
            border-radius: 1rem;
        }

        .hero h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .hero p {
            font-size: 1.125rem;
            opacity: 0.9;
        }

        /* Category Carousel */
        .category-carousel-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .category-carousel {
            display: flex;
            gap: 1rem;
            overflow-x: auto;
            padding: 1rem 0;
            scroll-behavior: smooth;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: thin;
            scrollbar-color: var(--primary-color) var(--bg-light);
        }

        .category-carousel::-webkit-scrollbar {
            height: 8px;
        }

        .category-carousel::-webkit-scrollbar-track {
            background: var(--bg-light);
            border-radius: 4px;
        }

        .category-carousel::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }

        .category-tile {
            flex: 0 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.75rem;
            padding: 1.25rem 1.5rem;
            background: white;
            border-radius: 1rem;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid var(--border-color);
            min-width: 120px;
        }

        .category-tile:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-color);
        }

        .category-tile.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(37, 99, 235, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%);
            box-shadow: var(--shadow);
        }

        .category-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: var(--shadow);
        }

        .category-name {
            font-weight: 600;
            color: var(--text-primary);
            font-size: 0.95rem;
            text-align: center;
            white-space: nowrap;
        }

        .category-tile.active .category-name {
            color: var(--primary-color);
        }

        .carousel-nav {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 2px solid var(--border-color);
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .carousel-nav:hover {
            border-color: var(--primary-color);
            background: var(--primary-color);
        }

        .carousel-nav svg {
            width: 20px;
            height: 20px;
            stroke: var(--text-secondary);
            transition: all 0.3s;
        }

        .carousel-nav:hover svg {
            stroke: white;
        }

        .carousel-nav:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }

        .carousel-nav:disabled:hover {
            border-color: var(--border-color);
            background: white;
        }

        .carousel-nav:disabled:hover svg {
            stroke: var(--text-secondary);
        }

        /* Filters Section */
        .filters-section {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group input,
        .filter-group select {
            padding: 0.75rem;
            border: 2px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: var(--primary-dark);
        }

        .btn-secondary {
            background: white;
            color: var(--text-secondary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            border-color: var(--text-secondary);
        }

        /* Results Header */
        .results-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .results-count {
            font-size: 1.125rem;
            color: var(--text-secondary);
        }

        .results-count strong {
            color: var(--text-primary);
        }

        /* Plans Grid */
        .plans-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .plan-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
        }

        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .plan-image {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            font-weight: 700;
        }

        .plan-content {
            padding: 1.5rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .plan-header {
            margin-bottom: 1rem;
        }

        .plan-name {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .plan-site {
            font-size: 0.875rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-description {
            color: var(--text-secondary);
            font-size: 0.95rem;
            margin-bottom: 1.5rem;
            flex: 1;
            line-height: 1.6;
        }

        .plan-features {
            margin-bottom: 1.5rem;
        }

        .feature-tag {
            display: inline-block;
            background: var(--bg-light);
            color: var(--text-secondary);
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.8rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .feature-tag.digital {
            background: #dbeafe;
            color: #1e40af;
        }

        .feature-tag.print {
            background: #fce7f3;
            color: #9f1239;
        }

        .feature-tag.tag-badge {
            background: #e0e7ff;
            color: #3730a3;
        }

        .plan-pricing {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1rem;
            border-top: 2px solid var(--border-color);
            margin-bottom: 1rem;
        }

        .price-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
        }

        .price-amount {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary-color);
        }

        .price-from {
            font-size: 0.875rem;
            color: var(--text-secondary);
            margin-right: 0.25rem;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin: 3rem 0;
        }

        .pagination button {
            padding: 0.75rem 1rem;
            border: 2px solid var(--border-color);
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .pagination button:hover:not(:disabled) {
            border-color: var(--primary-color);
            color: var(--primary-color);
        }

        .pagination button.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-info {
            color: var(--text-secondary);
            font-size: 0.95rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .empty-state svg {
            width: 80px;
            height: 80px;
            margin-bottom: 1.5rem;
            stroke: var(--text-secondary);
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
        }

        .empty-state p {
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        /* Loading State */
        .loading {
            text-align: center;
            padding: 3rem;
        }

        .spinner {
            border: 4px solid var(--border-color);
            border-top: 4px solid var(--primary-color);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1rem;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Featured Badge */
        .featured-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #fbbf24;
            color: #78350f;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .sale-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #ef4444;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .offer-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #8b5cf6;
            color: white;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 10;
        }

        .plan-categories {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .category-badge {
            display: inline-block;
            background: var(--bg-light);
            color: var(--text-secondary);
            padding: 0.25rem 0.625rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .plan-card {
            position: relative;
        }

        /* Price Range Inputs */
        .price-range-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
        }

        .price-input {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .price-input span {
            font-weight: 600;
            color: var(--text-secondary);
        }

        .price-input input {
            flex: 1;
        }

        /* Tags Filter */
        .tags-filter {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .tag-checkbox {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border: 2px solid var(--border-color);
            border-radius: 2rem;
            cursor: pointer;
            transition: all 0.3s;
            user-select: none;
        }

        .tag-checkbox:hover {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.05);
        }

        .tag-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }

        .tag-checkbox input[type="checkbox"]:checked + .tag-label {
            color: var(--primary-color);
            font-weight: 600;
        }

        .tag-label {
            font-size: 0.875rem;
            color: var(--text-secondary);
            transition: all 0.3s;
        }

        .tag-checkbox:has(input:checked) {
            border-color: var(--primary-color);
            background: rgba(37, 99, 235, 0.1);
        }

        /* Active Filters Display */
        .active-filters {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .filter-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
        }

        .filter-chip button {
            background: none;
            border: none;
            color: white;
            cursor: pointer;
            font-size: 1.125rem;
            padding: 0;
            line-height: 1;
        }

        @media (max-width: 768px) {
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .plans-grid {
                grid-template-columns: 1fr;
            }

            .hero h1 {
                font-size: 2rem;
            }

            .filter-actions {
                flex-direction: column;
            }

            .filter-actions .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
<div class="header">
    <div class="container">
        <div class="header-content">
            <h2>Subscription Shop</h2>
        </div>
    </div>
</div>

<div class="container">
    <div class="hero">
        <h1>Find Your Perfect Subscription</h1>
        <p>Browse subscriptions across all our publications</p>
    </div>

    <!-- Category Carousel -->
    <?php
    $activeCategory = $filters['category'] ?? 'All';
    ?>

    <?php if (!empty($available_categories)): ?>
        <div style="margin-top: 1.5rem; margin-bottom: 1.5rem;">
            <label style="font-weight: 600; font-size: 0.875rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 1rem;">
                Browse by Category
            </label>

            <div class="category-carousel-wrapper">
                <button class="carousel-nav carousel-prev" onclick="scrollCarousel('left')"
                        aria-label="Previous categories">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </button>

                <div class="category-carousel" id="category-carousel">
                    <?php
                    $selectedCategories = !empty($filters['categories']) ? (is_array($filters['categories']) ? $filters['categories'] : explode(',', $filters['categories'])) : [];

                    foreach ($available_categories as $category):
                        $isSelected = in_array($category['name'], $selectedCategories);
                        $iconKey = strtolower($category['name']);
                        $icon = $category['icon'] ?? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect></svg>';
                        ?>
                        <div class="category-tile <?= $isSelected ? 'selected' : '' ?>"
                             data-category="<?= htmlspecialchars($category['name']) ?>"
                             onclick="toggleCategory('<?= htmlspecialchars($category['name']) ?>')">
                            <div class="category-icon">
                                <?= $icon ?>
                            </div>
                            <div class="category-name">
                                <?= htmlspecialchars(ucfirst($category['name'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <button class="carousel-nav carousel-next" onclick="scrollCarousel('right')"
                        aria-label="Next categories">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </button>
            </div>

            <!-- Hidden inputs for selected categories -->
            <div id="category-inputs"></div>
        </div>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="filters-section">
        <form id="filter-form">
            <input type="hidden" id="category" name="category"
                   value="<?= htmlspecialchars($filters['category'] ?? '') ?>">

            <div class="filters-grid">
                <!-- Search -->
                <div class="filter-group">
                    <label for="search">Search</label>
                    <input type="text" id="search" name="search" placeholder="Search subscriptions..."
                           value="<?= htmlspecialchars($filters['search'] ?? '') ?>">
                </div>

                <!-- Site Filter -->
                <div class="filter-group">
                    <label for="site_id">Publication</label>
                    <select id="site_id" name="site_id">
                        <option value="">All Publications</option>
                        <?php foreach ($available_sites as $site): ?>
                            <option value="<?= $site->id ?>"
                                    <?= ($filters['site_id'] ?? '') == $site->id ? 'selected' : '' ?>>
                                <?= htmlspecialchars($site->name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Category Filter -->
                <?php if (!empty($available_categories)): ?>
                    <div class="filter-group">
                        <label for="category">Category</label>
                        <select id="category" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($available_categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['name']) ?>"
                                        <?= ($filters['category']['name'] ?? '') === $category ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($category['name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <!-- Delivery Type -->
                <div class="filter-group">
                    <label for="delivery_type">Delivery Type</label>
                    <select id="delivery_type" name="delivery_type">
                        <option value="">All Types</option>
                        <option value="digital" <?= ($filters['delivery_type'] ?? '') === 'digital' ? 'selected' : '' ?>>
                            Digital Only
                        </option>
                        <option value="print" <?= ($filters['delivery_type'] ?? '') === 'print' ? 'selected' : '' ?>>
                            Print Only
                        </option>
                    </select>
                </div>

                <!-- Sales & Offers Filter -->
                <div class="filter-group">
                    <label for="special_filter">Special Offers</label>
                    <select id="special_filter" name="special_filter">
                        <option value="">All Subscriptions</option>
                        <option value="on_sale" <?= ($filters['special_filter'] ?? '') === 'on_sale' ? 'selected' : '' ?>>
                            On Sale
                        </option>
                        <option value="limited_offer" <?= ($filters['special_filter'] ?? '') === 'limited_offer' ? 'selected' : '' ?>>
                            Limited Time Offers
                        </option>
                    </select>
                </div>

                <!-- Sort -->
                <div class="filter-group">
                    <label for="sort">Sort By</label>
                    <select id="sort" name="sort">
                        <?php foreach ($sort_options as $option): ?>
                            <option value="<?= $option->value ?>"
                                    <?= ($filters['sort'] ?? '') === $option->value ? 'selected' : '' ?>>
                                <?= $option->label() ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Price Range -->
                <div class="filter-group" style="grid-column: span 2;">
                    <label>Price Range</label>
                    <div class="price-range-group">
                        <div class="price-input">
                            <span>£</span>
                            <input type="number" id="price_min" name="price_min"
                                   placeholder="Min" min="0" step="0.01"
                                   value="<?= htmlspecialchars($filters['price_min'] ?? '') ?>">
                        </div>
                        <div class="price-input">
                            <span>£</span>
                            <input type="number" id="price_max" name="price_max"
                                   placeholder="Max" min="0" step="0.01"
                                   value="<?= htmlspecialchars($filters['price_max'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tags Filter - Checkboxes -->
            <?php if (!empty($available_tags)): ?>
                <div style="margin-top: 1.5rem;">
                    <label style="font-weight: 600; font-size: 0.875rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 0.75rem;">
                        Filter by Tags
                    </label>
                    <div class="tags-filter">
                        <?php
                        $selectedTags = !empty($filters['tags']) ? (is_array($filters['tags']) ? $filters['tags'] : explode(',', $filters['tags'])) : [];
                        foreach ($available_tags as $tag):
                            ?>
                            <label class="tag-checkbox">
                                <input
                                        type="checkbox"
                                        name="tags[]"
                                        value="<?= htmlspecialchars($tag) ?>"
                                        <?= in_array($tag, $selectedTags) ? 'checked' : '' ?>>
                                <span class="tag-label">
                                    <?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="filter-actions">
                <button type="button" class="btn btn-secondary" onclick="clearFilters()">
                    Clear All
                </button>
                <button type="submit" class="btn btn-primary">
                    Apply Filters
                </button>
            </div>

            <!-- Active Filters Display -->
            <div id="active-filters" class="active-filters"></div>
        </form>
    </div>

    <!-- Results Header -->
    <div class="results-header">
        <div class="results-count">
            <strong><?= number_format($pagination['total']) ?></strong>
            subscription<?= $pagination['total'] !== 1 ? 's' : '' ?> found
        </div>
    </div>

    <!-- Plans Grid -->
    <?php if (empty($plans)): ?>
        <div class="empty-state">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <circle cx="11" cy="11" r="8"></circle>
                <path d="m21 21-4.35-4.35"></path>
            </svg>
            <h3>No subscriptions found</h3>
            <p>Try adjusting your filters or search terms</p>
            <button class="btn btn-primary" onclick="clearFilters()">Clear Filters</button>
        </div>
    <?php else: ?>
        <div class="plans-grid">
            <?php foreach ($plans as $plan): ?>
                <div class="plan-card">
                    <?php if ($plan->is_featured): ?>
                        <div class="featured-badge">Featured</div>
                    <?php endif; ?>

                    <?php
                    // Check if plan is on sale or limited offer
                    $isOnSale = false;
                    $isLimitedOffer = false;
                    $hasDiscount = false;

                    foreach ($plan->pricingTiers ?? [] as $tier) {
                        if ($tier->hasDiscount()) {
                            $hasDiscount = true;
                            break;
                        }
                    }

                    // Limited offer: has end date in near future (within 30 days)
                    if ($plan->end_date && $plan->end_date->diffInDays(now()) <= 30) {
                        $isLimitedOffer = true;
                    }

                    $isOnSale = $hasDiscount;
                    ?>

                    <?php if ($isOnSale && !$plan->is_featured): ?>
                        <div class="sale-badge">On Sale</div>
                    <?php elseif ($isLimitedOffer && !$plan->is_featured && !$isOnSale): ?>
                        <div class="offer-badge">Limited Offer</div>
                    <?php endif; ?>

                    <div class="plan-image">
                        <?= strtoupper(substr($plan->name, 0, 1)) ?>
                    </div>


                    <div class="plan-content">
                        <div class="plan-header">
                            <div class="plan-site">
                                <?= htmlspecialchars($plan->site->name ?? 'Publication') ?>
                            </div>
                            <h3 class="plan-name"><?= htmlspecialchars($plan->name) ?></h3>

                            <?php
                            // Display categories if present
                            if (is_array($plan->categories) && count($plan->categories) > 0):
                                ?>
                                <div class="plan-categories">
                                    <?php foreach (array_slice($plan->categories, 0, 2) as $cat): ?>
                                        <span class="category-badge">
                                            <?= htmlspecialchars(ucfirst($cat)) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <p class="plan-description">
                            <?= htmlspecialchars(substr($plan->description ?? '', 0, 120)) ?>
                            <?= strlen($plan->description ?? '') > 120 ? '...' : '' ?>
                        </p>

                        <div class="plan-features">
                            <?php if ($plan->hasDigitalOption()): ?>
                                <span class="feature-tag digital">Digital</span>
                            <?php endif; ?>
                            <?php if ($plan->hasPrintOption()): ?>
                                <span class="feature-tag print">Print</span>
                            <?php endif; ?>
                            <?php if ($plan->includes_insider): ?>
                                <span class="feature-tag">Insider Access</span>
                            <?php endif; ?>

                            <?php
                            // Display plan tags
                            if (is_array($plan->tags) && count($plan->tags) > 0):
                                foreach (array_slice($plan->tags, 0, 3) as $tag):
                                    ?>
                                    <span class="feature-tag tag-badge">
                                    <?= htmlspecialchars(ucwords(str_replace('-', ' ', $tag))) ?>
                                </span>
                                <?php
                                endforeach;
                            endif;
                            ?>
                        </div>

                        <div class="plan-pricing">
                            <div class="price-label">Starting at</div>
                            <div>
                                <span class="price-from">from</span>
                                <span class="price-amount">
                                    £<?= number_format($plan->price, 2) ?>
                                </span>
                            </div>
                        </div>

                        <a href="/<?= \App\Framework\Support\SiteContext::slug() ?>/subscriptions/onetime/<?= $plan->id ?>"
                           class="btn btn-primary" style="width: 100%;">
                            View Details
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
            <div class="pagination">
                <button
                        onclick="goToPage(<?= $pagination['current_page'] - 1 ?>)"
                        <?= $pagination['current_page'] <= 1 ? 'disabled' : '' ?>>
                    Previous
                </button>

                <?php
                $start = max(1, $pagination['current_page'] - 2);
                $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

                if ($start > 1): ?>
                    <button onclick="goToPage(1)">1</button>
                    <?php if ($start > 2): ?>
                        <span class="pagination-info">...</span>
                    <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start; $i <= $end; $i++): ?>
                    <button
                            onclick="goToPage(<?= $i ?>)"
                            class="<?= $i === $pagination['current_page'] ? 'active' : '' ?>">
                        <?= $i ?>
                    </button>
                <?php endfor; ?>

                <?php if ($end < $pagination['total_pages']): ?>
                    <?php if ($end < $pagination['total_pages'] - 1): ?>
                        <span class="pagination-info">...</span>
                    <?php endif; ?>
                    <button onclick="goToPage(<?= $pagination['total_pages'] ?>)">
                        <?= $pagination['total_pages'] ?>
                    </button>
                <?php endif; ?>

                <button
                        onclick="goToPage(<?= $pagination['current_page'] + 1 ?>)"
                        <?= $pagination['current_page'] >= $pagination['total_pages'] ? 'disabled' : '' ?>>
                    Next
                </button>
            </div>

            <div style="text-align: center; color: var(--text-secondary); font-size: 0.95rem;">
                Showing <?= (($pagination['current_page'] - 1) * $pagination['per_page']) + 1 ?>
                - <?= min($pagination['current_page'] * $pagination['per_page'], $pagination['total']) ?>
                of <?= number_format($pagination['total']) ?> subscriptions
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<script>
    const form = document.getElementById('filter-form');
    let selectedCategories = new Set(<?= json_encode($selectedCategories ?? []) ?>);

    // Category filtering
    function filterByCategory(event, category) {
        event.preventDefault();

        // Update hidden input
        document.getElementById('category').value = category;

        // Update active state
        document.querySelectorAll('.category-tile').forEach(tile => {
            tile.classList.remove('active');
        });
        event.currentTarget.classList.add('active');

        // Apply filters
        applyFilters();
    }

    // Category carousel functions
    function scrollCarousel(direction) {
        const carousel = document.getElementById('category-carousel');
        const scrollAmount = 300;

        if (direction === 'left') {
            carousel.scrollBy({left: -scrollAmount, behavior: 'smooth'});
        } else {
            carousel.scrollBy({left: scrollAmount, behavior: 'smooth'});
        }
    }

    function toggleCategory(category) {
        if (selectedCategories.has(category)) {
            selectedCategories.delete(category);
        } else {
            selectedCategories.add(category);
        }

        // Update UI
        updateCategoryUI();

        // Apply filters
        applyFilters();
    }

    function updateCategoryUI() {
        // Update tile selected states
        document.querySelectorAll('.category-tile').forEach(tile => {
            const category = tile.dataset.category;
            if (selectedCategories.has(category)) {
                tile.classList.add('selected');
            } else {
                tile.classList.remove('selected');
            }
        });

        // Update hidden inputs
        const container = document.getElementById('category-inputs');
        container.innerHTML = '';
        selectedCategories.forEach(cat => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'categories[]';
            input.value = cat;
            container.appendChild(input);
        });
    }

    // Initialize category UI
    updateCategoryUI();

    // Apply filters on form submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        applyFilters();
    });

    // Apply filters on input changes
    document.querySelectorAll('#filter-form input, #filter-form select').forEach(input => {
        input.addEventListener('change', function () {
            if (this.type !== 'text') {
                applyFilters();
            }
        });
    });

    // Debounce search input
    let searchTimeout;
    document.getElementById('search').addEventListener('input', function () {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 500);
    });

    function applyFilters() {
        const formData = new FormData(form);
        const params = new URLSearchParams();

        for (const [key, value] of formData.entries()) {
            if (value) {
                // Handle arrays (tags and categories)
                if (key === 'tags[]' || key === 'categories[]') {
                    params.append(key, value);
                } else {
                    params.set(key, value);
                }
            }
        }

        // Add selected categories from carousel
        selectedCategories.forEach(cat => {
            params.append('categories[]', cat);
        });

        window.location.href = '?' + params.toString();
    }

    function clearFilters() {
        window.location.href = window.location.pathname;
    }

    function goToPage(page) {
        const params = new URLSearchParams(window.location.search);
        params.set('page', page);
        window.location.href = '?' + params.toString();
    }

    // Display active filters
    function displayActiveFilters() {
        const container = document.getElementById('active-filters');
        const params = new URLSearchParams(window.location.search);
        const chips = [];

        const filterLabels = {
            search: 'Search',
            site_id: 'Publication',
            delivery_type: 'Delivery Type',
            special_filter: 'Special Offer',
            price_min: 'Min Price',
            price_max: 'Max Price',
            sort: 'Sort'
        };

        // Handle regular filters
        for (const [key, value] of params.entries()) {
            if (key === 'tags[]' || key === 'categories[]') continue; // Handle separately

            if (value && key !== 'page' && filterLabels[key]) {
                let displayValue = value;

                // Get display text for select options
                const select = document.getElementById(key);
                if (select && select.tagName === 'SELECT') {
                    const option = select.querySelector(`option[value="${value}"]`);
                    if (option) {
                        displayValue = option.textContent;
                    }
                }

                chips.push(`
                    <div class="filter-chip">
                        <span>${filterLabels[key]}: ${displayValue}</span>
                        <button onclick="removeFilter('${key}')" type="button">×</button>
                    </div>
                `);
            }
        }

        // Handle categories
        const categories = params.getAll('categories[]');
        categories.forEach(cat => {
            const displayCat = cat.split('-').map(word =>
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');

            chips.push(`
                <div class="filter-chip">
                    <span>Category: ${displayCat}</span>
                    <button onclick="removeCategory('${cat}')" type="button">×</button>
                </div>
            `);
        });

        // Handle tags
        const tags = params.getAll('tags[]');
        tags.forEach(tag => {
            const displayTag = tag.split('-').map(word =>
                word.charAt(0).toUpperCase() + word.slice(1)
            ).join(' ');

            chips.push(`
                <div class="filter-chip">
                    <span>Tag: ${displayTag}</span>
                    <button onclick="removeTag('${tag}')" type="button">×</button>
                </div>
            `);
        });

        container.innerHTML = chips.join('');
        container.style.display = chips.length > 0 ? 'flex' : 'none';
    }

    function removeFilter(key) {
        const params = new URLSearchParams(window.location.search);
        params.delete(key);
        window.location.href = '?' + params.toString();
    }

    function removeCategory(catValue) {
        const params = new URLSearchParams(window.location.search);
        const categories = params.getAll('categories[]');

        // Remove all categories first
        params.delete('categories[]');

        // Add back all categories except the one to remove
        categories.forEach(cat => {
            if (cat !== catValue) {
                params.append('categories[]', cat);
            }
        });

        window.location.href = '?' + params.toString();
    }

    function removeTag(tagValue) {
        const params = new URLSearchParams(window.location.search);
        const tags = params.getAll('tags[]');

        // Remove all tags first
        params.delete('tags[]');

        // Add back all tags except the one to remove
        tags.forEach(tag => {
            if (tag !== tagValue) {
                params.append('tags[]', tag);
            }
        });

        window.location.href = '?' + params.toString();
    }

    // Initialize active filters display
    displayActiveFilters();
</script>
</body>
</html>