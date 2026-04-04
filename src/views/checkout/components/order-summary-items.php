<?php
/**
 * Order summary items — merchant-grouped item rows.
 *
 * Deduplicates items that share the same product_id + variant_id (or
 * subscription_plan_id) by summing their quantities and subtotals.  This
 * prevents the same product appearing twice in the sidebar when the cart
 * contains offer or bundle rows for the same SKU.
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
// Two rows are considered the same logical line when they share:
//   - subscription_plan_id (for subscription rows), or
//   - product_id + variant_id (for product rows)
// When duplicates are found the first row's metadata is preserved and
// quantities/subtotals are summed.
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
            $productName = $item['product_name'] ?? ($item['name'] ?? 'Item');
            $productImg = $item['product_image'] ?? null;
            ?>

            <div class="cs-item">
                <!-- Thumbnail -->
                <?php if ($productImg): ?>
                    <img src="<?= htmlspecialchars($productImg) ?>"
                         alt="<?= htmlspecialchars($productName) ?>"
                         class="cs-item-img">
                <?php else: ?>
                    <div class="cs-item-img-placeholder" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"
                             stroke="currentColor" stroke-width="1.5">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="8.5" cy="8.5" r="1.5"/>
                            <polyline points="21 15 16 10 5 21"/>
                        </svg>
                    </div>
                <?php endif; ?>

                <!-- Details -->
                <div class="cs-item-details">
                    <div class="cs-item-name"><?= htmlspecialchars($productName) ?></div>

                    <?php if (!empty($item['variant_id']) && !empty($item['variant_options'])): ?>
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

                    <?php if (!empty($item['options']['subscription_plan_id'])): ?>
                        <input type="hidden" name="plan_id"
                               value="<?= (int)$item['options']['subscription_plan_id'] ?>">
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