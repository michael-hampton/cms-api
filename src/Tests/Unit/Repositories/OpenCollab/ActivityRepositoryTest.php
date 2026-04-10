<?php

namespace App\Tests\Unit\Repositories\OpenCollab;

use App\Enums\OpenCollab\ActivityEventType;
use App\Models\ActivityEvent;
use App\Repositories\OpenCollab\ActivityRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;
use App\Tests\Unit\Repositories\RepositoryTestCase;

class ActivityRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private ActivityRepository $repository;

    public function test_record_creates_activity_event(): void
    {
        $page = $this->createPage();
        $user = $this->createUser();
        // Act
        $event = $this->repository->record(
            siteId: $this->siteId,
            userId: $user->id,
            type: ActivityEventType::ArticlePublished,
            payload: ['page_id' => $page->id],
        );

        // Assert
        $this->assertInstanceOf(ActivityEvent::class, $event);
        $this->assertEquals(1, $event->site_id);
        $this->assertEquals($user->id, $event->user_id);
        $this->assertEquals(ActivityEventType::ArticlePublished->value, $event->type);
        $this->assertStringContainsString($page->id, $event->payload);
    }

    public function test_record_stores_empty_payload_by_default(): void
    {
        $user = $this->createUser();
        $event = $this->repository->record($this->siteId, $user->id, ActivityEventType::ArticlePublished);

        $this->assertEquals('[]', $event->payload);
    }

    public function test_for_contributor_returns_events_for_user(): void
    {
        $user = $this->createUser();
        $user2 = $this->createUser();
        // Arrange
        ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);
        ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user2->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);

        // Act
        $results = $this->repository->forContributor($user->id);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($user->id, $results->first()->user_id);
    }

    public function test_for_contributor_orders_newest_first(): void
    {
        $user = $this->createUser();
        // Arrange
        $act1 = ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => '2024-01-01 00:00:00']);
        $act2 = ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => '2024-06-01 00:00:00']);

        // Act
        $results = $this->repository->forContributor($user->id);

        // Assert
        $this->assertEquals($act2->id, $results->first()->id);
    }

    public function test_for_contributor_respects_limit(): void
    {
        $user = $this->createUser();
        // Arrange
        for ($i = 0; $i < 25; $i++) {
            ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);
        }

        // Act
        $results = $this->repository->forContributor($user->id, limit: 5);

        // Assert
        $this->assertCount(5, $results);
    }

    public function test_for_site_returns_events_for_site(): void
    {
        $user = $this->createUser();
        $otherSite = $this->createSite();
        // Arrange
        ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);
        ActivityEvent::create(['site_id' => $otherSite->id, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);

        // Act
        $results = $this->repository->forSite($this->siteId);

        // Assert
        $this->assertCount(1, $results);
        $this->assertEquals($this->siteId, $results->first()->site_id);
    }

    public function test_for_site_respects_limit(): void
    {
        $user = $this->createUser();
        // Arrange
        for ($i = 0; $i < 60; $i++) {
            ActivityEvent::create(['site_id' => $this->siteId, 'user_id' => $user->id, 'type' => ActivityEventType::ArticlePublished->value, 'payload' => '[]', 'created_at' => now()]);
        }

        // Act
        $results = $this->repository->forSite($this->siteId, limit: 10);

        // Assert
        $this->assertCount(10, $results);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ActivityRepository();
    }
}