# Order Cancelled

Hello **{{ $order->user->first_name ?? 'Valued Customer' }}**,

We're writing to confirm that your order has been cancelled as requested.

@panel(❌ Order #{{ $order->order_number }} has been cancelled)

## Cancellation Details

**Order Number:** {{ $order->order_number }}
**Cancellation Date:** {{ date('F j, Y', strtotime('now')) }}
**Original Total:** @price({{ number_format($order->total, 2) }})

@if($reason)
**Reason:** {{ $reason }}
@endif

@divider

## Refund Information

@if($order->payment_status === 'paid')
@promotion(💰 Your refund is being processed)

A refund of **${{ number_format($order->total, 2) }}** will be issued to your original payment method within **5-10 business days**.

You'll receive a separate email confirmation once the refund has been processed.
@else
No payment was collected for this order, so no refund is necessary.
@endif

@divider

## Cancelled Items

@foreach($order->items as $item)
- **{{ htmlspecialchars($item->product_name) }}** (Qty: {{ $item->quantity }}) - ${{ number_format($item->total, 2) }}
@endforeach

@divider

## Need Help?

If you have any questions about this cancellation or need assistance with anything else, we're here to help.

@button(Browse Products, {{ config('app.url') }}/products)

@buttonSecondary(Contact Support, {{ config('app.url') }}/support)

@subcopy(We're sorry to see this order cancelled. If there's anything we can do to improve your experience, please let us know.)