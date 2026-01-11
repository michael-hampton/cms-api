<?php

namespace App\Tests\Integration\Mail;

use App\Framework\Mail\ArrayMailer;
use App\Framework\Support\Config;
use App\Models\Member;
use App\Models\Product;
use App\Services\Members\EmailService;
use App\Tests\Functional\Controllers\FunctionalTestCase;

class EmailWorkflowTest extends FunctionalTestCase
{
    private EmailService $emailService;

    public function testCompleteUserSignupWorkflow(): void
    {
        $member = $this->createMockMember();
        $verificationToken = bin2hex(random_bytes(32));

        // Step 1: Send signup confirmation
        $result = $this->emailService->sendSignupConfirmation($member, $verificationToken);
        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Verify', $emails[0]['subject']);
        $this->assertStringContainsString($verificationToken, $emails[0]['body']);

        // Simulate email verification happens...

        // Step 2: User could receive a welcome email (not implemented yet)
        // This demonstrates the workflow
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

    public function testPasswordResetWorkflow(): void
    {
        $member = $this->createMockMember();
        $resetToken = bin2hex(random_bytes(32));

        // Step 1: User requests password reset
        $result = $this->emailService->sendPasswordReset($member, $resetToken);
        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Reset', $emails[0]['subject']);
        $this->assertStringContainsString($resetToken, $emails[0]['body']);
    }

    public function testNewsletterSignupWorkflow(): void
    {
        $email = 'newsubscriber@example.com';
        $confirmationToken = bin2hex(random_bytes(32));

        // Step 1: Send confirmation email
        $result = $this->emailService->sendNewsletterConfirmation(
            $email,
            $confirmationToken,
            'New',
            ['Weekly Newsletter', 'Special Offers']
        );
        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);

        // Clear for next step
        ArrayMailer::clear();

        // Step 2: After confirmation, send welcome email
        $welcomeOffer = [
            'title' => '10% Off First Order',
            'description' => 'Welcome bonus',
            'code' => 'WELCOME10',
            'discount' => 10,
            'expires_at' => date('Y-m-d', strtotime('+30 days'))
        ];

        $result = $this->emailService->sendNewsletterWelcome($email, 'New', $welcomeOffer);
        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Welcome', $emails[0]['subject']);
        $this->assertStringContainsString('WELCOME10', $emails[0]['body']);
    }

    public function testPriceAlertWorkflow(): void
    {
        $member = $this->createMockMember();
        $product = $this->createMockProduct();

        // User sets a price alert for $80
        $targetPrice = 80.00;
        $oldPrice = 99.99;
        $newPrice = 75.00; // Price drops below target

        $result = $this->emailService->sendPriceAlert(
            $product,
            $member,
            $oldPrice,
            $newPrice,
            $targetPrice
        );

        $this->assertTrue($result);

        $emails = ArrayMailer::getEmails();
        $this->assertCount(1, $emails);
        $this->assertStringContainsString('Price Drop', $emails[0]['subject']);
        $this->assertStringContainsString($product->name, $emails[0]['body']);
    }

    private function createMockProduct(): Product
    {
        $product = new Product();
        $product->id = 1;
        $product->name = 'Test Product';
        $product->slug = 'test-product';
        $product->description = 'A great test product';
        $product->stock = 10;
        return $product;
    }

    public function testMultipleEmailsInSequence(): void
    {
        $member = $this->createMockMember();

        // Send multiple different emails
        $this->emailService->sendSignupConfirmation($member, 'token1');
        $this->emailService->sendPasswordReset($member, 'token2');
        $this->emailService->sendNewsletterConfirmation($member->email, 'token3');

        $emails = ArrayMailer::getEmails();
        $this->assertCount(3, $emails);

        // Verify all went to the same email
        foreach ($emails as $email) {
            $this->assertEquals($member->email, $email['to']);
        }
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