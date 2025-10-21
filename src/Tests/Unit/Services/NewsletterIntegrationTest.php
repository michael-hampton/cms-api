<?php

namespace App\Tests\Unit\Services;

use App\Framework\Container;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterIntegrationTest extends FunctionalTestCase
{
    public function testCompleteSignupAndUnsubscribeFlow(): void
    {
        // Step 1: Signup
        $signupResponse = $this->postForSite('/api/newsletter/signup', [
            'email' => 'integration@example.com'
        ]);

        $this->assertResponseStatus(200, $signupResponse);
        $signupData = json_decode($signupResponse->getContent(), true);
        $this->assertTrue($signupData['data']['success']);

        $token = $signupData['data']['confirmation_token'];

        // Step 2: Confirm
        $confirmResponse = $this->postForSite('/api/newsletter/confirm', [
            'token' => $token
        ]);

        $this->assertResponseStatus(200, $confirmResponse);
        $confirmData = json_decode($confirmResponse->getContent(), true);
        $this->assertTrue($confirmData['data']['success']);

        // Step 3: Verify in subscriber list
        $listResponse = $this->getForSite('/api/newsletter/subscribers');
        $listData = json_decode($listResponse->getContent(), true);
        $this->assertContains('integration@example.com', $listData['data']['subscribers']);

        // Step 4: Unsubscribe
        $subscriber = \App\Models\Subscriber::findByEmail('integration@example.com', $this->siteId);
        $unsubResponse = $this->postForSite('/api/newsletter/unsubscribe', [
            'token' => $subscriber->unsubscribe_token
        ]);

        $this->assertResponseStatus(200, $unsubResponse);

        // Step 5: Verify removed from list
        $finalListResponse = $this->getForSite('/api/newsletter/subscribers');
        $finalListData = json_decode($finalListResponse->getContent(), true);
        $this->assertNotContains('integration@example.com', $finalListData['data']['subscribers']);
    }

    public function testNewsletterSendingWithBlocks(): void
    {
        // Create confirmed subscriber
        \App\Models\Subscriber::create([
            'email' => 'blocks@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => ''
        ]);

        // Create newsletter with various block types
        $content = json_encode([
            [
                'type' => 'heading',
                'level' => 1,
                'content' => 'Newsletter Title'
            ],
            [
                'type' => 'paragraph',
                'content' => 'This is the introduction paragraph.'
            ],
            [
                'type' => 'list',
                'items' => ['Feature 1', 'Feature 2', 'Feature 3']
            ],
            [
                'type' => 'button',
                'content' => 'Read More',
                'url' => 'https://example.com/article'
            ],
            [
                'type' => 'image',
                'url' => 'https://example.com/image.jpg',
                'alt' => 'Newsletter image'
            ]
        ]);

        $newsletter = \App\Models\Newsletter::create([
            'title' => 'Rich Content Newsletter',
            'content' => $content,
            'interval' => \App\Models\Newsletter::INTERVAL_DAILY,
            'active' => true,
            'site_id' => $this->siteId
        ]);

        // Send newsletter
        $parser = (new Container())->resolve(\App\Services\BlockParserService::class);
        $emailService = $this->createMock(\App\Services\EmailService::class);

        // Capture the HTML that would be sent
        $sentHtml = null;
        $emailService->expects($this->once())
            ->method('send')
            ->willReturnCallback(function($to, $subject, $html) use (&$sentHtml) {
                $sentHtml = $html;
                return true;
            });

        $service = new \App\Services\NewsletterSendService(
            $parser,
            $emailService,
            new \App\Repositories\SubscriberRepository(),
            new \App\Repositories\NewsletterRepository(),
            new \App\Repositories\NewsletterSendRepository(),
        );
        $result = $service->sendNewsletter($newsletter, $this->siteId);

        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['recipients']);

        // Verify HTML contains expected elements
        $this->assertStringContainsString('<h1>Newsletter Title</h1>', $sentHtml);
        $this->assertStringContainsString('<p>This is the introduction paragraph.</p>', $sentHtml);
        $this->assertStringContainsString('<ul>', $sentHtml);
        $this->assertStringContainsString('<li>Feature 1</li>', $sentHtml);
        $this->assertStringContainsString('<a href="https://example.com/article"', $sentHtml);
        $this->assertStringContainsString('<img src="https://example.com/image.jpg"', $sentHtml);
    }

    public function testMultipleSitesIsolation(): void
    {
        // Create another site
        $site2 = \App\Models\Site::create([
            'name' => 'Second Site',
            'slug' => 'second-site',
            'is_default' => false
        ]);

        // Create subscriber for first site
        \App\Models\Subscriber::create([
            'email' => 'site1@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $this->siteId,
            'unsubscribe_token' => ''
        ]);

        // Create subscriber for second site with same email
        \App\Models\Subscriber::create([
            'email' => 'site1@example.com',
            'confirmed' => true,
            'subscribed_at' => date('Y-m-d H:i:s'),
            'site_id' => $site2->id,
            'unsubscribe_token' => ''
        ]);

        // Get subscribers for first site
        $response = $this->getForSite('/api/newsletter/subscribers');
        $data = json_decode($response->getContent(), true);

        // Should only return 1 subscriber (for this site)
        $this->assertEquals(1, $data['data']['count']);
    }
}