<?php

namespace App\Tests\Unit\Repositories;

use App\Models\Newsletter;
use App\Repositories\NewsletterRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterRepository $repository;

    public function test_find_returns_newsletter_with_correct_id(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Test content']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->find($newsletter->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($newsletter->id, $result->id);
        $this->assertEquals('Test Newsletter', $result->title);
    }

    public function test_find_returns_null_for_nonexistent_id(): void
    {
        // Act
        $result = $this->repository->find(99999);

        // Assert
        $this->assertNull($result);
    }

    public function test_get_due_newsletters_returns_only_due_newsletters(): void
    {
        // Arrange - Create daily newsletter that's overdue
        $dueNewsletter = Newsletter::create([
            'title' => 'Due Daily',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create weekly newsletter that's not due
        Newsletter::create([
            'title' => 'Not Due Weekly',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create inactive newsletter
        Newsletter::create([
            'title' => 'Inactive',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'active' => false,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getDueNewsletters($this->siteId);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($dueNewsletter->id, $result[0]->id);
    }

    public function test_get_due_newsletters_includes_never_sent_newsletters(): void
    {
        // Arrange
        $neverSent = Newsletter::create([
            'title' => 'Never Sent',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => null,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getDueNewsletters($this->siteId);

        // Assert
        $this->assertGreaterThanOrEqual(1, count($result));
        $ids = array_column($result, 'id');
        $this->assertEmpty($ids);
    }

    public function test_get_due_newsletters_filters_by_site(): void
    {
        // Arrange
        $otherSite = $this->createSite();

        Newsletter::create([
            'title' => 'This Site Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Other Site Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => true,
            'site_id' => $otherSite->id
        ]);

        // Act
        $result = $this->repository->getDueNewsletters($this->siteId);

        // Assert
        foreach ($result as $newsletter) {
            $this->assertEquals($this->siteId, $newsletter->site_id);
        }
    }

    public function test_create_persists_newsletter(): void
    {
        // Arrange
        $data = [
            'title' => 'New Newsletter',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Content']]),
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'active' => true,
            'site_id' => $this->siteId
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result->id);
        $this->assertEquals('New Newsletter', $result->title);
        $this->assertEquals(Newsletter::INTERVAL_MONTHLY, $result->interval);
        $this->assertTrue($result->active);

        // Verify it's in database
        $found = Newsletter::find($result->id);
        $this->assertNotNull($found);
        $this->assertEquals('New Newsletter', $found->title);
    }

    public function test_update_modifies_newsletter(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'Original Title',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->update($newsletter->id, [
            'title' => 'Updated Title',
            'interval' => Newsletter::INTERVAL_MONTHLY,
            'active' => false
        ]);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals('Updated Title', $result->title);
        $this->assertEquals(Newsletter::INTERVAL_MONTHLY, $result->interval);
        $this->assertFalse($result->active);
    }

    public function test_delete_removes_newsletter(): void
    {
        // Arrange
        $newsletter = Newsletter::create([
            'title' => 'To Delete',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->delete($newsletter->id);

        // Assert
        $this->assertTrue($result);
        $this->assertNull(Newsletter::find($newsletter->id));
    }

    public function test_get_published_returns_only_sent_active_newsletters(): void
    {
        // Arrange
        $sent1 = Newsletter::create([
            'title' => 'Sent Newsletter 1',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        $sent2 = Newsletter::create([
            'title' => 'Sent Newsletter 2',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Never sent
        Newsletter::create([
            'title' => 'Never Sent',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => null,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Inactive
        Newsletter::create([
            'title' => 'Inactive',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => false,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getPublished($this->siteId);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals($sent1->id, $result->first()->id); // Most recent first
        $this->assertEquals($sent2->id, $result->last()->id);
    }

    public function test_get_published_orders_by_last_sent_desc(): void
    {
        // Arrange
        $oldest = Newsletter::create([
            'title' => 'Oldest',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        $newest = Newsletter::create([
            'title' => 'Newest',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        $middle = Newsletter::create([
            'title' => 'Middle',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-5 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getPublished($this->siteId);

        // Assert
        $this->assertEquals($newest->id, $result->first()->id);
        $this->assertEquals($middle->id, $result->get(1)->id);
        $this->assertEquals($oldest->id, $result->get(2)->id);
    }

    public function test_get_published_limits_to_10_results(): void
    {
        // Arrange - Create 15 newsletters
        for ($i = 0; $i < 15; $i++) {
            Newsletter::create([
                'title' => 'Newsletter ' . $i,
                'content' => '[]',
                'interval' => Newsletter::INTERVAL_WEEKLY,
                'last_sent' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                'active' => true,
                'site_id' => $this->siteId
            ]);
        }

        // Act
        $result = $this->repository->getPublished($this->siteId);

        // Assert
        $this->assertCount(10, $result);
    }

    public function test_get_published_filters_by_site(): void
    {
        // Arrange
        $otherSite = $this->createSite();

        Newsletter::create([
            'title' => 'This Site',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Other Site',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $otherSite->id
        ]);

        // Act
        $result = $this->repository->getPublished($this->siteId);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($this->siteId, $result->first()->site_id);
    }

    public function test_get_archive_paginates_results(): void
    {
        // Arrange - Create 25 newsletters
        for ($i = 0; $i < 25; $i++) {
            Newsletter::create([
                'title' => 'Newsletter ' . $i,
                'content' => '[]',
                'interval' => Newsletter::INTERVAL_WEEKLY,
                'last_sent' => date('Y-m-d H:i:s', strtotime('-' . $i . ' days')),
                'active' => true,
                'site_id' => $this->siteId
            ]);
        }

        // Act
        $page1 = $this->repository->getArchive($this->siteId, 1, 20);
        $page2 = $this->repository->getArchive($this->siteId, 2, 20);

        // Assert
        $this->assertCount(20, $page1);
        $this->assertCount(5, $page2);
    }

    public function test_get_archive_returns_only_sent_active_newsletters(): void
    {
        // Arrange
        Newsletter::create([
            'title' => 'Sent',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Never Sent',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => null,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Inactive',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => false,
            'site_id' => $this->siteId
        ]);

        // Act
        $result = $this->repository->getArchive($this->siteId);

        // Assert
        $this->assertCount(1, $result);
    }

    public function test_get_archive_orders_by_last_sent_desc(): void
    {
        // Arrange
        $oldest = Newsletter::create([
            'title' => 'Oldest',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-10 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);
        $newest = Newsletter::create([
            'title' => 'Newest',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

// Act
        $result = $this->repository->getArchive($this->siteId);

// Assert
        $this->assertEquals($newest->id, $result->first()->id);
        $this->assertEquals($oldest->id, $result->last()->id);
    }

    public function test_get_archive_filters_by_site(): void
    {
        $otherSite = $this->createSite();
        Newsletter::create([
            'title' => 'This Site',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        Newsletter::create([
            'title' => 'Other Site',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'active' => true,
            'site_id' => $otherSite->id
        ]);

// Act
        $result = $this->repository->getArchive($this->siteId);

// Assert
        $this->assertCount(1, $result);
        $this->assertEquals($this->siteId, $result->first()->site_id);
    }
    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NewsletterRepository();
    }
}