<?php

namespace App\Tests\Unit\Mail;

use App\Mail\NewsletterSignupConfirmation;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSignupConfirmationTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';

        $mailable = new NewsletterSignupConfirmation($email, $token);
        $mailable->build();

        $this->assertEquals('Confirm Your Newsletter Subscription', $mailable->subject);
    }

    public function testIncludesConfirmationUrl(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';

        $mailable = new NewsletterSignupConfirmation($email, $token);
        $mailable->build();

        $this->assertStringContainsString($token, $mailable->viewData['confirmationUrl']);
        $this->assertStringContainsString('/newsletter/confirm', $mailable->viewData['confirmationUrl']);
        $this->assertStringContainsString(urlencode($email), $mailable->viewData['confirmationUrl']);
    }

    public function testUsesFirstNameWhenProvided(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';
        $firstName = 'Charlie';

        $mailable = new NewsletterSignupConfirmation($email, $token, $firstName);
        $mailable->build();

        $this->assertEquals('Charlie', $mailable->viewData['name']);
    }

    public function testUsesDefaultNameWhenNotProvided(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';

        $mailable = new NewsletterSignupConfirmation($email, $token);
        $mailable->build();

        $this->assertEquals('there', $mailable->viewData['name']);
    }

    public function testIncludesPreferences(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';
        $preferences = ['Weekly Newsletter', 'Product Updates', 'Special Offers'];

        $mailable = new NewsletterSignupConfirmation($email, $token, null, $preferences);
        $mailable->build();

        $this->assertEquals($preferences, $mailable->viewData['preferences']);
    }

    public function testRendersWithAllData(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';
        $firstName = 'Diana';
        $preferences = ['Weekly Newsletter'];

        $mailable = new NewsletterSignupConfirmation($email, $token, $firstName, $preferences);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($firstName, $html);
        $this->assertStringContainsString($email, $html);
        $this->assertStringContainsString('Weekly Newsletter', $html);
    }
}