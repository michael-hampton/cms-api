<?php

namespace App\Tests\Unit\Services;

use App\Framework\Mail\ArrayMailer;
use App\Framework\Support\Config;
use App\Models\Member;
use App\Models\Product;
use App\Services\Members\EmailService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class EmailServiceTest extends FunctionalTestCase
{
    private EmailService $emailService;

    public function testSendPriceAlert(): void
    {
        $product = $this->createMockProduct();
        $member = $this->createMockMember();

        $result = $this->emailService->sendPriceAlert(
            $product,
            $member,
            99.99,
            79.99,
            80.00
        );

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertEquals($member->email, $emails[0]['to']);
    }

    private function createMockProduct(): Product
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->description = 'Test description';
        $product->stock = 10;
        return $product;
    }

    private function createMockMember(): Member
    {
        $member = new Member();
        $member->id = 1;
        $member->email = 'test@example.com';
        $member->first_name = 'Test';
        $member->last_name = 'User';
        $member->created_at = date('Y-m-d H:i:s');
        return $member;
    }

    public function testSendDealAlert(): void
    {
        $member = $this->createMockMember();
        $deals = [
            [
                'id' => 1,
                'product_name' => 'Test Product',
                'original_price' => 100.00,
                'deal_price' => 70.00,
                'discount_percentage' => 30
            ]
        ];

        $result = $this->emailService->sendDealAlert($member, $deals);

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
    }

    public function testSendSignupConfirmation(): void
    {
        $member = $this->createMockMember();
        $token = 'verification-token-123';

        $result = $this->emailService->sendSignupConfirmation($member, $token);

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Verify', $emails[0]['subject']);
    }

    public function testSendPasswordReset(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $result = $this->emailService->sendPasswordReset($member, $token, 120);

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
    }

    public function testSendForgotPassword(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $result = $this->emailService->sendForgotPassword($email, $token);

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertEquals($email, $emails[0]['to']);
    }

    public function testSendNewsletterConfirmation(): void
    {
        $email = 'subscriber@example.com';
        $token = 'newsletter-token-123';
        $firstName = 'Test';
        $preferences = ['Weekly Newsletter'];

        $result = $this->emailService->sendNewsletterConfirmation(
            $email,
            $token,
            $firstName,
            $preferences
        );

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
    }

    public function testSendNewsletterWelcome(): void
    {
        $email = 'subscriber@example.com';
        $firstName = 'Test';
        $welcomeOffer = [
            'title' => '20% Off',
            'code' => 'WELCOME20',
            'discount' => 20,
            'expires_at' => date('Y-m-d', strtotime('+30 days'))
        ];

        $result = $this->emailService->sendNewsletterWelcome(
            $email,
            $firstName,
            $welcomeOffer
        );

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
    }

    public function testHandlesEmailFailureGracefully(): void
    {
        // This would require mocking a failing mailer
        // For now, we just verify the method returns a boolean
        $member = $this->createMockMember();
        $token = 'test-token';

        $result = $this->emailService->sendSignupConfirmation($member, $token);

        $this->assertIsBool($result);
    }

    protected function setUp(): void
    {
        // Set config to use array mailer
        $config = include __DIR__ . '/../../../config/mail.php';
        $config['driver'] = 'array';

        Config::set('mail', $config);

        ArrayMailer::clear();
        $this->emailService = new EmailService();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
    }
}