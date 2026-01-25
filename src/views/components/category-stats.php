<section class="category-stats-container">
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon total">📦</div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['total_products'] ?></span>
                <span class="stat-label">Products</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon sale">🏷️</div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['on_sale_products']->count() ?></span>
                <span class="stat-label">On Sale</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon offers">🔥</div>
            <div class="stat-content">
                <span class="stat-value"><?= $stats['active_offers'] ?></span>
                <span class="stat-label">Active Offers</span>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon price">💰</div>
            <div class="stat-content">
                <span class="stat-value">$<?= number_format($stats['average_price'], 2) ?></span>
                <span class="stat-label">Avg. Price</span>
            </div>
        </div>
    </div>
</section>