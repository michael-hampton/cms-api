<?php

namespace App\Tests\Unit\Models;

use App\Models\BriefSchedule;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use PHPUnit\Framework\Attributes\DataProvider;

class BriefScheduleModelTest extends FunctionalTestCase
{
    use CreatesTestData;

    public static function validFrequencies(): array
    {
        return [
            ['daily'],
            ['weekly'],
            ['monthly'],
            ['custom'],
        ];
    }

    public function test_requires_source_brief_id(): void
    {
        $this->expectException(\Exception::class);

        BriefSchedule::create([
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);
    }

    public function test_requires_frequency(): void
    {
        $this->expectException(\Exception::class);

        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);
    }

    public function test_frequency_must_be_valid_value(): void
    {
        $this->expectException(\Exception::class);

        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'hourly', // invalid
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);
    }

    #[DataProvider('validFrequencies')]
    public function test_accepts_valid_frequencies(string $frequency): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => $frequency,
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals($frequency, $schedule->frequency);
    }

    public function test_next_run_at_is_datetime(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('2026-06-01 09:00:00'),
            'site_id' => $this->siteId,
        ]);

        $this->assertInstanceOf(\DateTime::class, $schedule->next_run_at);
        $this->assertEquals('2026-06-01 09:00:00', $schedule->next_run_at->format('Y-m-d H:i:s'));
    }

    public function test_occurrences_count_defaults_to_zero(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals(0, $schedule->occurrences_count);
    }

    public function test_active_defaults_to_true(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);

        $this->assertTrue($schedule->active);
    }

    public function test_stores_end_type_after_occurrences(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('+1 day'),
            'end_type' => 'after_occurrences',
            'end_after_occurrences' => 5,
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals('after_occurrences', $schedule->end_type);
        $this->assertEquals(5, $schedule->end_after_occurrences);
    }

    public function test_stores_end_type_on_date(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $endDate = new \DateTime('2026-12-31');

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'daily',
            'next_run_at' => new \DateTime('+1 day'),
            'end_type' => 'on_date',
            'end_date' => $endDate,
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals('on_date', $schedule->end_type);
        $this->assertInstanceOf(\DateTime::class, $schedule->end_date);
        $this->assertEquals('2026-12-31', $schedule->end_date->format('Y-m-d'));
    }

    public function test_week_days_stored_as_array(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'weekly',
            'week_days' => [1, 3, 5],
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals([1, 3, 5], $schedule->week_days);
    }

    public function test_custom_interval_stored_correctly(): void
    {
        $user = $this->createUser();
        $brief = $this->createBrief(['owner_id' => $user->id]);

        $schedule = BriefSchedule::create([
            'source_brief_id' => $brief->id,
            'frequency' => 'custom',
            'custom_interval' => 10,
            'next_run_at' => new \DateTime('+1 day'),
            'site_id' => $this->siteId,
        ]);

        $this->assertEquals(10, $schedule->custom_interval);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->runMigrations();
    }
}