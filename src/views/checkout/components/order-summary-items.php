<?php
/**
 * Order summary items — merchant-grouped item rows.
 *
 * @var array $items Flat array of cart items.
 * @var string $currency Currency symbol (e.g. '£', '$'). Defaults to '£'.
 * @var bool $showMerchantHeader Whether to render the cs-merchant-header row.
 */

use App\Helpers\CartViewHelpers;

$currency = $currency ?? '£';
$showMerchantHeader = $showMerchantHeader ?? true;
$items = $items ?? [];
$groups = CartViewHelpers::groupByMerchant($items ?? []);
$groupCount = count($groups);
$groupIndex = 0;

?>
    <style>
        /* Merchant group header */
        .cs-merchant-group {
            margin-bottom: 1.25rem;
        }

        .cs-merchant-group:last-child {
            margin-bottom: 0;
        }


        /* Summary item row */
        .cs-item {
            display: grid;
            grid-template-columns: 52px 1fr auto;
            gap: 0.75rem;
            padding: 0.625rem 0;
            border-bottom: 1px dashed #f0f4f8;
            align-items: center;
        }

        .cs-item:last-child {
            border-bottom: none;
        }

        .cs-item-img {
            width: 52px;
            height: 52px;
            object-fit: cover;
            border-radius: 0.4rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .cs-item-img-placeholder {
            width: 52px;
            height: 52px;
            border-radius: 0.4rem;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #cbd5e1;
            flex-shrink: 0;
        }

        .cs-item-details {
            min-width: 0;
        }

        .cs-item-name {
            font-size: 0.8rem;
            font-weight: 600;
            color: #1e293b;
            line-height: 1.35;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .cs-item-variant {
            font-size: 0.7rem;
            color: #64748b;
            margin-top: 0.15rem;
        }

        .cs-item-sku {
            font-size: 0.65rem;
            color: #94a3b8;
            margin-top: 0.1rem;
        }

        .cs-item-meta {
            font-size: 0.72rem;
            color: #64748b;
            margin-top: 0.1rem;
        }

        .cs-item-delivery {
            font-size: 0.7rem;
            color: #059669;
            margin-top: 0.15rem;
        }

        .cs-item-price {
            font-size: 0.85rem;
            font-weight: 700;
            color: #1e293b;
            white-space: nowrap;
        }

        .cs-item-free {
            color: #059669;
        }

        .cs-group-divider {
            height: 1px;
            background: linear-gradient(90deg, #2563eb22, #e2e8f0, transparent);
            margin: 1rem 0;
        }
    </style>

<?php foreach ($groups as $merchantId => $merchantData):
    $groupIndex++;
    $isFreeGiftGroup = false; // per-item check below

    /* Avatar initials */
    $words = array_filter(explode(' ', $merchantData['name']));
    $initials = implode('', array_map(fn($w) => strtoupper($w[0]), array_slice($words, 0, 2)));
    $initials = $initials ?: '?';
    $itemCount = count($merchantData['items']);
    ?>

    <div class="cs-merchant-group">
        @include('checkout/components/merchant-group-header', [
        'name' => $merchantData['name'],
        'show' => $groupCount > 1 || $merchantId !== 0,
        'currency' => $currency,
        'subtotal' => $merchantData['subtotal'],
        'itemCount' => count($merchantData['items']),
        'initials' => CartViewHelpers::merchantInitials($merchantData['name']),
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
                    <div class="cs-item-img-placeholder">
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
                        <?php if (!empty($item['variant_sku'])): ?>
                            <div class="cs-item-sku">SKU: <?= htmlspecialchars($item['variant_sku']) ?></div>
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
                        <div class="cs-item-delivery">
                            📦 <?= htmlspecialchars($item['estimated_delivery']) ?>
                        </div>
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