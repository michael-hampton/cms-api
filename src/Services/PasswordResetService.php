<?php

namespace App\Services;

use App\Framework\Support\SiteContext;
use App\Framework\Authorization\EloquentTokenRepository;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;

class PasswordResetService
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly ?EloquentTokenRepository $tokenRepository = null,
    )
    {

    }

    public function generateResetToken(Member $member): string
    {
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $member = $this->memberRepository->find($member->id);

        $member->update([
            'password_reset_token' => hash('sha256', $token),
            'password_reset_expires_at' => $expiresAt,
        ]);

        return $token;
    }

    public function sendResetEmail(Member $member, string $token): void
    {
        $site = SiteContext::get();
        $resetUrl = $this->buildResetUrl($token);
        $name = $site->name ?? 'Your Website';
        $subject = "Reset your password for {$name}";
        $body = $this->buildResetEmailBody($member, $resetUrl, $site);

        mail($member->email, $subject, $body, $this->getEmailHeaders());
    }

    public function validateToken(string $token, ?int $siteId = null): ?Member
    {
        $siteId = $siteId ?? SiteContext::getId();
        $hashedToken = hash('sha256', $token);

        return Member::findByPasswordResetToken($hashedToken, $siteId);
    }

    /**
     * Set a member's password and record all related metadata.
     *
     * This is the single authoritative location for:
     *   - password hashing (algorithm choice lives here and nowhere else)
     *   - writing password_set_at
     *   - invalidating the reset/activation token (single-use guarantee)
     *
     * Callers are responsible for ensuring this is called on the correct
     * member. password_set_at is always written — callers must not invoke
     * this on accounts that are already active unless they intend to
     * overwrite that timestamp.
     *
     * This method performs a single DB write. Wrap it in a transaction at
     * the calling layer if it must be atomic with other writes.
     */
    public function setPassword(Member $member, string $plainTextPassword): void
    {
        // Reload from DB to guarantee we are updating persisted state,
        // not a potentially stale in-memory object.
        $persisted = $this->memberRepository->find($member->id);

        $persisted->update([
            'password' => password_hash($plainTextPassword, PASSWORD_DEFAULT),
            'password_set_at' => date('Y-m-d H:i:s'),
            'password_reset_token' => null,
            'password_reset_expires_at' => null,
        ]);

        // Sync the in-memory object so callers hold a consistent reference
        // without needing to reload again themselves.
        $member->password = $persisted->password;
        $member->password_set_at = $persisted->password_set_at;
        $member->password_reset_token = null;
        $member->password_reset_expires_at = null;
    }

    /**
     * Validate the token and set the new password in one operation.
     *
     * Delegates to setPassword() so that hashing and persistence rules
     * remain centralised. Returns true on success, false if the token
     * is invalid or expired.
     */
    public function resetPassword(string $token, string $newPassword, ?int $siteId = null): bool
    {
        $member = $this->validateToken($token, $siteId);

        if (!$member) {
            return false;
        }

        $this->setPassword($member, $newPassword);
        ($this->tokenRepository ?? new EloquentTokenRepository())
            ->revokeTokensFor(Member::class, $member->id, $siteId ?? SiteContext::getId());

        return true;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

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
        $name = $site->name ?? 'Your Website';
        $fromEmail = $site->email ?? 'noreply@example.com';

        return "From: {$name} <{$fromEmail}>\r\n" .
            "Content-Type: text/html; charset=UTF-8\r\n";
    }
}
