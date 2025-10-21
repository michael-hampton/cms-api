<?php

namespace App\Tests\Unit\Services;

use App\Framework\Container;
use App\Models\Newsletter;
use App\Models\Subscriber;
use App\Services\BlockParserService;
use App\Services\NewsletterSendService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSendTest extends FunctionalTestCase
{
    public function testSendNewsletterToConfirmedSubscribers(): void
    {
        // Create confirmed subscribers
        Subscriber::create([
            'email' => 'user1@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => '',
        ]);

        Subscriber::create([
            'email' => 'user2@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => '',
        ]);

        // Create newsletter
        $content = json_encode([
            ['type' => 'heading', 'level' => 1, 'content' => 'Weekly Update'],
            ['type' => 'paragraph', 'content' => 'Here is your update!']
        ]);

        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => $content,
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Mock dependencies
        $parser = (new Container())->resolve(BlockParserService::class);

        $emailService = $this->createMock(\App\Services\EmailService::class);
        $emailService->expects($this->exactly(2))
            ->method('send')
            ->willReturn(true);

        $subscriberRepo = new \App\Repositories\SubscriberRepository();
        $newsletterRepo = new \App\Repositories\NewsletterRepository();
        $sendRepo = new \App\Repositories\NewsletterSendRepository();

        $service = new \App\Services\NewsletterSendService(
            $parser,
            $emailService,
            $subscriberRepo,
            $newsletterRepo,
            $sendRepo,
            $this->siteId
        );

        $result = $service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['recipients']);
    }

    public function testSendNewsletterWithNoSubscribers(): void
    {
        $newsletter = Newsletter::create([
            'title' => 'Test Newsletter',
            'content' => '[]',
            'interval' => Newsletter::INTERVAL_DAILY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        $parser = (new Container())->resolve(BlockParserService::class);
        $emailService = new \App\Services\EmailService();
        $service = (new Container())->resolve(NewsletterSendService::class);

        $result = $service->sendNewsletter($newsletter, $this->siteId);

        $this->assertFalse($result['success']);
        $this->assertEquals('No confirmed subscribers', $result['error']);
    }

    public function testSendDueNewslettersOnly(): void
    {
        // Create due newsletter
        Newsletter::create([
            'title' => 'Daily News',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'News']]),
            'interval' => Newsletter::INTERVAL_DAILY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-2 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create not-due newsletter
        Newsletter::create([
            'title' => 'Weekly Digest',
            'content' => json_encode([['type' => 'paragraph', 'content' => 'Digest']]),
            'interval' => Newsletter::INTERVAL_WEEKLY,
            'last_sent' => date('Y-m-d H:i:s', strtotime('-3 days')),
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Create confirmed subscriber
        Subscriber::create([
            'email' => 'subscriber@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => '',
        ]);

        $parser = (new Container())->resolve(BlockParserService::class);
        $emailService = $this->createMock(\App\Services\EmailService::class);
        $emailService->expects($this->once())
            ->method('send')
            ->willReturn(true);

        $service = new NewsletterSendService(
            $parser,
            $emailService,
            new \App\Repositories\SubscriberRepository(),
            new \App\Repositories\NewsletterRepository(),
            new \App\Repositories\NewsletterSendRepository(),
        );

        $results = $service->sendDueNewsletters($this->siteId);;

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }
}