<?php

namespace App\Framework\Mail;

class ArrayMailer implements MailerInterface
{
    private static array $emails = [];

    public static function getEmails(): array
    {
        return self::$emails;
    }

    public static function clear(): void
    {
        self::$emails = [];
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        self::$emails[] = [
            'to' => $to,
            'subject' => $subject,
            'body' => $body,
            'options' => $options,
            'sent_at' => date('Y-m-d H:i:s')
        ];

        return true;
    }
}