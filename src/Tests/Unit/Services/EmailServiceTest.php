<?php

namespace App\Tests\Unit\Services;

use App\Framework\Mail\Mailable;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Framework\Support\Config;
use App\Models\Member;
use App\Models\Product;
use App\Services\MemberInsights\EmailService;
use App\Tests\Unit\UnitTestCase;
use Mockery;

/**
 * Mail sending is exercised via an injected MailManager mock so we never boot
 * the app, hit MySQL for branding themes, or render markdown templates.
 */
class EmailServiceTest extends UnitTestCase
{
    private EmailService $emailService;

    /** @var list<array{to: mixed, subject: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('mail', [
            'driver' => 'array',
            'from' => ['address' => 'noreply@example.com', 'name' => 'Test'],
        ]);
        MailManager::reset();

        $this->sent = [];
        $mailManager = Mockery::mock(MailManager::class);
        $mailManager->shouldReceive('to')
            ->andReturnUsing(function (string|array $address) {
                $pending = Mockery::mock(PendingMail::class);
                $pending->shouldReceive('send')
                    ->once()
                    ->andReturnUsing(function (Mailable $mailable) use ($address) {
                        $mailable->build();
                        $this->sent[] = [
                            'to' => is_array($address) ? ($address[0] ?? null) : $address,
                            'subject' => $mailable->subject,
                        ];
                        return true;
                    });

                return $pending;
            });

        $this->emailService = new EmailService($mailManager);
    }

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
        $this->assertCount(1, $this->sent);
        $this->assertEquals($member->email, $this->sent[0]['to']);
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
        $this->assertCount(1, $this->sent);
    }

    public function testSendSignupConfirmation(): void
    {
        $member = $this->createMockMember();
        $token = 'verification-token-123';

        $result = $this->emailService->sendSignupConfirmation($member, $token);

        $this->assertTrue($result);
        $this->assertCount(1, $this->sent);
        $this->assertStringContainsString('Verify', $this->sent[0]['subject']);
    }

    public function testSendPasswordReset(): void
    {
        $member = $this->createMockMember();
        $token = 'reset-token-123';

        $result = $this->emailService->sendPasswordReset($member, $token, 120);

        $this->assertTrue($result);
        $this->assertCount(1, $this->sent);
    }

    public function testSendForgotPassword(): void
    {
        $email = 'user@example.com';
        $token = 'forgot-token-123';

        $result = $this->emailService->sendForgotPassword($email, $token);

        $this->assertTrue($result);
        $this->assertCount(1, $this->sent);
        $this->assertEquals($email, $this->sent[0]['to']);
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
        $this->assertCount(1, $this->sent);
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
        $this->assertCount(1, $this->sent);
    }

    public function testHandlesEmailFailureGracefully(): void
    {
        $mailManager = Mockery::mock(MailManager::class);
        $pending = Mockery::mock(PendingMail::class);
        $mailManager->shouldReceive('to')->andReturn($pending);
        $pending->shouldReceive('send')->andThrow(new \RuntimeException('smtp down'));

        $service = new EmailService($mailManager);
        $result = $service->sendSignupConfirmation($this->createMockMember(), 'test-token');

        $this->assertFalse($result);
    }
}
