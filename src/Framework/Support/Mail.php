<?php

namespace App\Framework\Support;

use App\Framework\Mail\MailerInterface;
use App\Framework\Mail\SMTPMailer;

class Mail
{
    private static $mailer;

    public static function setMailer(MailerInterface $mailer): void
    {
        self::$mailer = $mailer;
    }

    public static function send(string $to, string $subject, string $body, array $options = []): bool
    {
        if (!self::$mailer) {
            self::$mailer = new SMTPMailer();
        }

        return self::$mailer->send($to, $subject, $body, $options);
    }
}