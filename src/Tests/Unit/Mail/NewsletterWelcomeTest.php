<?php

namespace App\Tests\Unit\Mail;

use App\Mail\NewsletterWelcome;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterWelcomeTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $email = 'subscriber@example.com';

        $mailable = new NewsletterWelcome($email);
        $mailable->build();

        $this->assertStringContainsString('Welcome', $mailable->subject);
    }

    public function testUsesFirstNameWhenProvided(): void
    {
        $email = 'subscriber@example.com';
        $firstName = 'Eve';

        $mailable = new NewsletterWelcome($email, $firstName);
        $mailable->build();

        $this->assertEquals('Eve', $mailable->viewData['name']);
    }

    public function testUsesDefaultNameWhenNotProvided(): void
    {
        $email = 'subscriber@example.com';

        $mailable = new NewsletterWelcome($email);
        $mailable->build();

        $this->assertEquals('there', $mailable->viewData['name']);
    }

    public function testIncludesWelcomeOfferWhenProvided(): void
    {
        $email = 'subscriber@example.com';
        $welcomeOffer = [
            'title' => '20% Off Your First Order',
            'description' => 'Use this code at checkout',
            'code' => 'WELCOME20',
            'discount' => 20,
            'expires_at' => date('Y-m-d', strtotime('+30 days'))
        ];

        $mailable = new NewsletterWelcome($email, null, $welcomeOffer);
        $mailable->build();

        $this->assertEquals($welcomeOffer, $mailable->viewData['welcomeOffer']);
    }

    public function testRendersWithWelcomeOffer(): void
    {
        $email = 'subscriber@example.com';
        $welcomeOffer = [
            'title' => '20% Off',
            'description' => 'Welcome discount',
            'code' => 'WELCOME20',
            'discount' => 20,
            'expires_at' => date('Y-m-d', strtotime('+30 days'))
        ];

        $mailable = new NewsletterWelcome($email, 'Frank', $welcomeOffer);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('WELCOME20', $html);
        $this->assertStringContainsString('20% Off', $html);
    }

    public function testRendersWithoutWelcomeOffer(): void
    {
        $email = 'subscriber@example.com';

        $mailable = new NewsletterWelcome($email);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Welcome', $html);
        $this->assertIsString($html);
    }
}