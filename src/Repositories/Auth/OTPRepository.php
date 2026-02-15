<?php

namespace App\Repositories\Auth;

use App\Models\Model;
use App\Models\OTPVerification;
use App\Repositories\Repository;
use DateTimeImmutable;

class OTPRepository extends Repository
{
    /**
     * Find active OTP by email, session, and site
     */
    public function findActiveOTP(string $email, string $sessionId, int $siteId): ?OTPVerification
    {
        return $this->model::where('email', $email)
            ->where('session_id', $sessionId)
            ->where('site_id', $siteId)
            ->where('verified', false)
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Create new OTP record
     */
    public function createOTP(
        string            $email,
        string            $hashedOTP,
        string            $sessionId,
        int               $siteId,
        DateTimeImmutable $expiresAt
    ): Model
    {
        return $this->create([
            'email' => $email,
            'otp' => $hashedOTP,
            'session_id' => $sessionId,
            'site_id' => $siteId,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'attempts' => 0,
            'resend_count' => 0,
            'verified' => false,
        ]);
    }

    /**
     * Invalidate all OTPs for email/session
     */
    public function invalidateOTPs(string $email, string $sessionId, int $siteId): ?int
    {
        return $this->model::where('email', $email)
            ->where('session_id', $sessionId)
            ->where('site_id', $siteId)
            ->where('verified', false)
            ->update(['verified' => true]); // Mark as verified to invalidate
    }

    /**
     * Clean up expired OTPs (can be run as a scheduled task)
     */
    public function deleteExpiredOTPs(): int
    {
        $now = now_datetime()->format('Y-m-d H:i:s');

        return $this->model::where('expires_at', '<', $now)
            ->where('verified', false)
            ->delete();
    }

    /**
     * Get OTP count for rate limiting
     */
    public function getRecentOTPCount(string $email, int $siteId, int $minutes = 60): int
    {
        $since = now_datetime()->modify("-{$minutes} minutes")->format('Y-m-d H:i:s');

        return $this->model::where('email', $email)
            ->where('site_id', $siteId)
            ->where('created_at', '>=', $since)
            ->count();
    }

    protected function getModelClass(): string
    {
        return OTPVerification::class;
    }
}