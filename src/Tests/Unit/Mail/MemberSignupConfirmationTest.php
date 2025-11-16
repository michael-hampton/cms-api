<?php

namespace App\Tests\Unit\Mail;

use App\Mail\MemberSignupConfirmation;
use App\Models\Member;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class MemberSignupConfirmationTest extends FunctionalTestCase
{
    public function testBuildsSetsCorrectSubject(): void
    {
        $member = $this->createMockMember();
        $token = 'test-verification-token';

        $mailable = new MemberSignupConfirmation($member, $token);
        $mailable->build();

        $this->assertStringContainsString('Verify Your Email', $mailable->subject);
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'newuser@example.com';
        $member->first_name = 'Alice';
        $member->last_name = 'Smith';
        $member->created_at = date('Y-m-d H:i:s');
        return $member;
    }

    public function testIncludesVerificationUrl(): void
    {
        $member = $this->createMockMember();
        $token = 'test-token-123';

        $mailable = new MemberSignupConfirmation($member, $token);
        $mailable->build();

        $this->assertStringContainsString($token, $mailable->viewData['verificationUrl']);
        $this->assertStringContainsString('/verify-email', $mailable->viewData['verificationUrl']);
    }

    public function testRendersWithMemberData(): void
    {
        $member = $this->createMockMember();
        $token = 'test-token';

        $mailable = new MemberSignupConfirmation($member, $token);
        $mailable->build();
        $html = $mailable->render();

        $this->assertStringContainsString($member->first_name, $html);
        $this->assertStringContainsString($member->email, $html);
    }

    public function testUsesMarkdownTemplate(): void
    {
        $member = $this->createMockMember();
        $token = 'test-token';

        $mailable = new MemberSignupConfirmation($member, $token);
        $mailable->build();

        $this->assertEquals('emails.auth.signup-confirmation', $mailable->markdown);
    }
}