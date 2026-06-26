<?php

namespace App\Resources;

use App\Framework\Resource\JsonResource;
use DateTimeInterface;

class BillingHistoryRowResource extends JsonResource
{
    public function toArray(): array
    {
        $order   = $this->resource['order'];
        $payment = $this->resource['payment'] ?? null;

        $rawDate = $payment
            ? ($payment->created_at ?? $order->created_at ?? null)
            : ($order->created_at ?? null);

        $rawAmount   = $order->total;
        $rawCurrency = $payment ? ($payment->currency ?? $order->currency  ?? 'GBP') : ($order->currency ?? 'GBP');

        return [
            'date'            => $this->formatDate($rawDate),
            'date_value'      => $this->formatDateValue($rawDate),
            'reference'       => $payment
                ? ($payment->payment_intent_id ?? $payment->stripe_payment_id ?? $payment->id ?? ('order-' . $order->id))
                : ($order->payment_intent_id ?? ('order-' . $order->id)),
            'order_id'        => $order->id,
            'order_url'       => '/press-stack/account/orders/' . $order->id,
            'order_number'    => $order->order_number ?? ('#' . $order->id),
            'subscription_id' => $order->one_time_subscription_id,
            'order_status'    => $order->status ?? null,
            'payment_status'  => $payment
                ? ($payment->status ?? $order->payment_status ?? null)
                : ($order->payment_status ?? null),
            'amount'          => $this->formatAmount($rawAmount, $rawCurrency),
            'invoice_url'     => $payment ? ($payment->invoice_url ?? null) : null,
        ];
    }

    private function formatDate(mixed $value): string
    {
        return $this->toDateTime($value)?->format('j M Y') ?? '—';
    }

    private function formatDateValue(mixed $value): string
    {
        return $this->toDateTime($value)?->format('Y-m-d') ?? '';
    }

    private function formatAmount(mixed $amount, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format((float) $amount, 2);
    }

    private function toDateTime(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (!$value) {
            return null;
        }

        try {
            return new \DateTime((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}