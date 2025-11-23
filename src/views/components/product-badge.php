<?php if (isset($product) && $product->relationLoaded('badges')): ?>
    <div class="product-badges"
         style="position: absolute; top: 1rem; left: 1rem; display: flex; flex-direction: column; gap: 0.5rem; z-index: 10;">
        <?php foreach ($product->activeBadges as $badge): ?>
            <span class="product-badge" style="
                    background: <?= htmlspecialchars($badge->color) ?>;
                    color: white;
                    padding: 0.25rem 0.75rem;
                    border-radius: 0.25rem;
                    font-size: 0.75rem;
                    font-weight: 600;
                    text-transform: uppercase;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    ">
                <?php if ($badge->icon): ?>
                    <span style="margin-right: 0.25rem;"><?= $badge->icon ?></span>
                <?php endif; ?>
                <?= htmlspecialchars($badge->label) ?>
            </span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>