<?php

namespace App\Services\PublicContent\Vouchers;

use App\Models\Page;
use App\Models\Voucher;
use App\Repositories\PublicContent\PublicVoucherRepository;

final class PublicVoucherCarouselProvider
{
    public function __construct(private readonly PublicVoucherRepository $vouchers)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function forPage(Page $page, int $siteId): array
    {
        if (!$this->supportsPage($page)) {
            return [];
        }

        $limit = max(1, (int) config('public_content.widgets.vouchers.limit', 8));

        return $this->mapVouchers($this->vouchers->activeForSite($siteId, $limit));
    }

    private function supportsPage(Page $page): bool
    {
        $pageTypes = config('public_content.widgets.vouchers.page_types', ['*']);

        if (!is_array($pageTypes)) {
            return true;
        }

        return in_array('*', $pageTypes, true)
            || in_array((string) $page->page_type, $pageTypes, true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function mapVouchers(iterable $vouchers): array
    {
        $mapped = [];

        foreach ($vouchers as $voucher) {
            if (!$voucher instanceof Voucher) {
                continue;
            }

            $mapped[] = [
                'id' => (int) $voucher->id,
                'code' => (string) $voucher->code,
                'title' => (string) $voucher->name,
                'description' => $voucher->description ? (string) $voucher->description : null,
                'type' => (string) $voucher->type,
                'value' => (float) $voucher->value,
                'discount_label' => $this->discountLabel($voucher),
                'minimum_order_value' => $voucher->minimum_order_value !== null ? (float) $voucher->minimum_order_value : null,
                'maximum_discount' => $voucher->maximum_discount !== null ? (float) $voucher->maximum_discount : null,
                'expires_at' => $this->formatDate($voucher->expires_at),
                'terms_and_conditions' => $voucher->terms_and_conditions ?? null,
            ];
        }

        return $mapped;
    }

    private function discountLabel(Voucher $voucher): string
    {
        if ((string) $voucher->type === 'percentage') {
            return rtrim(rtrim(number_format((float) $voucher->value, 2), '0'), '.') . '% off';
        }

        return '£' . number_format((float) $voucher->value, 2) . ' off';
    }

    private function formatDate(mixed $date): ?string
    {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d H:i:s');
        }

        return $date ? (string) $date : null;
    }
}
