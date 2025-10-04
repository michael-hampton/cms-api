@include('estate/header')

<?php
// views/estate/property.php
$title = $property['page']->title . ' - Premier Properties';
$description = $property['page']->meta_description ?? substr($property['page']->title, 0, 160);
?>

<main class="mt-20">
    <div class="container" style="padding: 2rem;">
        <!-- Breadcrumbs -->
        <div style="margin-bottom: 2rem; color: var(--text-light);">
            <a href="/" style="color: var(--primary); text-decoration: none;">Home</a> >
            <a href="/properties" style="color: var(--primary); text-decoration: none;">Properties</a> >
            <?= htmlspecialchars($property['page']->title) ?>
        </div>

        <!-- Property Header -->
        <div style="margin-bottom: 3rem;">
            <h1 style="font-size: 2.5rem; margin-bottom: 1rem;"><?= htmlspecialchars($property['page']->title) ?></h1>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <?php if ($property['location']['area']): ?>
                    <p style="color: var(--text-light); font-size: 1.125rem; margin-bottom: 0.5rem;">📍 <?= htmlspecialchars($property['location']['area']) ?></p>
                    <?php endif; ?>
                    <?php if ($property['details']['property_type']): ?>
                    <p style="color: var(--text-light);">Property Type: <?= ucfirst($property['details']['property_type']) ?></p>
                    <?php endif; ?>
                </div>
                <?php if ($property['details']['price']): ?>
                <div style="font-size: 2rem; font-weight: 800; color: var(--primary);">£<?= number_format($property['details']['price']) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 3rem;">
            <!-- Main Content -->
            <div>
                <!-- Property Features Summary -->
                <?php if ($property['details']['bedrooms'] || $property['details']['bathrooms'] || $property['details']['sqft']): ?>
                <div style="background: var(--bg-light); padding: 2rem; border-radius: var(--radius-xl); margin-bottom: 3rem;">
                    <h2 style="margin-bottom: 1.5rem;">Property Features</h2>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <?php if ($property['details']['bedrooms']): ?>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 2rem;">🛏️</div>
                            <div>
                                <div style="font-weight: 600;"><?= $property['details']['bedrooms'] ?> Bedrooms</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['bathrooms']): ?>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 2rem;">🚿</div>
                            <div>
                                <div style="font-weight: 600;"><?= $property['details']['bathrooms'] ?> Bathrooms</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['sqft']): ?>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 2rem;">📐</div>
                            <div>
                                <div style="font-weight: 600;"><?= number_format($property['details']['sqft']) ?> sq ft</div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['property_type']): ?>
                        <div style="display: flex; align-items: center; gap: 1rem;">
                            <div style="font-size: 2rem;">🏠</div>
                            <div>
                                <div style="font-weight: 600;"><?= ucfirst($property['details']['property_type']) ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- CMS Content (Blocks) -->
                <?php if ($property['page']->blocks): ?>
                <?php foreach ($property['page']->blocks as $block): ?>
                <?php if (!in_array($block->type, ['agent-profile', 'contact-form'])): ?>
                <?= $blockParserService->buildBlock($property['page']->id, $block->data + ['type' => $block->type], $block->order) ?>
                <?php endif; ?>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php
                        $agentBlock = null;
                        $contactFormBlock = null;
                        if ($property['page']->blocks):
            foreach ($property['page']->blocks as $block):
            if ($block->type === 'agent-profile') $agentBlock = $block;
            if ($block->type === 'contact-form') $contactFormBlock = $block;
            endforeach;
            endif;
            ?>

            <!-- Sidebar -->
            <div style="position: sticky; top: 2rem; height: fit-content;">
                <!-- Contact Agent -->
                <div style="background: white; border-radius: var(--radius-xl); padding: 2rem; margin-bottom: 2rem; box-shadow: var(--shadow);">
                    <?php if ($contactFormBlock): ?>
                        <?= $blockParserService->buildBlock(
                        $property['page']->id,
                        $contactFormBlock->data + ['type' => $contactFormBlock->type],
                        $contactFormBlock->order,
                        'sidebar'
                        );
                    endif; ?>

                    <?php if ($agentBlock): ?>
                    <div class="sidebar-agent-block" style="margin-top: 20px">
                        <h3>Your Agent</h3>
                        <?= $blockParserService->buildBlock(
                        $property['page']->id,
                        $agentBlock->data + ['type' => $agentBlock->type],
                        $agentBlock->order,
                        'sidebar'
                        ) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Property Summary -->
                <div style="background: white; border-radius: var(--radius-xl); padding: 2rem; box-shadow: var(--shadow);">
                    <h3 style="margin-bottom: 1.5rem;">Property Summary</h3>
                    <div>
                        <?php if ($property['details']['price']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Price</strong>
                            <span>£<?= number_format($property['details']['price']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['property_type']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Type</strong>
                            <span><?= ucfirst($property['details']['property_type']) ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['bedrooms']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Bedrooms</strong>
                            <span><?= $property['details']['bedrooms'] ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['details']['bathrooms']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border);">
                            <strong>Bathrooms</strong>
                            <span><?= $property['details']['bathrooms'] ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if ($property['location']['area']): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.75rem 0;">
                            <strong>Area</strong>
                            <span><?= htmlspecialchars($property['location']['area']) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Properties -->
        <?php if (!empty($related_properties)): ?>
        <section style="margin-top: 6rem; padding-top: 3rem; border-top: 1px solid var(--border);">
            <h2 style="margin-bottom: 2rem;">Similar Properties</h2>
            <div class="properties-grid">
                <?php foreach ($related_properties as $relatedProperty): ?>
                <div class="property-card">
                    <?php if (!empty($relatedProperty['images'])): ?>
                    <div class="property-image">
                        <img src="<?= htmlspecialchars($relatedProperty['images'][0]['src']) ?>" alt="<?= htmlspecialchars($relatedProperty['page']->title) ?>">
                        <?php if ($relatedProperty['details']['price']): ?>
                        <div class="property-price">£<?= number_format($relatedProperty['details']['price']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <div class="property-content">
                        <h3 class="property-title"><?= htmlspecialchars($relatedProperty['page']->title) ?></h3>
                        <div class="property-features">
                            <?php if ($relatedProperty['details']['bedrooms']): ?>
                            <span class="feature">🛏️ <?= $relatedProperty['details']['bedrooms'] ?> bed</span>
                            <?php endif; ?>
                            <?php if ($relatedProperty['details']['bathrooms']): ?>
                            <span class="feature">🚿 <?= $relatedProperty['details']['bathrooms'] ?> bath</span>
                            <?php endif; ?>
                        </div>
                        <a href="/property/<?= $relatedProperty['page']->id ?>" class="btn btn-outline" style="width: 100%; margin-top: 1rem;">View Details</a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </div>
</main>

@include('estate/footer');
