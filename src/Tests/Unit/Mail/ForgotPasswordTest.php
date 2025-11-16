<?php

namespace App\Tests\Unit\Mail;

use App\Mail\ForgotPassword;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ForgotPasswordTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $mailable = new ForgotPassword($email, $token);
        $mailable->build();

        $this->assertEquals('Password Reset Request', $mailable->subject);
    }

    public function testIncludesResetUrl(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $mailable = new ForgotPassword($email, $token);
        $mailable->build();

        $this->assertStringContainsString($token, $mailable->viewData['resetUrl']);
        $this->assertStringContainsString('/reset-password', $mailable->viewData['resetUrl']);
        $this->assertStringContainsString(urlencode($email), $mailable->viewData['resetUrl']);
    }

    public function testRendersWithEmail(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $mailable = new ForgotPassword($email, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($email, $html);
    }

    public function testIncludesSecurityTips(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $mailable = new ForgotPassword($email, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('Security Tips', $html);
        $this->assertStringContainsString('password manager', $html);
    }
}