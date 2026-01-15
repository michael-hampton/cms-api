<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Comparison</title>
    <style>
        .comparison-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        .comparison-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .comparison-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .comparison-table thead th {
            border-bottom: 2px solid #e5e5e5;
        }

        .product-header {
            padding: 1.5rem;
            text-align: center;
            vertical-align: top;
            min-width: 200px;
        }

        .product-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .product-name {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1.5rem;
            color: #059669;
            font-weight: 700;
        }

        .spec-group-header {
            background: #f9f9f9;
            padding: 1rem 1.5rem;
            font-weight: 700;
            font-size: 1.125rem;
            border-bottom: 1px solid #e5e5e5;
        }

        .spec-row {
            border-bottom: 1px solid #f0f0f0;
        }

        .spec-row:hover {
            background: #f9f9f9;
        }

        .spec-label {
            padding: 1rem 1.5rem;
            font-weight: 600;
            background: #fafafa;
            border-right: 1px solid #e5e5e5;
            vertical-align: middle;
            width: 200px;
        }

        .spec-value {
            padding: 1rem 1.5rem;
            text-align: center;
            vertical-align: middle;
        }

        .spec-value.different {
            background: #fef3c7;
            font-weight: 600;
        }

        .ai-summary {
            margin: 2rem 0;
            padding: 1.5rem;
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
        }

        .ai-summary h3 {
            margin-top: 0;
            color: #1e40af;
        }

        .ai-summary pre {
            white-space: pre-wrap;
            font-family: system-ui;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            .spec-row {
                grid-template-columns: 1fr;
            }

            .spec-label {
                border-right: none;
                border-bottom: 1px solid #e5e5e5;
            }
        }
    </style>
</head>
<body>
<div class="comparison-container">
    <div class="comparison-header">
        <h1>Product Comparison</h1>
    </div>

    <?php if (!empty($comparison['ai_summary'])): ?>
        <div class="ai-summary">
            <h3>🤖 Key Differences</h3>
            <pre><?= htmlspecialchars($comparison['ai_summary']) ?></pre>
        </div>
    <?php endif; ?>

    <table class="comparison-table">
        <!-- Product Headers -->
        <thead>
        <tr>
            <th class="spec-label" style="width: 200px;"></th>
            <?php foreach ($comparison['products'] as $product): ?>
                <th class="product-header">
                    <img src="<?= htmlspecialchars($product['image']) ?>"
                         alt="<?= htmlspecialchars($product['name']) ?>"
                         class="product-image">
                    <div class="product-name"><?= htmlspecialchars($product['name']) ?></div>
                    <div class="product-price">
                        <?php if ($product['sale_price'] && $product['sale_price'] < $product['price']): ?>
                            $<?= number_format($product['sale_price'], 2) ?>
                        <?php else: ?>
                            $<?= number_format($product['price'], 2) ?>
                        <?php endif; ?>
                    </div>
                </th>
            <?php endforeach; ?>
        </tr>
        </thead>

        <!-- Specification Groups -->
        <tbody>
        <?php foreach ($comparison['specification_groups'] as $group): ?>
            <tr>
                <td colspan="<?= count($comparison['products']) + 1 ?>" class="spec-group-header">
                    <?= htmlspecialchars($group['name']) ?>
                </td>
            </tr>

            <?php foreach ($group['specifications'] as $key => $productSpecs): ?>
                <?php
                // Check if this spec has different values
                $values = array_map(fn($spec) => $spec->value, $productSpecs);
                $isDifferent = count(array_unique($values)) > 1;
                ?>
                <tr class="spec-row">
                    <td class="spec-label"><?= htmlspecialchars($key) ?></td>
                    <?php
                    // Create array indexed by product position
                    $valuesByPosition = [];
                    foreach ($productSpecs as $index => $spec) {
                        $valuesByPosition[$index] = $spec->value;
                    }

                    // Output values in correct order
                    for ($i = 0; $i < count($comparison['products']); $i++):
                        ?>
                        <td class="spec-value <?= $isDifferent ? 'different' : '' ?>">
                            <?= htmlspecialchars($valuesByPosition[$i] ?? 'N/A') ?>
                        </td>
                    <?php endfor; ?>
                </tr>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>