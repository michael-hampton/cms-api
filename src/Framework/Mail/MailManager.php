<?php

namespace App\Framework\Mail;

use App\Framework\Support\Config;
use App\Framework\Support\Logger;

class MailManager
{
    private static ?MailManager $instance = null;
    private array $config;
    private MailerInterface $mailer;

    private function __construct()
    {
        $this->config = Config::get('mail');
        $this->mailer = $this->createMailer();
    }

    private function createMailer(): MailerInterface
    {
        $driver = $this->config['driver'] ?? 'smtp';

        return match ($driver) {
            'log' => new LogMailer(),
            'array' => new ArrayMailer(),
            'smtp' => new SMTPMailer($this->config),
            default => new SMTPMailer($this->config)
        };
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function send(Mailable $mailable): bool
    {
        // Build the mailable
        $mailable->build();

        // Get the first recipient
        $to = !empty($mailable->to) ? $mailable->to[0]['address'] : null;

        if (!$to) {
            Logger::error('No recipient specified for email');
            return false;
        }

        // Render the email body
        $body = $mailable->render();

        // Prepare options
        $options = [
            'from' => $mailable->from,
            'from_name' => $mailable->fromName,
            'cc' => $mailable->cc,
            'bcc' => $mailable->bcc,
            'reply_to' => $mailable->replyTo,
            'attachments' => $mailable->attachments,
        ];

        return $this->mailer->send($to, $mailable->subject, $body, $options);
    }

    public function to(string|array $address): PendingMail
    {
        return new PendingMail($this, $address);
    }

    public function getMailer(): MailerInterface
    {
        return $this->mailer;
    }
}