<?php

namespace App\Services\Auth;

use App\Repositories\Auth\OTPRepository;
use App\Services\Members\EmailService;

/**
 * OTPService
 *
 * Responsibility: Generate, send, verify OTPs; enforce TTL, retries, resend limits
 */
class OTPService
{
    private const OTP_LENGTH = 6;
    private const TTL_MINUTES = 5;
    private const MAX_ATTEMPTS = 5;
    private const MAX_RESENDS = 5;
    private const RESEND_COOLDOWN_SECONDS = 300; // 5 minutes

    public function __construct(
        private readonly OTPRepository $otpRepository,
        private readonly EmailService  $emailService
    )
    {
    }

    /**
     * Verify OTP
     *
     * @param string $email
     * @param string $otp
     * @param string $sessionId
     * @param int $siteId
     * @return array ['success' => bool, 'message' => string]
     */
    public function verify(string $email, string $otp, string $sessionId, int $siteId): array
    {
        // Normalize email
        $email = trim(strtolower($email));

        // Find active OTP
        $otpVerification = $this->otpRepository->findActiveOTP($email, $sessionId, $siteId);

        if (!$otpVerification) {
            return [
                'success' => false,
                'message' => 'Invalid or expired code'
            ];
        }

        // CRITICAL: Check if OTP is usable (not expired AND not verified)
        if (!$otpVerification->isUsable()) {
            return [
                'success' => false,
                'message' => 'Invalid or expired code',
                'can_resend' => true
            ];
        }

        // Check max attempts
        if ($otpVerification->hasMaxAttemptsReached()) {
            return [
                'success' => false,
                'message' => 'Too many attempts. Please request a new code.'
            ];
        }

        // Verify OTP using timing-safe comparison
        $isValid = $otpVerification->matchesOtp($otp);

        if (!$isValid) {
            // Increment attempts (guards against expired/verified internally)
            $otpVerification->incrementAttempts();

            $remainingAttempts = self::MAX_ATTEMPTS - $otpVerification->attempts;

            return [
                'success' => false,
                'message' => 'Invalid or expired code',
                'remaining_attempts' => $remainingAttempts
            ];
        }

        // Mark as verified
        $otpVerification->markAsVerified();

        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }

    /**
     * Resend OTP
     *
     * Generates new OTP and invalidates old one
     *
     * @param string $email
     * @param string $sessionId
     * @param int $siteId
     * @return array ['success' => bool, 'message' => string]
     */
    public function resend(string $email, string $sessionId, int $siteId): array
    {
        // Normalize email
        $email = trim(strtolower($email));

        // Find active OTP
        $otpVerification = $this->otpRepository->findActiveOTP($email, $sessionId, $siteId);

        if (!$otpVerification) {
            // No active OTP, generate new one
            return $this->generateAndSend($email, $sessionId, $siteId);
        }

        // Check max resends
        if ($otpVerification->hasMaxResendsReached()) {
            return [
                'success' => false,
                'message' => 'Maximum resend limit reached. Please try again later.'
            ];
        }

        // Check cooldown
        if (!$otpVerification->canResend()) {
            $lastResend = new \DateTimeImmutable($otpVerification->last_resend_at);
            $now = now_datetime();
            $elapsed = $now->getTimestamp() - $lastResend->getTimestamp();
            $remaining = self::RESEND_COOLDOWN_SECONDS - $elapsed;

            return [
                'success' => false,
                'message' => "Please wait {$remaining} seconds before requesting a new code."
            ];
        }

        // Increment resend count
        $otpVerification->incrementResendCount();

        // Generate and send new OTP (invalidates old one)
        return $this->generateAndSend($email, $sessionId, $siteId);
    }

    /**
     * Generate and send OTP
     *
     * @param string $email
     * @param string $sessionId
     * @param int $siteId
     * @return array ['success' => bool, 'message' => string]
     */
    public function generateAndSend(string $email, string $sessionId, int $siteId): array
    {
        // Normalize email
        $email = trim(strtolower($email));

        // Check rate limiting (max 10 OTPs per hour per email)
        if ($this->isRateLimited($email, $siteId)) {
            return [
                'success' => false,
                'message' => 'Too many OTP requests. Please try again later.'
            ];
        }

        // Invalidate any existing OTPs for this session
        $this->otpRepository->invalidateOTPs($email, $sessionId, $siteId);

        // Generate OTP
        $otp = $this->generateOTP();
        $hashedOTP = $this->hashOTP($otp);

        // Calculate expiration
        $expiresAt = now_datetime()->modify('+' . self::TTL_MINUTES . ' minutes');

        // Store OTP
        $this->otpRepository->createOTP(
            $email,
            $hashedOTP,
            $sessionId,
            $siteId,
            $expiresAt
        );

        // Send email
        $emailSent = $this->sendOTPEmail($email, $otp);

        if (!$emailSent) {
            return [
                'success' => false,
                'message' => 'Failed to send OTP email. Please try again.'
            ];
        }

        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in' => self::TTL_MINUTES * 60, // seconds
        ];
    }

    /**
     * Check if email is rate limited
     */
    private function isRateLimited(string $email, int $siteId): bool
    {
        $count = $this->otpRepository->getRecentOTPCount($email, $siteId, 60);
        return $count >= 10; // Max 10 OTPs per hour
    }

    /**
     * Generate 6-digit OTP
     */
    private function generateOTP(): string
    {
        return str_pad((string)random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }

    /**
     * Hash OTP for storage (SHA256)
     * OTP is NEVER stored in plain text
     */
    private function hashOTP(string $otp): string
    {
        return hash('sha256', $otp);
    }

    /**
     * Send OTP via email
     * OTP is NEVER logged
     */
    private function sendOTPEmail(string $email, string $otp): bool
    {
        try {
            $subject = 'Your verification code';
            $message = "Your verification code is: {$otp}\n\nThis code will expire in " . self::TTL_MINUTES . " minutes.";

            return $this->emailService->send($email, $subject, $message);
        } catch (\Exception $e) {
            // Log error but don't expose OTP
            error_log("Failed to send OTP email to: {$email}");
            return false;
        }
    }
}