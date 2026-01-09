<?php

namespace App\Framework\Authorization;

class AuthenticationResponse
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $tokenType,
        public readonly int $userId,
        public readonly string $userName,
        public readonly string $userEmail,
        public readonly int    $siteId,
        public readonly string $role
    ) {}

    public function toArray(): array
    {
        return [
            'access_token' => $this->accessToken,
            'token_type' => $this->tokenType,
            'user' => [
                'id' => $this->userId,
                'name' => $this->userName,
                'email' => $this->userEmail,
                'site_id' => $this->siteId,
                'role' => $this->role
            ],
        ];
    }
}