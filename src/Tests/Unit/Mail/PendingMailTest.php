<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\ArrayMailer;
use App\Framework\Mail\Mailable;
use App\Framework\Mail\MailManager;
use App\Framework\Mail\PendingMail;
use App\Framework\Support\Config;
use PHPUnit\Framework\TestCase;

class PendingMailTest extends TestCase
{
    private MailManager $manager;

    public function testCanSetSingleRecipient(): void
    {
        $pending = new PendingMail($this->manager, 'test@example.com');
        $mailable = new SimpleMailable();

        $pending->send($mailable);

        $this->assertCount(1, $mailable->to);
        $this->assertEquals('test@example.com', $mailable->to[0]['address']); //todo check this
    }

    public function testCanSetMultipleRecipients(): void
    {
        $pending = new PendingMail(
            $this->manager,
            ['test1@example.com', 'test2@example.com']
        );
        $mailable = new SimpleMailable();

        $pending->send($mailable);

        $this->assertCount(2, $mailable->to);
    }

    public function testCanAddCc(): void
    {
        $pending = new PendingMail($this->manager, 'test@example.com');
        $pending->cc('cc@example.com');
        $mailable = new SimpleMailable();

        $pending->send($mailable);

        $this->assertCount(1, $mailable->cc);
    }

    public function testCanAddMultipleCc(): void
    {
        $pending = new PendingMail($this->manager, 'test@example.com');
        $pending->cc(['cc1@example.com', 'cc2@example.com']);
        $mailable = new SimpleMailable();

        $pending->send($mailable);

        $this->assertCount(2, $mailable->cc);
    }

    public function testCanAddBcc(): void
    {
        $pending = new PendingMail($this->manager, 'test@example.com');
        $pending->bcc('bcc@example.com');
        $mailable = new SimpleMailable();

        $pending->send($mailable);

        $this->assertCount(1, $mailable->bcc);
    }

    public function testCanChainMethods(): void
    {
        $result = $this->manager
            ->to('test@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->send(new SimpleMailable());

        $this->assertTrue($result);
    }

    protected function setUp(): void
    {
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

class SimpleMailable extends Mailable
{
    public function build(): self
    {
        return $this->subject('Simple Test');
    }

    public function render(): string
    {
        return '<p>Simple email</p>';
    }
}