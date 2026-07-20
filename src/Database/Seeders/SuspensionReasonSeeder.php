<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;
use App\Models\SuspensionReason;

class SuspensionReasonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['payment_dispute', 'Payment dispute', false, 10],
            ['policy_violation', 'Policy violation', false, 20],
            ['fraud_suspected', 'Suspected fraud', false, 30],
            ['chargeback', 'Chargeback', false, 40],
            ['other', 'Other reason', true, 50],
        ] as [$code, $label, $requiresNote, $sortOrder]) {
            $reason = SuspensionReason::where('code', $code)->first() ?? new SuspensionReason();
            $reason->fill([
                'code' => $code,
                'label' => $label,
                'requires_note' => $requiresNote,
                'sort_order' => $sortOrder,
                'is_active' => true,
            ]);
            $reason->save();
        }
    }
}
