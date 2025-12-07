<?php
// src/Tests/Unit/Repositories/NewsletterSendRepositoryTest.php

namespace App\Tests\Unit\Repositories;

use App\Models\NewsletterSend;
use App\Repositories\NewsletterSendRepository;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class NewsletterSendRepositoryTest extends RepositoryTestCase
{
    use CreatesTestData;

    private NewsletterSendRepository $repository;

    public function test_get_by_newsletter_id_returns_all_sends(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'recipient_count' => 100
        ]);

        NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'recipient_count' => 150
        ]);

        // Act
        $result = $this->repository->getByNewsletterId($newsletter->id);

        // Assert
        $this->assertCount(2, $result);
    }

    public function test_get_by_newsletter_id_returns_empty_array_when_none(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();

        // Act
        $result = $this->repository->getByNewsletterId($newsletter->id);

        // Assert
        $this->assertEmpty($result);
    }

    public function test_get_by_newsletter_id_filters_by_newsletter(): void
    {
        // Arrange
        $newsletter1 = $this->createNewsletter();
        $newsletter2 = $this->createNewsletter();

        NewsletterSend::create([
            'newsletter_id' => $newsletter1->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => 100
        ]);

        NewsletterSend::create([
            'newsletter_id' => $newsletter2->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => 50
        ]);

        // Act
        $result = $this->repository->getByNewsletterId($newsletter1->id);

        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals($newsletter1->id, $result[0]['newsletter_id']);
    }

    public function test_create_persists_newsletter_send(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();
        $sentAt = date('Y-m-d H:i:s');

        $data = [
            'newsletter_id' => $newsletter->id,
            'sent_at' => $sentAt,
            'recipient_count' => 250
        ];

        // Act
        $result = $this->repository->create($data);

        // Assert
        $this->assertNotNull($result->id);
        $this->assertEquals($newsletter->id, $result->newsletter_id);
        $this->assertEquals(250, $result->recipient_count);

        // Verify in database
        $found = NewsletterSend::find($result->id);
        $this->assertNotNull($found);
        $this->assertEquals(250, $found->recipient_count);
    }

    public function test_find_returns_newsletter_send(): void
    {
        // Arrange
        $newsletter = $this->createNewsletter();
        $send = NewsletterSend::create([
            'newsletter_id' => $newsletter->id,
            'sent_at' => date('Y-m-d H:i:s'),
            'recipient_count' => 100
        ]);

        // Act
        $result = $this->repository->find($send->id);

        // Assert
        $this->assertNotNull($result);
        $this->assertEquals($send->id, $result->id);
        $this->assertEquals(100, $result->recipient_count);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NewsletterSendRepository();
    }
}