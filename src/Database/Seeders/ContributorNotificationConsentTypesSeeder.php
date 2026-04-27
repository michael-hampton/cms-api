<?php

namespace App\Database\Seeders;

use App\Framework\Database\Seeder\Seeder;

class ContributorNotificationConsentTypesSeeder extends Seeder
{
    private array $types = [
        // Content lifecycle
        ['code' => 'contributor.article_approved', 'name' => 'Article Approved', 'category' => 'content', 'scope' => 'contributor', 'default_granted' => true, 'required' => false],
        ['code' => 'contributor.article_rejected', 'name' => 'Article Rejected', 'category' => 'content', 'scope' => 'contributor', 'default_granted' => true, 'required' => false],
        ['code' => 'contributor.article_needs_changes', 'name' => 'Article Needs Changes', 'category' => 'content', 'scope' => 'contributor', 'default_granted' => true, 'required' => false],
        // Earnings / payouts
        ['code' => 'contributor.payout_processed', 'name' => 'Payout Processed', 'category' => 'financial', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        ['code' => 'contributor.payout_failed', 'name' => 'Payout Failed', 'category' => 'financial', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        // Disputes
        ['code' => 'contributor.dispute_raised', 'name' => 'Dispute Raised (ack)', 'category' => 'financial', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        ['code' => 'contributor.dispute_resolved', 'name' => 'Dispute Resolved', 'category' => 'financial', 'scope' => 'contributor', 'default_granted' => true, 'required' => false],
        // Moderation / risk
        ['code' => 'contributor.violation_recorded', 'name' => 'Violation Recorded', 'category' => 'moderation', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        // Platform / contracts
        ['code' => 'contributor.contract_published', 'name' => 'New Contract Published', 'category' => 'platform', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        ['code' => 'contributor.guidelines_updated', 'name' => 'Guidelines Updated', 'category' => 'platform', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
        // Account
        ['code' => 'contributor.account_suspended', 'name' => 'Account Suspended', 'category' => 'account', 'scope' => 'contributor', 'default_granted' => true, 'required' => true],
    ];

    public function run(): void
    {
        foreach ($this->types as $type) {
            \App\Models\ConsentType::updateOrCreate(
                ['code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => "Notify contributor when: {$type['name']}",
                    'category' => $type['category'],
                    'scope' => $type['scope'],
                    'is_active' => true,
                    'is_required' => $type['required'],
                    'retention_days' => null,
                ],
            );
        }
    }
}