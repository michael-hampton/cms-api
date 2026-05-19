<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\Member;
use App\Models\Order;
use App\Models\OrderItem;

final class OrdersExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'orders';
    }

    public function export(Member $member): array
    {
        $orders = Order::where('user_id', $member->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return $orders->map(function (Order $order) {
            $items = OrderItem::where('order_id', $order->id)
                ->get()
                ->map(fn(OrderItem $i) => [
                    'product_name' => $i->product_name,
                    'product_sku'  => $i->product_sku,
                    'quantity'     => $i->quantity,
                    'unit_price'   => $i->unit_price,
                    'total'        => $i->total,
                ])
                ->toArray();

            return [
                'id'             => $order->id,
                'order_number'   => $order->order_number,
                'status'         => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal'       => $order->subtotal,
                'tax'            => $order->tax,
                'shipping'       => $order->shipping,
                'discount'       => $order->discount,
                'total'          => $order->total,
                'currency'       => $order->currency,
                'created_at'     => $order->created_at?->format('Y-m-d H:i:s'),
                'completed_at'   => $order->completed_at?->format('Y-m-d H:i:s'),
                'items'          => $items,
            ];
        })->toArray();
    }
}