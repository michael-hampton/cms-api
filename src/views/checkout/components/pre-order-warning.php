<?php if (!empty($preOrders)): ?>
    <div style="background: #fef3c7; border: 1px solid #f59e0b; padding: 1rem;
                border-radius: 0.5rem; margin-bottom: 1.5rem;" role="region" aria-label="Pre-order notice">
        <div style="font-weight: 600; color: #92400e; margin-bottom: 0.75rem;">
            ⚠️ Pre-Order Items in Cart
        </div>

        <?php foreach ($preOrders as $preOrder): ?>
            <div style="font-size: 0.875rem; color: #78350f; margin-bottom: 0.5rem;">
                <strong><?= htmlspecialchars($preOrder['name']) ?></strong><br>
                <?= htmlspecialchars($preOrder['message']) ?>
                <?php if (!empty($preOrder['ship_date'])): ?>
                    <br>Ships: <?= htmlspecialchars($preOrder['ship_date']) ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <label style="display: flex; align-items: start; gap: 0.75rem; cursor: pointer;
                      margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #fbbf24;">
            <input type="checkbox"
                   id="accept-pre-order"
                   name="accept_pre_order"
                   required
                   style="margin-top: 0.25rem; width: 18px; height: 18px;
                          cursor: pointer; flex-shrink: 0;">
            <span style="flex: 1; font-size: 0.875rem; color: #92400e;">
                I understand this order contains pre-order items and accept the delivery timelines shown above.
            </span>
        </label>
    </div>
<?php endif; ?>