<?php

namespace App\Tests\Unit\Services\Members\Segmentation;

use App\Enums\Member\CampaignPurpose;
use PHPUnit\Framework\TestCase;

class CampaignPurposeTest extends TestCase
{
    public function test_transactional_does_not_require_consent(): void
    {
        $this->assertFalse(CampaignPurpose::TRANSACTIONAL->requiresConsent());
    }

    public function test_marketing_requires_consent(): void
    {
        $this->assertTrue(CampaignPurpose::MARKETING->requiresConsent());
    }

    public function test_product_updates_requires_consent(): void
    {
        $this->assertTrue(CampaignPurpose::PRODUCT_UPDATES->requiresConsent());
    }

    public function test_all_non_transactional_purposes_require_consent(): void
    {
        $requiresConsent = array_filter(
            CampaignPurpose::cases(),
            fn($p) => $p !== CampaignPurpose::TRANSACTIONAL
        );

        foreach ($requiresConsent as $purpose) {
            $this->assertTrue(
                $purpose->requiresConsent(),
                "Expected {$purpose->value} to require consent"
            );
        }
    }
}