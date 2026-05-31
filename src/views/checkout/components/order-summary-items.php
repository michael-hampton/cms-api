<?php
/**
 * Order summary items — merchant-grouped item rows.
 *
 * Deduplicates items that share the same product_id + variant_id (or
 * subscription_plan_id) by summing their quantities and subtotals.
 *
 * @var array $items Flat array of cart items.
 * @var string $currency Currency symbol (e.g. '£', '$'). Defaults to '£'.
 * @var bool $showMerchantHeader Whether to render the cs-merchant-header row.
 */

use App\Helpers\CartViewHelpers;

$currency = $currency ?? '£';
$showMerchantHeader = $showMerchantHeader ?? true;
$items = $items ?? [];

// ── Deduplicate items ────────────────────────────────────────────────────────
$deduplicated = [];
foreach ($items as $item) {
    if (!empty($item['subscription_plan_id'])) {
        $key = 'plan:' . $item['subscription_plan_id'];
    } else {
        $key = 'product:' . ($item['product_id'] ?? 'unknown') . ':' . ($item['variant_id'] ?? '');
    }

    if (!isset($deduplicated[$key])) {
        $deduplicated[$key] = $item;
    } else {
        $deduplicated[$key]['quantity'] = ($deduplicated[$key]['quantity'] ?? 1) + ($item['quantity'] ?? 1);
        $deduplicated[$key]['subtotal'] = ($deduplicated[$key]['subtotal'] ?? 0) + ($item['subtotal'] ?? 0);
    }
}

$groups = CartViewHelpers::groupByMerchant(array_values($deduplicated));
$groupCount = count($groups);
$groupIndex = 0;
?>

<?php foreach ($groups as $merchantId => $merchantData):
    $groupIndex++;
    $initials = CartViewHelpers::merchantInitials($merchantData['name']);
    $itemCount = count($merchantData['items']);
    ?>

    <div class="cs-merchant-group">

        @include('checkout/components/merchant-group-header', [
        'name' => $merchantData['name'],
        'show' => $groupCount > 1 || $merchantId !== 0,
        'currency' => $currency,
        'subtotal' => $merchantData['subtotal'],
        'itemCount' => $itemCount,
        'initials' => $initials,
        ])

        <?php foreach ($merchantData['items'] as $item):
            $isFreeGift = CartViewHelpers::isFreeGift($item);

            // ── Resolve display name ─────────────────────────────────────
            // For subscriptions the canonical name lives in options.plan_name.
            // Fall back to product_name then name then 'Item'.
            $isSubscription = !empty($item['subscription_plan_id']);
            $opts = $item['options'] ?? [];

            if ($isSubscription && !empty($opts['plan_name'])) {
                $productName = $opts['plan_name'];
            } elseif (!empty($item['product_name'])) {
                $productName = $item['product_name'];
            } elseif (!empty($item['name'])) {
                $productName = $item['name'];
            } else {
                $productName = 'Item';
            }

            // ── Resolve thumbnail ────────────────────────────────────────
            // Subscription may store plan image in options.plan_image or
            // directly on the plan object attached to the item.
            if ($isSubscription) {
                $productImg = $opts['plan_image']
                        ?? $item['plan_image']
                        ?? $item['product_image']
                        ?? null;
            } else {
                $productImg = $item['product_image'] ?? null;
            }
            ?>

            <div class="cs-item" data-item-id="<?= $item['id'] ?>">
                <!-- Thumbnail -->
                <?php if ($productImg): ?>
                    <img src="<?= htmlspecialchars($productImg) ?>"
                         alt="<?= htmlspecialchars($productName) ?>"
                         class="cs-item-img">
                <?php elseif ($isSubscription): ?>
                    <!-- Subscription placeholder icon -->
                    <div class="cs-item-img-placeholder" aria-hidden="true"
                         style="background: linear-gradient(135deg,#1e40af 0%,#3b82f6 100%);">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5">
                            <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/>
                            <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/>
                        </svg>
                    </div>
                <?php else: ?>
                    <div class="cs-item-img-placeholder" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <!-- Details -->
                <div class="cs-item-details">
                    <div class="cs-item-name"><?= htmlspecialchars($productName) ?></div>

                    <?php if ($isSubscription && !empty($opts['delivery_type'])): ?>
                        <div class="cs-item-meta" style="font-size:.7rem; color:var(--text-secondary);">
                            <?= htmlspecialchars(ucfirst($opts['delivery_type'])) ?> delivery
                            <?php if (!empty($opts['billing_period'])): ?>
                                &bull; <?= htmlspecialchars($opts['billing_period']) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isSubscription && !empty($item['variant_id']) && !empty($item['variant_options'])): ?>
                        <div class="cs-item-variant">
                            <?php
                            $parts = [];
                            foreach ($item['variant_options'] as $k => $v) {
                                $parts[] = htmlspecialchars(ucfirst($k)) . ': <strong>' . htmlspecialchars($v) . '</strong>';
                            }
                            echo implode(' · ', $parts);
                            ?>
                        </div>
                        <?php if (!empty($item['sku'])): ?>
                            <div class="cs-item-sku">SKU: <?= htmlspecialchars($item['sku']) ?></div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <div class="cs-item-meta">Qty: <?= (int)($item['quantity'] ?? 1) ?></div>

                    <?php if (!empty($item['trial_days'])): ?>
                        <div style="display:inline-flex;align-items:center;gap:.35rem;background:#f0fdf4;border:1px solid #6ee7b7;border-radius:100px;padding:.2rem .75rem;font-size:.75rem;font-weight:600;color:#065f46;margin-top:.4rem;line-height:1.6;">
                            <span aria-hidden="true">🎁</span>
                            <?= (int)$item['trial_days'] ?>-day free trial included
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($item['estimated_delivery'])): ?>
                        <div class="cs-item-delivery">📦 <?= htmlspecialchars($item['estimated_delivery']) ?></div>
                    <?php endif; ?>

                    <?php if ($isSubscription && !empty($item['subscription_plan_id'])): ?>
                        <input type="hidden" name="plan_id" value="<?= (int)$item['subscription_plan_id'] ?>">
                    <?php endif; ?>
                </div>

                <!-- Price -->
                <div class="cs-item-price <?= $isFreeGift ? 'cs-item-free' : '' ?>">
                    <?= $isFreeGift
                            ? 'FREE'
                            : htmlspecialchars($currency) . number_format((float)($item['subtotal'] ?? 0), 2)
                    ?>
                </div>
            </div>

        <?php endforeach; ?>
    </div>

    <?php if ($groupIndex < $groupCount): ?>
    <div class="cs-group-divider"></div>
<?php endif; ?>

<?php endforeach; ?>