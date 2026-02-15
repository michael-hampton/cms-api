<?php

namespace App\Services\Auth;

use App\DTO\Auth\CheckoutIdentityResult;

/**
 * CheckoutIdentityService
 *
 * Orchestrates checkout identity resolution
 * - Decides which flow applies
 * - Triggers OTP when needed
 * - Resolves member
 * - Returns state, not redirect
 */
class CheckoutIdentityService
{
    public function __construct(
        private readonly MemberResolver     $memberResolver,
        private readonly GuestMemberService $guestMemberService,
        private readonly OTPService         $otpService
    )
    {
    }

    /**
     * Resolve identity by email
     *
     * Returns state indicating which flow to use
     */
    public function resolveIdentity(string $email, string $sessionId, int $siteId): CheckoutIdentityResult
    {
        // Check if member exists
        $member = $this->memberResolver->resolveByEmail($email, $siteId);

        if ($member) {
            // Existing member - trigger OTP
            $result = $this->otpService->generateAndSend($email, $sessionId, $siteId);

            if (!$result['success']) {
                throw new \RuntimeException($result['message']);
            }

            return new CheckoutIdentityResult(
                type: CheckoutIdentityResult::TYPE_OTP_REQUIRED,
                userId: $member->id,
                message: 'Verification code sent to your email',
                expiresIn: $result['expires_in']
            );
        }

        // New email - anonymous flow
        return new CheckoutIdentityResult(
            type: CheckoutIdentityResult::TYPE_ANONYMOUS_CREATED,
            message: 'Proceed with checkout'
        );
    }

    /**
     * Verify OTP and authenticate
     */
    public function verifyOTP(string $email, string $otp, string $sessionId, int $siteId): CheckoutIdentityResult
    {
        $result = $this->otpService->verify($email, $otp, $sessionId, $siteId);

        if (!$result['success']) {
            throw new \RuntimeException($result['message']);
        }

        // Get member
        $member = $this->memberResolver->resolveByEmail($email, $siteId);

        if (!$member) {
            throw new \RuntimeException('Member not found');
        }

        return new CheckoutIdentityResult(
            type: CheckoutIdentityResult::TYPE_AUTHENTICATED,
            userId: $member->id,
            message: 'Authentication successful'
        );
    }

    /**
     * Create anonymous member
     */
    public function createAnonymous(string $email, int $siteId, array $data = []): CheckoutIdentityResult
    {
        try {
            $member = $this->guestMemberService->createAnonymousMember($email, $siteId, $data);

            return new CheckoutIdentityResult(
                type: CheckoutIdentityResult::TYPE_ANONYMOUS_CREATED,
                userId: $member->id,
                message: 'Anonymous member created'
            );
        } catch (\RuntimeException $e) {
            throw new \RuntimeException('Failed to create anonymous member: ' . $e->getMessage());
        }
    }

    /**
     * Resend OTP
     */
    public function resendOTP(string $email, string $sessionId, int $siteId): array
    {
        return $this->otpService->resend($email, $sessionId, $siteId);
    }
}