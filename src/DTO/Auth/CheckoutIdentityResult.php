<?php

namespace App\DTO\Auth;

/**
 * CheckoutIdentityResult
 *
 * Result object for checkout identity resolution
 * Returns state, not redirect
 */
class CheckoutIdentityResult
{
    public const TYPE_ANONYMOUS_CREATED = 'anonymous_created';
    public const TYPE_OTP_REQUIRED = 'otp_required';
    public const TYPE_AUTHENTICATED = 'authenticated';

    public function __construct(
        public readonly string  $type,
        public readonly ?int    $userId = null,
        public readonly ?string $message = null,
        public readonly ?int    $expiresIn = null
    )
    {
    }

    public function isAnonymous(): bool
    {
        return $this->type === self::TYPE_ANONYMOUS_CREATED;
    }

    public function requiresOTP(): bool
    {
        return $this->type === self::TYPE_OTP_REQUIRED;
    }

    public function isAuthenticated(): bool
    {
        return $this->type === self::TYPE_AUTHENTICATED;
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'user_id' => $this->userId,
            'message' => $this->message,
            'expires_in' => $this->expiresIn,
        ];
    }
}