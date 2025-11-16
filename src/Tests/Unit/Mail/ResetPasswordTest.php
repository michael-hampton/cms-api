<?php

namespace App\Tests\Unit\Mail;

use App\Mail\ResetPassword;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class ResetPasswordTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $mailable = new ResetPassword($member, $token);
        $mailable->build();

        $this->assertEquals('Reset Your Password', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'user@example.com';
        $member->first_name = 'Bob';
        $member->last_name = 'Johnson';
        return $member;
    }

    public function testIncludesResetUrl(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $mailable = new ResetPassword($member, $token);
        $mailable->build();

        $this->assertStringContainsString($token, $mailable->viewData['resetUrl']);
        $this->assertStringContainsString('/reset-password', $mailable->viewData['resetUrl']);
        $this->assertStringContainsString(urlencode($member->email), $mailable->viewData['resetUrl']);
    }

    public function testIncludesExpirationTime(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';
        $expiresIn = 120;

        $mailable = new ResetPassword($member, $token, $expiresIn);
        $mailable->build();

        $this->assertEquals($expiresIn, $mailable->viewData['expiresInMinutes']);
    }

    public function testDefaultExpirationIs60Minutes(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $mailable = new ResetPassword($member, $token);
        $mailable->build();

        $this->assertEquals(60, $mailable->viewData['expiresInMinutes']);
    }

    public function testRendersWithSecurityInformation(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $mailable = new ResetPassword($member, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString('60 minutes', $html);
        $this->assertStringContainsString($member->first_name, $html);
    }
}