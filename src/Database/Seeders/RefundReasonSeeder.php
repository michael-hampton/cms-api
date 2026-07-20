<?php

namespace App\Database\Seeders;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Framework\Database\Seeder\Seeder;
use App\Models\BusinessDecision;
use App\Models\RefundReason;

class RefundReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['customer_request', 'Customer request', false, 10],
            ['duplicate', 'Duplicate payment', false, 20],
            ['fraudulent', 'Fraudulent payment', true, 30],
            ['early_cancellation', 'Early cancellation', false, 40],
            ['goodwill', 'Goodwill', true, 50],
        ] as [$code, $label, $requiresNote, $sortOrder]) {
            $reason = RefundReason::where('code', $code)->first() ?? new RefundReason();
            $reason->fill([
                'code' => $code,
                'label' => $label,
                'requires_note' => $requiresNote,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
            $reason->save();
        }

        if (!BusinessDecision::where('category', BusinessDecisionCategoryEnum::REFUNDS->value)
            ->where('is_default', true)->first()) {
            $decision = new BusinessDecision();
            $decision->fill([
                'category' => BusinessDecisionCategoryEnum::REFUNDS->value,
                'name' => 'Global Refund Defaults',
                'description' => 'Fallback decision used when no site or plan has a more specific refund Business Decision assigned.',
                'is_default' => true,
                'is_active' => true,
            ]);
            $decision->save();
        }
    }
}
