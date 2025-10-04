@include('estate/header')

<?php
// views/estate/properties.php
$title = 'Properties - Premier Properties';
$description = 'Browse our extensive collection of premium properties';
?>

<main class="mt-20">
    <div class="container" style="padding: 2rem;">
        <div style="margin-bottom: 3rem;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;">Find Your Perfect Property</h1>
            <p style="font-size: 1.125rem; color: var(--text-light);">Browse our extensive collection of premium properties</p>
        </div>

        <!-- Search Filters -->
        <div style="background: white; border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 3rem; box-shadow: var(--shadow);">
            <form method="GET" action="/properties">
                <div class="form-row">
                    <div class="form-group">
                        <label>Location</label>
                        <input type="text" name="location" value="<?= htmlspecialchars($filters['location']) ?>" placeholder="Enter area or postcode">
                    </div>
                    <div class="form-group">
                        <label>Property Type</label>
                        <select name="property_type">
                            <option value="">Any Type</option>
                            <option value="house" <?= $filters['property_type'] === 'house' ? 'selected' : '' ?>>House</option>
                            <option value="apartment" <?= $filters['property_type'] === 'apartment' ? 'selected' : '' ?>>Apartment</option>
                            <option value="townhouse" <?= $filters['property_type'] === 'townhouse' ? 'selected' : '' ?>>Townhouse</option>
                            <option value="penthouse" <?= $filters['property_type'] === 'penthouse' ? 'selected' : '' ?>>Penthouse</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Min Price</label>
                        <input type="number" name="price_min" value="<?= htmlspecialchars($filters['price_min']) ?>" placeholder="Min price">
                    </div>
                    <div class="form-group">
                        <label>Max Price</label>
                        <input type="number" name="price_max" value="<?= htmlspecialchars($filters['price_max']) ?>" placeholder="Max price">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Bedrooms</label>
                        <select name="bedrooms">
                            <option value="">Any</option>
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                            <option value="<?= $i ?>" <?= $filters['bedrooms'] == $i ? 'selected' : '' ?>><?= $i ?>+</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bathrooms</label>
                        <select name="bathrooms">
                            <option value="">Any</option>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <option value="<?= $i ?>" <?= $filters['bathrooms'] == $i ? 'selected' : '' ?>><?= $i ?>+</option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; justify-content: center; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary">Search Properties</button>
                    <a href="/properties" class="btn btn-outline">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Results -->
        <div style="margin-bottom: 2rem;">
            <p style="color: var(--text-light);"><?= count($properties) ?> properties found</p>
        </div>

        <div class="properties-grid">
            <?php foreach ($properties as $property): ?>
            <div class="property-card">
                <?php if (!empty($property['images'])): ?>
                <div class="property-image">
                    <img src="<?= htmlspecialchars($property['images'][0]['src']) ?>" alt="<?= htmlspecialchars($property['page']->title) ?>">
                    <div class="property-badge">For Sale</div>
                    <?php if ($property['details']['price']): ?>
                    <div class="property-price">£<?= number_format($property['details']['price']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="property-content">
                    <h3 class="property-title"><?= htmlspecialchars($property['page']->title) ?></h3>
                    <div class="property-location">📍 <?= htmlspecialchars($property['location']['area'] ?? 'London') ?></div>

                    <div class="property-features">
                        <?php if ($property['details']['bedrooms']): ?>
                        <span class="feature">🛏️ <?= $property['details']['bedrooms'] ?> bed</span>
                        <?php endif; ?>
                        <?php if ($property['details']['bathrooms']): ?>
                        <span class="feature">🚿 <?= $property['details']['bathrooms'] ?> bath</span>
                        <?php endif; ?>
                        <?php if ($property['details']['sqft']): ?>
                        <span class="feature">📐 <?= number_format($property['details']['sqft']) ?> sq ft</span>
                        <?php endif; ?>
                    </div>

                    <div class="property-actions">
                        <a href="/property/<?= $property['page']->id ?>" class="btn btn-outline">View Details</a>
                        <a href="/contact?property=<?= $property['page']->id ?>" class="btn btn-primary">Enquire</a>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($pagination['total_pages'] > 1): ?>
        <div style="display: flex; justify-content: center; gap: 0.5rem; margin-top: 3rem;">
            <?php if ($pagination['has_previous']): ?>
            <a href="?page=<?= $pagination['current_page'] - 1 ?>" class="btn btn-outline">Previous</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
            <a href="?page=<?= $i ?>" class="btn <?= $i == $pagination['current_page'] ? 'btn-primary' : 'btn-outline' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($pagination['has_next']): ?>
            <a href="?page=<?= $pagination['current_page'] + 1 ?>" class="btn btn-outline">Next</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

@include('estate/footer');
