# Refund Processed Successfully

Hello **<?= $customer_name ?>**,

Good news! Your refund has been processed successfully.

@promotion(✅ Refund Confirmed - $<?= number_format($refund->amount, 2) ?>)

## Refund Details

**Refund Amount:** @price(<?= number_format($refund->amount, 2) ?>)
**Original Order:** #<?= $order->order_number ?>
**Refund Date:** <?= date('F j, Y', strtotime($refund->created_at)) ?>
**Refund ID:** #<?= $refund->id ?>
**Payment Method:** <?= ucfirst($refund->payment_method ?? 'Original payment method') ?>

@divider

## Processing Timeline

@panel(💰 Your refund of $<?= number_format($refund->amount, 2) ?> will appear in your account within 5-10 business days)

The exact timing depends on your bank or card issuer. Most refunds appear within 3-5 business days, but some may take up to 10 business days.

@divider

## Refunded Items

@foreach($order->items as $item)
<?php if (isset($item->refund_quantity) && $item->refund_quantity > 0): ?>
    - **<?= htmlspecialchars($item->product_name) ?>** (Qty: <?= $item->refund_quantity ?>) - $<?= number_format($item->unit_price * $item->refund_quantity, 2) ?>
<?php endif; ?>
@endforeach

@divider

## What This Means

✓ You don't need to take any further action
✓ The refund will appear on your statement as "<?= config('app.name') ?> Refund"
✓ You'll receive email confirmation once the funds are deposited

@button(View Order Details, <?= config('app.url') ?>/orders/<?= $order->order_number ?>)

@divider

## Still Have Questions?

If you have any questions about your refund or need further assistance, our support team is ready to help.

@buttonSecondary(Contact Support, <?= config('app.url') ?>/support)

@panel(📞 **Quick Contact**
Support hours: Monday-Friday, 9AM-5PM EST
Response time: Usually within 24 hours)

@subcopy(Thank you for your patience during the refund process. We appreciate your business and hope to serve you again soon.)