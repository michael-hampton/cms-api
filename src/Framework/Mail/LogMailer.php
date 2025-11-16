<?php

namespace App\Framework\Mail;

use App\Framework\Support\Logger;

class LogMailer implements MailerInterface
{
    public function send(string $to, string $subject, string $body, array $options = []): bool
    {
        $logPath = __DIR__ . '/../../storage/logs/mail.log';
        $logDir = dirname($logPath);

        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $separator = str_repeat('=', 80);
        $timestamp = date('Y-m-d H:i:s');

        $logContent = <<<LOG

{$separator}
MAIL LOG - {$timestamp}
{$separator}
To: {$to}
Subject: {$subject}
From: {$options['from_name']} <{$options['from']}>

LOG;

        if (!empty($options['cc'])) {
            $cc = array_map(fn($r) => $r['address'], $options['cc']);
            $logContent .= "CC: " . implode(', ', $cc) . "\n";
        }

        if (!empty($options['bcc'])) {
            $bcc = array_map(fn($r) => $r['address'], $options['bcc']);
            $logContent .= "BCC: " . implode(', ', $bcc) . "\n";
        }

        if (!empty($options['attachments'])) {
            $logContent .= "\nAttachments:\n";
            foreach ($options['attachments'] as $attachment) {
                $name = $attachment['name'] ?? basename($attachment['path'] ?? 'unknown');
                $logContent .= "  - {$name}\n";
            }
        }

        $logContent .= "\n{$separator}\nBODY:\n{$separator}\n";
        $logContent .= $body . "\n";
        $logContent .= "{$separator}\n\n";

        file_put_contents($logPath, $logContent, FILE_APPEND);

        Logger::info("Email logged", ['to' => $to, 'subject' => $subject]);

        return true;
    }
}