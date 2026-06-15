<?php

namespace App\Services\Pricing;

use App\DTO\Pricing\PriceDisclosureContext;

final readonly class PriceDisclosureFormatter
{
    public function __construct(private PriceDisclosureTemplateResolver $templates)
    {
    }

    public function format(PriceDisclosureContext $context): array
    {
        return [
            'main_line' => $context->pricingLabel ?? '',
            'renewal_line' => $context->isRecurring ? ($context->renewalPeriodLabel ?? '') : null,
            'label' => $context->pricingLabel,
            'badges' => $context->badges,
        ];
    }
}
