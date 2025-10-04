<?php

namespace App\Framework\Mail;

interface MailerInterface
{
    public function send(string $to, string $subject, string $body, array $options = []): bool;
}