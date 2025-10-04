@include('estate/header')

<?php
// views/estate/homepage.php
$title = $page->title ?? 'Premier Properties - Your Dream Home Awaits';
$description = $page->meta_description ?? 'Find your perfect home with Premier Properties';
?>

<main class="mt-20">
    <?php if ($page && $page->blocks): ?>
    <?php foreach ($page->blocks as $block): ?>
    <?= $blockParserService->buildBlock($page->id, $block->data + ['type' => $block->type], $block->order) ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($featured_properties)): ?>
    <section style="padding: 6rem 2rem;">
        <div class="container">
            <div class="section-header">
                <h2>Featured Properties</h2>
                <p>Discover our handpicked selection of premium properties</p>
            </div>

            <div class="properties-grid">
                <?php foreach ($featured_properties as $property): ?>
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

            <div style="text-align: center; margin-top: 3rem;">
                <a href="/properties" class="btn btn-primary btn-lg">View All Properties</a>
            </div>
        </div>
    </section>
    <?php endif; ?>
</main>

@include('estate/footer');


