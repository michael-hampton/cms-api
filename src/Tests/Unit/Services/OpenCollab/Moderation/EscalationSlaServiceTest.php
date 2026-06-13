<?php

namespace App\Tests\Unit\Services\OpenCollab\Moderation;

use App\Enums\OpenCollab\EscalationCategory;
use App\Services\OpenCollab\Moderation\EscalationSlaService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class EscalationSlaServiceTest extends TestCase
{
    private EscalationSlaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EscalationSlaService();
    }

    public function test_brand_safety_due_same_business_day(): void
    {
        // Monday
        $createdAt = new DateTimeImmutable('2026-06-15 10:00:00');

        $due = $this->service->dueAt(EscalationCategory::BrandSafety, $createdAt);

        $this->assertSame('2026-06-15', $due->format('Y-m-d'));
        $this->assertSame('23:59:59', $due->format('H:i:s'));
    }

    public function test_copyright_due_one_business_day_later(): void
    {
        // Monday
        $createdAt = new DateTimeImmutable('2026-06-15 10:00:00');

        $due = $this->service->dueAt(EscalationCategory::Copyright, $createdAt);

        $this->assertSame('2026-06-16', $due->format('Y-m-d')); // Tuesday
    }

    public function test_music_rights_due_two_business_days_later(): void
    {
        // Monday
        $createdAt = new DateTimeImmutable('2026-06-15 10:00:00');

        $due = $this->service->dueAt(EscalationCategory::MusicRights, $createdAt);

        $this->assertSame('2026-06-17', $due->format('Y-m-d')); // Wednesday
    }

    public function test_one_business_day_skips_weekend(): void
    {
        // Friday
        $createdAt = new DateTimeImmutable('2026-06-19 10:00:00');

        $due = $this->service->dueAt(EscalationCategory::Copyright, $createdAt);

        $this->assertSame('2026-06-22', $due->format('Y-m-d')); // Monday
    }

    public function test_two_business_days_skips_weekend(): void
    {
        // Friday
        $createdAt = new DateTimeImmutable('2026-06-19 10:00:00');

        $due = $this->service->dueAt(EscalationCategory::MusicRights, $createdAt);

        $this->assertSame('2026-06-23', $due->format('Y-m-d')); // Tuesday
    }

    public function test_unknown_category_falls_back_to_two_business_days(): void
    {
        $createdAt = new DateTimeImmutable('2026-06-15 10:00:00'); // Monday

        $due = $this->service->dueAt(EscalationCategory::Other, $createdAt);

        $this->assertSame('2026-06-17', $due->format('Y-m-d'));
    }
}