<?php

namespace App\Repositories\Auth;

use App\Framework\Date;
use App\Models\Model;
use App\Models\OTPVerification;
use App\Repositories\Repository;

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
        Date $expiresAt
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
            ->where('verified', 0)
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

    public function getActiveOTP(string $email, int $siteId, string $sessionId): ?Model
    {
        return $this->model::where('email', $email)
            ->where('site_id', $siteId)
            ->where('session_id', $sessionId)
            ->where('verified', 0)
            ->where('expires_at', '>', now_datetime()->format('Y-m-d H:i:s'))
            ->first();
    }

    public function cancelOTP(string $sessionId, string $email, int $siteId)
    {
        return OTPVerification::where('verified', 0)
            ->where('email', $email)
            ->where('session_id', $sessionId)
            ->where('site_id', $siteId)
            ->update(['verified' => 1]);

    }

    protected function getModelClass(): string
    {
        return OTPVerification::class;
    }
}