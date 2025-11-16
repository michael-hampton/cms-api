<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\ArrayMailer;
use PHPUnit\Framework\TestCase;

class ArrayMailerTest extends TestCase
{
    private ArrayMailer $mailer;

    public function testSendStoresEmail(): void
    {
        $result = $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            ['from' => 'sender@example.com']
        );

        $this->assertTrue($result);
        $this->assertCount(1, ArrayMailer::getEmails());
    }

    public function testEmailContainsRecipient(): void
    {
        $this->mailer->send(
            'recipient@example.com',
            'Test Subject',
            'Test Body',
            ['from' => 'sender@example.com']
        );

        $emails = ArrayMailer::getEmails();
        $this->assertEquals('recipient@example.com', $emails[0]['to']);
    }

    public function testEmailContainsSubject(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Important Subject',
            'Test Body',
            ['from' => 'sender@example.com']
        );

        $emails = ArrayMailer::getEmails();
        $this->assertEquals('Important Subject', $emails[0]['subject']);
    }

    public function testEmailContainsBody(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            '<p>Test Body</p>',
            ['from' => 'sender@example.com']
        );

        $emails = ArrayMailer::getEmails();
        $this->assertEquals('<p>Test Body</p>', $emails[0]['body']);
    }

    public function testEmailContainsOptions(): void
    {
        $options = [
            'from' => 'sender@example.com',
            'from_name' => 'Sender Name',
            'cc' => ['cc@example.com']
        ];

        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            $options
        );

        $emails = ArrayMailer::getEmails();
        $this->assertEquals($options, $emails[0]['options']);
    }

    public function testMultipleEmailsAreStored(): void
    {
        $this->mailer->send('test1@example.com', 'Subject 1', 'Body 1', []);
        $this->mailer->send('test2@example.com', 'Subject 2', 'Body 2', []);
        $this->mailer->send('test3@example.com', 'Subject 3', 'Body 3', []);

        $this->assertCount(3, ArrayMailer::getEmails());
    }

    public function testClearRemovesAllEmails(): void
    {
        $this->mailer->send('test1@example.com', 'Subject', 'Body', []);
        $this->mailer->send('test2@example.com', 'Subject', 'Body', []);

        $this->assertCount(2, ArrayMailer::getEmails());

        ArrayMailer::clear();

        $this->assertCount(0, ArrayMailer::getEmails());
    }

    public function testEmailHasTimestamp(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            ['from' => 'sender@example.com']
        );

        $emails = ArrayMailer::getEmails();
        $this->assertArrayHasKey('sent_at', $emails[0]);
        $this->assertNotEmpty($emails[0]['sent_at']);
    }

    protected function setUp(): void
    {
        $this->mailer = new ArrayMailer();
        ArrayMailer::clear();
    }

    protected function tearDown(): void
    {
        ArrayMailer::clear();
    }
}