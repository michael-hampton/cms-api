<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\ArrayMailer;
use App\Framework\Mail\Mailable;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

class MailManagerTest extends TestCase
{
    private MailManager $manager;

    public function testGetInstanceReturnsSingleton(): void
    {
        $instance1 = MailManager::getInstance();
        $instance2 = MailManager::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    public function testSendMailableSuccess(): void
    {
        $mailable = new TestMailableForManager();
        $result = $this->manager->send($mailable);

        $this->assertTrue($result);
    }

    public function testToReturnsPendingMail(): void
    {
        $pending = $this->manager->to('test@example.com');

        $this->assertInstanceOf(PendingMail::class, $pending);
    }

    public function testSendBuildsMailableBeforeSending(): void
    {
        $mailable = new TestMailableForManager();
        $this->assertEmpty($mailable->subject);

        $this->manager->send($mailable);

        $this->assertEquals('Test Subject', $mailable->subject);
    }

    public function testSendFailsWithoutRecipient(): void
    {
        $mailable = new EmptyMailable();
        $result = $this->manager->send($mailable);

        $this->assertFalse($result);
    }

    protected function setUp(): void
    {
        // Set config to use array mailer
        $config = include __DIR__ . '/../../../config/mail.php';
        $config['driver'] = 'array';

        Config::set('mail', $config);

        $this->manager = MailManager::getInstance();
        ArrayMailer::clear();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
    }
}

class TestMailableForManager extends Mailable
{
    public function build(): self
    {
        return $this
            ->to('recipient@example.com')
            ->subject('Test Subject')
            ->view('test');
    }

    public function render(): string
    {
        return '<p>Test email</p>';
    }
}

class EmptyMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Test');
    }

    public function render(): string
    {
        return 'Test';
    }
}