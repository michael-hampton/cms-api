<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationCategory;
use App\Services\OpenCollab\Moderation\EscalationRoutingService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class EscalationRoutingServiceTest extends TestCase
{
    private EscalationRoutingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EscalationRoutingService();
    }

    #[DataProvider('categoryTeamProvider')]
    public function test_category_routes_to_expected_team(EscalationCategory $category, string $expectedTeam): void
    {
        $this->assertSame($expectedTeam, $this->service->teamFor($category));
    }

    public static function categoryTeamProvider(): array
    {
        return [
            'copyright -> legal' => [EscalationCategory::Copyright, 'legal'],
            'music rights -> legal' => [EscalationCategory::MusicRights, 'legal'],
            'legal -> legal' => [EscalationCategory::Legal, 'legal'],
            'ai generated -> editorial' => [EscalationCategory::AiGenerated, 'editorial'],
            'brand safety -> brand_safety' => [EscalationCategory::BrandSafety, 'brand_safety'],
            'affiliate abuse -> commercial_compliance' => [EscalationCategory::AffiliateAbuse, 'commercial_compliance'],
            'sponsored content -> commercial_compliance' => [EscalationCategory::SponsoredContent, 'commercial_compliance'],
            'other -> editorial' => [EscalationCategory::Other, 'editorial'],
        ];
    }
}