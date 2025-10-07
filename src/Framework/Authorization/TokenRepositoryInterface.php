<?php

namespace App\Framework\Authorization;

interface TokenRepositoryInterface
{
    public function create(PersonalAccessToken $token): PersonalAccessToken;
    public function findByToken(string $token, int $siteId): ?PersonalAccessToken;
    public function revokeUserTokens(int $userId, int $siteId): void;
    public function updateLastUsed(int $tokenId): void;
    public function deleteExpiredTokens(): int;
}