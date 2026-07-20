<?php

namespace App\Database\Seeders;

use App\Enums\Subscriptions\BusinessDecisions\BusinessDecisionCategoryEnum;
use App\Models\BusinessDecision;
use App\Models\CancellationReason;

/**
 * Seeds the six reason codes that used to be the hardcoded
 * SubscriptionCancellationReason enum, plus the single global default
 * Business Decision for the CANCELLATIONS category (so the platform
 * isn't left unconfigured — see CancellationOptionsResolver, which
 * requires at least one resolvable decision).
 *
 * Deliberately does NOT seed any cancellation_reason_policies rows —
 * which save options apply to which reason (discount %, refund caps,
 * marketing consent, or introducing new business-only reasons like
 * "bereavement" from the ticket's example) is a business decision for
 * whoever administers this via the new CRUD, not something to invent
 * here. Left unconfigured, CancellationOptionsResolver's own
 * conservative defaults apply: no save actions, cancellation always
 * allowed, no marketing consent — see FIELD_DEFAULTS on the resolver.
 */
class CancellationReasonSeeder
{
    public function run(): void
    {
        $this->seedReasons();
        $this->seedGlobalDefaultDecision();
    }

    private function seedReasons(): void
    {
        $reasons = [
            ['code' => 'too_expensive', 'label' => "It's too expensive", 'sort_order' => 10, 'requires_note' => false],
            ['code' => 'not_using', 'label' => "I'm not using it enough", 'sort_order' => 20, 'requires_note' => false],
            ['code' => 'switching_to_other', 'label' => "I'm switching to another service", 'sort_order' => 30, 'requires_note' => false],
            ['code' => 'pausing_temporarily', 'label' => "I'm pausing for now", 'sort_order' => 40, 'requires_note' => false],
            ['code' => 'technical_issues', 'label' => 'Technical issues', 'sort_order' => 50, 'requires_note' => false],
            ['code' => 'other', 'label' => 'Other reason', 'sort_order' => 60, 'requires_note' => true],
        ];

        foreach ($reasons as $data) {
            $reason = CancellationReason::where('code', $data['code'])->first() ?? new CancellationReason();

            $reason->fill([
                'code' => $data['code'],
                'label' => $data['label'],
                'sort_order' => $data['sort_order'],
                'requires_note' => $data['requires_note'],
                'is_active' => true,
            ]);
            $reason->save();
        }
    }

    private function seedGlobalDefaultDecision(): void
    {
        $existingDefault = BusinessDecision::where('category', BusinessDecisionCategoryEnum::CANCELLATIONS->value)
            ->where('is_default', true)
            ->first();

        if ($existingDefault) {
            return;
        }

        $decision = new BusinessDecision();
        $decision->fill([
            'category' => BusinessDecisionCategoryEnum::CANCELLATIONS->value,
            'name' => 'Global Cancellation Defaults',
            'description' => 'Fallback decision used when no site or plan has a more specific cancellation Business Decision assigned.',
            'is_default' => true,
            'is_active' => true,
        ]);
        $decision->save();
    }
}
