<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Models\Member;

class EmailVerificationService
{
    public function generateVerificationToken(Member $member): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $member = Member::where('id', $member->id)->first();

        $member->update([
            'email_verification_token' => hash('sha256', $token),
            'email_verification_expires_at' => $expiresAt
        ]);

        return $token;
    }

    public function sendVerificationEmail(Member $member, string $token): void
    {
        $site = SiteContext::get();
        $verificationUrl = $this->buildVerificationUrl($token);
        $name = $site->name ?? 'Your Website';

        $subject = "Verify your email for {$name}";
        $body = $this->buildVerificationEmailBody($member, $verificationUrl, $site);

        // Use your email service
        mail($member->email, $subject, $body, $this->getEmailHeaders());
    }

    public function verify(string $token, ?int $siteId = null): bool
    {
        $siteId = $siteId ?? SiteContext::getId();

        $hashedToken = hash('sha256', $token);

        $member = Member::findByVerificationToken($hashedToken, $siteId);

        if (!$member) {
            return false;
        }

        $member = Member::where('id', $member->id)->first();

        $member->update([
            'email_verified_at' => date('Y-m-d H:i:s'),
            'email_verification_token' => null,
            'email_verification_expires_at' => null,
            'is_active' => true
        ]);

        return true;
    }

    private function buildVerificationUrl(string $token): string
    {
        $site = SiteContext::get();
        $baseUrl = rtrim($site->url ?? 'http://localhost', '/');
        return "{$baseUrl}/verify-email?token={$token}";
    }

    private function buildVerificationEmailBody(Member $member, string $url, $site): string
    {
        return <<<HTML
        <html>
        <body>
            <h2>Welcome to {$site->name}!</h2>
            <p>Hello {$member->first_name},</p>
            <p>Please click the link below to verify your email address:</p>
            <p><a href="{$url}">Verify Email Address</a></p>
            <p>This link will expire in 24 hours.</p>
            <p>If you didn't create an account, please ignore this email.</p>
        </body>
        </html>
        HTML;
    }

    private function getEmailHeaders(): string
    {
        $site = SiteContext::get();

        if (empty($site)) {
            return '';
        }

        $fromEmail = $site->email ?? 'noreply@example.com';

        return "From: {$site->name} <{$fromEmail}>\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n";
    }
}