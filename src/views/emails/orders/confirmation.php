# Order Confirmation

Hello **{{ $customerName }}**,

Thank you for your order! We're pleased to confirm that we've received your order and it's being processed.

## Order Details

**Order Number:** {{ $order->order_number }}
**Order Date:** {{ $order->created_at->format('F j, Y') }}
**Status:** {{ ucfirst($order->status) }}

## Items Ordered

@foreach($order->items as $item)
---
**{{ htmlspecialchars($item->product_name) }}**
Quantity: {{ $item->quantity }}
Price: ${{ number_format($item->unit_price, 2) }}
Subtotal: ${{ number_format($item->subtotal, 2) }}
@endforeach

---

## Order Summary

Subtotal: ${{ number_format($order->subtotal, 2) }}
@if($order->shipping_cost > 0)
Shipping: ${{ number_format($order->shipping_cost, 2) }}
@endif
@if($order->tax > 0)
Tax: ${{ number_format($order->tax, 2) }}
@endif
@if($order->discount > 0)
Discount: -${{ number_format($order->discount, 2) }}
@endif
**Total:** @price({{ number_format($order->total, 2) }})

@if($shippingAddress)
## Shipping Address

{{ htmlspecialchars($shippingAddress['name'] ?? '') }}
{{ htmlspecialchars($shippingAddress['line1']) }}
<?php if (!empty($shippingAddress['line2'])): ?>
    {{ htmlspecialchars($shippingAddress['line2']) }}
<?php endif; ?>
{{ htmlspecialchars($shippingAddress['city']) }}, {{ htmlspecialchars($shippingAddress['state']) }} {{ htmlspecialchars($shippingAddress['postal_code']) }}
{{ htmlspecialchars($shippingAddress['country']) }}
@endif

@button(View Order, {{ config('app.url') }}/orders/{{ $order->order_number }})

If you have any questions about your order, please don't hesitate to contact us.

Thank you for your business!