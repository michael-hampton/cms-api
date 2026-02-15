<?php

namespace App\Models;

use DateTimeImmutable;

class OTPVerification extends Model
{
    protected $table = 'otp_verifications';

    protected $fillable = [
        'email',
        'otp',
        'site_id',
        'session_id',
        'expires_at',
        'attempts',
        'resend_count',
        'last_resend_at',
        'verified',
        'verified_at',
    ];

    protected $casts = [
        'site_id' => 'integer',
        'attempts' => 'integer',
        'resend_count' => 'integer',
        'verified' => 'boolean',
    ];

    /**
     * Check if OTP is usable (not expired AND not verified)
     * Once expired OR verified → OTP is dead forever
     */
    public function isUsable(): bool
    {
        return !$this->verified && !$this->isExpired();
    }

    /**
     * Check if OTP has expired
     */
    public function isExpired(): bool
    {
        $expiresAt = new DateTimeImmutable($this->expires_at);
        $now = now_datetime();

        return $now > $expiresAt;
    }

    /**
     * Check if max attempts reached
     */
    public function hasMaxAttemptsReached(): bool
    {
        return $this->attempts >= 5;
    }

    /**
     * Check if max resends reached
     */
    public function hasMaxResendsReached(): bool
    {
        return $this->resend_count >= 5;
    }

    /**
     * Check if can resend (5 minute cooldown)
     */
    public function canResend(): bool
    {
        if (!$this->last_resend_at) {
            return true;
        }

        $lastResend = new DateTimeImmutable($this->last_resend_at);
        $now = now_datetime();
        $diff = $now->getTimestamp() - $lastResend->getTimestamp();

        return $diff >= 300; // 5 minutes = 300 seconds
    }

    /**
     * Verify OTP matches input using timing-safe comparison
     * OTP is stored as SHA256 hash
     */
    public function matchesOtp(string $input): bool
    {
        return hash_equals($this->otp, hash('sha256', $input));
    }

    /**
     * Increment verification attempts
     * Only increments if OTP is still usable (not expired, not verified)
     */
    public function incrementAttempts(): void
    {
        if ($this->isExpired() || $this->verified) {
            return;
        }

        $this->attempts++;
        $this->save();
    }

    /**
     * Increment resend count
     */
    public function incrementResendCount(): void
    {
        $this->resend_count++;
        $this->last_resend_at = now_datetime()->format('Y-m-d H:i:s');
        $this->save();
    }

    /**
     * Mark as verified
     */
    public function markAsVerified(): void
    {
        $this->verified = true;
        $this->verified_at = now_datetime()->format('Y-m-d H:i:s');
        $this->save();
    }
}