# 🎯 Price Drop Alert!

Hello **<?= $member->first_name ?>**,

Great news! The price on a product you've been watching has dropped!

@promotion(💰 Save $<?= number_format($savings, 2) ?> (<?= $percentageOff ?>% OFF))

## Product Details

**<?= htmlspecialchars($product->name) ?>**

<?php if (!empty($product->description)): ?>
    <?= htmlspecialchars(substr($product->description, 0, 200)) ?>...
<?php endif; ?>

@divider

## Price Comparison

@table(Was|Now|You Save)
@row($<?= number_format($oldPrice, 2) ?>|@price(<?= number_format($newPrice, 2) ?>)|$<?= number_format($savings, 2) ?> (<?= $percentageOff ?>%))
@endtable

<?php if ($newPrice <= $targetPrice): ?>
    @panel(✅ This product has reached your target price of $<?= number_format($targetPrice, 2) ?>!)
<?php else: ?>
    @panel(📊 Current price: $<?= number_format($newPrice, 2) ?> | Your target: $<?= number_format($targetPrice, 2) ?>)
<?php endif; ?>

@divider

**Stock Status:** <?= $product->stock > 0 ? '✅ In Stock' : '⚠️ Low Stock' ?>

@button(Buy Now, <?= config('app.url') ?>/products/<?= $product->slug ?? $product->id ?>)

@buttonSecondary(View Product, <?= config('app.url') ?>/products/<?= $product->slug ?? $product->id ?>)

@subcopy(You're receiving this because you set a price alert for this product. To manage your alerts\, visit your account settings.)