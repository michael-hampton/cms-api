<?php

namespace App\Tests\Unit\Mail;

use App\Framework\Mail\LogMailer;
use PHPUnit\Framework\TestCase;

class LogMailerTest extends TestCase
{
    private LogMailer $mailer;
    private string $logPath;

    public function testSendLogsEmail(): void
    {
        $result = $this->mailer->send(
            'test@example.com',
            'Test Subject',
            '<p>Test Body</p>',
            [
                'from' => 'sender@example.com',
                'from_name' => 'Sender Name'
            ]
        );

        $this->assertTrue($result);
        $this->assertFileExists($this->logPath);
    }

    public function testLogContainsRecipient(): void
    {
        $this->mailer->send(
            'recipient@example.com',
            'Test Subject',
            'Test Body',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('To: recipient@example.com', $log);
    }

    public function testLogContainsSubject(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Important Subject',
            'Test Body',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('Subject: Important Subject', $log);
    }

    public function testLogContainsBody(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            '<p>Test email body content</p>',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('<p>Test email body content</p>', $log);
    }

    public function testLogContainsCc(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            [
                'from' => 'sender@example.com',
                'from_name' => 'Sender',
                'cc' => [
                    ['address' => 'cc1@example.com'],
                    ['address' => 'cc2@example.com']
                ]
            ]
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('CC: cc1@example.com, cc2@example.com', $log);
    }

    public function testLogContainsBcc(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            [
                'from' => 'sender@example.com',
                'from_name' => 'Sender',
                'bcc' => [
                    ['address' => 'bcc@example.com']
                ]
            ]
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('BCC: bcc@example.com', $log);
    }

    public function testLogContainsAttachments(): void
    {
        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            [
                'from' => 'sender@example.com',
                'from_name' => 'Sender',
                'attachments' => [
                    ['name' => 'invoice.pdf'],
                    ['path' => '/path/to/receipt.pdf']
                ]
            ]
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('Attachments:', $log);
        $this->assertStringContainsString('invoice.pdf', $log);
        $this->assertStringContainsString('receipt.pdf', $log);
    }

    public function testMultipleEmailsAreAppended(): void
    {
        $this->mailer->send(
            'test1@example.com',
            'First Email',
            'First Body',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $this->mailer->send(
            'test2@example.com',
            'Second Email',
            'Second Body',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $log = file_get_contents($this->logPath);
        $this->assertStringContainsString('test1@example.com', $log);
        $this->assertStringContainsString('test2@example.com', $log);
        $this->assertStringContainsString('First Email', $log);
        $this->assertStringContainsString('Second Email', $log);
    }

    public function testCreatesLogDirectoryIfNotExists(): void
    {
        $logDir = dirname($this->logPath);
        if (is_dir($logDir)) {
            rmdir($logDir);
        }

        $this->mailer->send(
            'test@example.com',
            'Test Subject',
            'Test Body',
            ['from' => 'sender@example.com', 'from_name' => 'Sender']
        );

        $this->assertDirectoryExists($logDir);
    }

    protected function setUp(): void
    {
        $this->mailer = new LogMailer();

        $this->logPath = __DIR__ . '/../../../storage/logs/mail.log';

        // Clean up log file
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }
}