<?php

namespace App\Framework\Mail;

use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use Exception;

class SMTPMailer implements MailerInterface
{
    private $config;

    public function __construct(array $config = [])
    {
        $this->config = !empty($config) ? $config : Config::get('mail');
    }

    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $from = $options['from'] ?? $this->config['from']['address'];
        $fromName = $options['from_name'] ?? $this->config['from']['name'];

        $headers = [
            "From: {$fromName} <{$from}>",
            "Reply-To: {$from}",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8"
        ];

        try {
            $result = mail($to, $subject, $body, implode("\r\n", $headers));

            if ($result) {
                Logger::info("Email sent successfully", ['to' => $to, 'subject' => $subject]);
            } else {
                Logger::error("Failed to send email", ['to' => $to, 'subject' => $subject]);
            }

            return $result;
        } catch (Exception $e) {
            Logger::error("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
}