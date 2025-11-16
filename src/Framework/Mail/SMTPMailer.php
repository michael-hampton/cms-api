<?php

namespace App\Framework\Mail;

use App\Framework\Support\Config;
use App\Framework\Support\Logger;
use Exception;

class SMTPMailer implements MailerInterface
{
    private array $config;

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
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8"
        ];

        // Add Reply-To
        if (!empty($options['reply_to'])) {
            $replyTo = $options['reply_to'][0];
            $headers[] = "Reply-To: {$replyTo['address']}";
        } else {
            $headers[] = "Reply-To: {$from}";
        }

        // Add CC
        if (!empty($options['cc'])) {
            $ccAddresses = array_map(fn($r) => $r['address'], $options['cc']);
            $headers[] = "CC: " . implode(', ', $ccAddresses);
        }

        // Add BCC
        if (!empty($options['bcc'])) {
            $bccAddresses = array_map(fn($r) => $r['address'], $options['bcc']);
            $headers[] = "BCC: " . implode(', ', $bccAddresses);
        }

        try {
            // Handle attachments if present
            if (!empty($options['attachments'])) {
                return $this->sendWithAttachments($to, $subject, $body, $headers, $options['attachments']);
            }

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

    private function sendWithAttachments(string $to, string $subject, string $body, array $headers, array $attachments): bool
    {
        $boundary = md5(time());

        // Modify headers for multipart
        $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

        // Build message body
        $message = "--{$boundary}\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
        $message .= $body . "\r\n\r\n";

        // Add attachments
        foreach ($attachments as $attachment) {
            if (isset($attachment['data'])) {
                // Data attachment
                $data = chunk_split(base64_encode($attachment['data']));
                $name = $attachment['name'];
                $mime = $attachment['mime'] ?? 'application/octet-stream';
            } else {
                // File attachment
                $path = $attachment['path'];
                if (!file_exists($path)) {
                    continue;
                }

                $data = chunk_split(base64_encode(file_get_contents($path)));
                $name = $attachment['as'] ?? basename($path);
                $mime = $attachment['mime'] ?? mime_content_type($path);
            }

            $message .= "--{$boundary}\r\n";
            $message .= "Content-Type: {$mime}; name=\"{$name}\"\r\n";
            $message .= "Content-Transfer-Encoding: base64\r\n";
            $message .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $message .= $data . "\r\n\r\n";
        }

        $message .= "--{$boundary}--";

        $result = mail($to, $subject, $message, implode("\r\n", $headers));

        if ($result) {
            Logger::info("Email with attachments sent successfully", ['to' => $to, 'subject' => $subject]);
        } else {
            Logger::error("Failed to send email with attachments", ['to' => $to, 'subject' => $subject]);
        }

        return $result;
    }
}