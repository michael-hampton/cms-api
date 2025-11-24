# 🚚 Your Order Has Shipped!

Hello **{{ $order->user->first_name ?? 'Valued Customer' }}**,

Great news! Your order has been shipped and is on its way to you.

@promotion(📦 Your package is on the way!)

## Shipping Information

**Order Number:** {{ $order->order_number }}
**Tracking Number:** {{ $trackingNumber }}
**Carrier:** {{ $carrier }}
**Shipped Date:** {{ date('F j, Y', strtotime($order->shipped_at ?? 'now')) }}

@button(Track Your Package, https://tracking.{{ strtolower($carrier) }}.com/{{ $trackingNumber }})

@divider

## Order Summary

@foreach($order->items as $item)
- **{{ htmlspecialchars($item->product_name) }}** (Qty: {{ $item->quantity }})
@endforeach

**Total:** @price({{ number_format($order->total, 2) }})

@divider

## Delivery Information

Your package should arrive within **3-5 business days**.

@panel(💡 Pro Tip: Use the tracking number above to get real-time updates on your package location)

@divider

## What's Next?

1. Track your package using the button above
2. Ensure someone is available to receive the delivery
3. Contact us if you have any concerns

@buttonSecondary(Contact Support, {{ config('app.url') }}/support)

@subcopy(Questions about your delivery? Our support team is here to help Monday-Friday, 9AM-5PM EST.)