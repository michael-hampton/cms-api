<?php

namespace App\Tests\Unit\Mail;

use App\Mail\NewsletterSignupConfirmationWithTracking;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class NewsletterSignupConfirmationLatestTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token);
        $mailable->build();

        $this->assertEquals('Confirm Your Newsletter Subscription', $mailable->subject);
    }

    public function testIncludesConfirmationUrl(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token);
        $mailable->build();

        $this->assertStringContainsString($token, $mailable->viewData['confirmationUrl']);
        $this->assertStringContainsString('/newsletter/confirm', $mailable->viewData['confirmationUrl']);
        $this->assertStringContainsString(urlencode($email), $mailable->viewData['confirmationUrl']);
    }

    public function testUsesFirstNameWhenProvided(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';
        $firstName = 'Sarah';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token, $firstName);
        $mailable->build();

        $this->assertEquals('Sarah', $mailable->viewData['name']);
    }

    public function testUsesDefaultNameWhenNotProvided(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token);
        $mailable->build();

        $this->assertEquals('there', $mailable->viewData['name']);
    }

    public function testRendersWithTrackingLink(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Newsletter Archive', $html);
        $this->assertStringContainsString('/newsletters', $html);
    }

    public function testIncludesPrivacyPromise(): void
    {
        $email = 'subscriber@example.com';
        $token = 'signup-token-123';

        $mailable = new NewsletterSignupConfirmationWithTracking($email, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Privacy Promise', $html);
        $this->assertStringContainsString('No spam', $html);
        $this->assertStringContainsString('Never sell your data', $html);
    }
}