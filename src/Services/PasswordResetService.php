<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Models\Member;

class PasswordResetService
{
    public function generateResetToken(Member $member): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $member = Member::where('id', $member->id)->first();

        $member->update([
            'password_reset_token' => hash('sha256', $token),
            'password_reset_expires_at' => $expiresAt
        ]);

        return $token;
    }

    public function sendResetEmail(Member $member, string $token): void
    {
        $site = SiteContext::get();
        $resetUrl = $this->buildResetUrl($token);

        $subject = "Reset your password for {$site->name}";
        $body = $this->buildResetEmailBody($member, $resetUrl, $site);

        mail($member->email, $subject, $body, $this->getEmailHeaders());
    }

    public function validateToken(string $token, ?int $siteId = null): ?Member
    {
        $siteId = $siteId ?? SiteContext::getId();
        $hashedToken = hash('sha256', $token);

        return Member::findByPasswordResetToken($hashedToken, $siteId);
    }

    public function resetPassword(string $token, string $newPassword, ?int $siteId = null): bool
    {
        $member = $this->validateToken($token, $siteId);;

        if (!$member) {
            return false;
        }

        $member = Member::where('id', $member->id)->first();

        $member->update([
            'password' => password_hash($newPassword, PASSWORD_DEFAULT),
            'password_reset_token' => null,
            'password_reset_expires_at' => null
        ]);

        return true;
    }

    private function buildResetUrl(string $token): string
    {
        $site = SiteContext::get();
        $baseUrl = rtrim($site->url ?? 'http://localhost', '/');
        return "{$baseUrl}/reset-password?token={$token}";
    }

    private function buildResetEmailBody(Member $member, string $url, $site): string
    {
        return <<<HTML
        <html>
        <body>
            <h2>Password Reset Request</h2>
            <p>Hello {$member->first_name},</p>
            <p>You requested to reset your password. Click the link below to proceed:</p>
            <p><a href="{$url}">Reset Password</a></p>
            <p>This link will expire in 1 hour.</p>
            <p>If you didn't request this, please ignore this email.</p>
        </body>
        </html>
        HTML;
    }

    private function getEmailHeaders(): string
    {
        $site = SiteContext::get();
        $fromEmail = $site->email ?? 'noreply@example.com';

        return "From: {$site->name} <{$fromEmail}>\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n";
    }
}