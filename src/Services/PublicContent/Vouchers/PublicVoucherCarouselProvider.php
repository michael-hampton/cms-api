<?php

namespace App\Services\PublicContent\Vouchers;

use App\Models\Voucher;

final class PublicVoucherCarouselProvider
{
    /**
     * @return list<array<string, mixed>>
     */
    public function forSite(int $siteId, int $limit = 8): array
    {
        $now = date('Y-m-d H:i:s');

        return Voucher::query()
            ->where('site_id', $siteId)
            ->where('status', 'active')
            ->where(function ($query) use ($now) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', $now);
            })
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                    ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            ->orderBy('expires_at')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get()
            ->map(static fn (Voucher $voucher): array => self::mapVoucher($voucher))
            ->values()
            ->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private static function mapVoucher(Voucher $voucher): array
    {
        return [
            'id' => (int) $voucher->id,
            'code' => (string) $voucher->code,
            'title' => (string) $voucher->name,
            'description' => $voucher->description ? (string) $voucher->description : null,
            'type' => (string) $voucher->type,
            'value' => (float) $voucher->value,
            'discount_label' => self::discountLabel($voucher),
            'minimum_order_value' => $voucher->minimum_order_value !== null ? (float) $voucher->minimum_order_value : null,
            'maximum_discount' => $voucher->maximum_discount !== null ? (float) $voucher->maximum_discount : null,
            'expires_at' => self::formatDate($voucher->expires_at),
            'terms_and_conditions' => $voucher->terms_and_conditions ?? null,
        ];
    }

    private static function discountLabel(Voucher $voucher): string
    {
        if ((string) $voucher->type === 'percentage') {
            return rtrim(rtrim(number_format((float) $voucher->value, 2), '0'), '.') . '% off';
        }

        return '£' . number_format((float) $voucher->value, 2) . ' off';
    }

    private static function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        return $date ? (string) $date : null;
    }
}
