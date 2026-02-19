<?php

namespace App\Tests\Unit\Repositories\Adverts\Boost;

use App\Enums\Boost\BoostEventType;
use App\Models\Boost;
use App\Models\BoostEvent;
use App\Repositories\Adverts\Boost\BoostEventRepository;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class BoostEventRepositoryTest extends FunctionalTestCase
{
    use CreatesTestData;

    private BoostEventRepository $repository;
    private Boost $boost;

    public function test_create_persists_event(): void
    {
        $event = $this->repository->create([
            'boost_id' => $this->boost->id,
            'type' => BoostEventType::Impression->value,
            'session_hash' => 'abc123',
            'occurred_at' => now(),
            'metadata' => [],
        ]);

        $this->assertNotNull($event->id);
        $this->assertEquals(BoostEventType::Impression->value, $event->type);
    }

    public function test_has_event_returns_true_when_exists(): void
    {
        BoostEvent::create([
            'boost_id' => $this->boost->id,
            'type' => BoostEventType::Click->value,
            'session_hash' => 'session-x',
            'occurred_at' => now(),
        ]);

        $this->assertTrue(
            $this->repository->hasEvent($this->boost->id, BoostEventType::Click, 'session-x')
        );
    }

    public function test_has_event_returns_false_for_different_session(): void
    {
        BoostEvent::create([
            'boost_id' => $this->boost->id,
            'type' => BoostEventType::Click->value,
            'session_hash' => 'session-a',
            'occurred_at' => now(),
        ]);

        $this->assertFalse(
            $this->repository->hasEvent($this->boost->id, BoostEventType::Click, 'session-b')
        );
    }

    public function test_count_by_type_returns_correct_count(): void
    {
        foreach (range(1, 3) as $i) {
            BoostEvent::create([
                'boost_id' => $this->boost->id, 'type' => BoostEventType::Impression->value,
                'session_hash' => "s{$i}", 'occurred_at' => now(),
            ]);
        }
        BoostEvent::create([
            'boost_id' => $this->boost->id, 'type' => BoostEventType::Click->value,
            'session_hash' => 'sc1', 'occurred_at' => now(),
        ]);

        $this->assertEquals(3, $this->repository->countByType($this->boost->id, BoostEventType::Impression));
        $this->assertEquals(1, $this->repository->countByType($this->boost->id, BoostEventType::Click));
        $this->assertEquals(0, $this->repository->countByType($this->boost->id, BoostEventType::Conversion));
    }

    public function test_has_event_within_window_returns_true_for_recent(): void
    {
        BoostEvent::create([
            'boost_id' => $this->boost->id,
            'type' => BoostEventType::Click->value,
            'session_hash' => 'sess1',
            'occurred_at' => now_datetime()->modify('-1 hour'),
        ]);

        $this->assertTrue(
            $this->repository->hasEventWithinWindow($this->boost->id, BoostEventType::Click, 'sess1', 24)
        );
    }

    public function test_has_event_within_window_returns_false_for_old_event(): void
    {
        BoostEvent::create([
            'boost_id' => $this->boost->id,
            'type' => BoostEventType::Click->value,
            'session_hash' => 'sess2',
            'occurred_at' => now_datetime()->modify('-48 hours'),
        ]);

        $this->assertFalse(
            $this->repository->hasEventWithinWindow($this->boost->id, BoostEventType::Click, 'sess2', 24)
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new BoostEventRepository();
        $this->boost = Boost::create([
            'merchant_id' => 1, 'boostable_type' => 'product', 'boostable_id' => 1,
            'context' => 'listing', 'status' => 'active', 'multiplier' => 1.5,
            'price_paid' => 35.00, 'currency' => 'GBP',
            'starts_at' => '2026-01-01 00:00:00', 'ends_at' => '2026-01-08 00:00:00',
        ]);
    }
}