<?php

namespace App\Framework\Authorization;

use App\Framework\Authorization\Exceptions\InactiveUserException;
use App\Framework\Authorization\Exceptions\InvalidCredentialsException;
use App\Framework\Support\SiteContext;
use App\Models\Member;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;

class AuthenticationService
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
        private EloquentTokenRepository $tokenRepository,
        private SecureTokenGenerator $tokenGenerator
    ) {}

    public function login(LoginRequest $request): AuthenticationResponse
    {
        $user = $this->userRepository->findByEmail(
            $request->email,
            null
        );

        if (!$user || !$user->verifyPassword($request->password)) {
            throw new InvalidCredentialsException('Invalid email or password');
        }

        if (!$user->isActive()) {
            throw new InactiveUserException('User account is inactive');
        }

        // Revoke existing tokens (single session)
        $this->tokenRepository->revokeUserTokens($user->id, $request->siteId);

        // Create new token
        $plainTextToken = $this->tokenGenerator->generate();

        $token = new PersonalAccessToken(
            User::class,
            $user->id,
            SiteContext::getId(),
            'auth_token',
            $plainTextToken,
            ['*']
        );

        $savedToken = $this->tokenRepository->create($token);

        return new AuthenticationResponse(
            accessToken: $plainTextToken,
            tokenType: 'bearer',
            userId: $user->id,
            userName: $user->name,
            userEmail: $user->email,
            siteId: SiteContext::getId(),
            role: $user->role
        );
    }

    public function logout(string $token, int $siteId): void
    {
        $accessToken = $this->tokenRepository->findByToken($token, $siteId);

        if ($accessToken) {
            $this->tokenRepository->revokeTokensFor(
                $accessToken->getTokenableType(),
                $accessToken->getTokenableId(),
                $siteId
            );
        }
    }

    public function validateAccessToken(string $token, int $siteId): ?PersonalAccessToken
    {
        $accessToken = $this->tokenRepository->findByToken($token, $siteId);

        if (!$accessToken || $accessToken->isExpired()) {
            return null;
        }

        $this->tokenRepository->updateLastUsed($accessToken->getId());

        return $accessToken;
    }

    public function validateToken(string $token, int $siteId): ?int
    {
        $accessToken = $this->validateAccessToken($token, $siteId);

        if (!$accessToken) {
            return null;
        }

        return $accessToken->getTokenableId();
    }

    public function createMemberToken(Member $member, int $siteId): string
    {
        $this->tokenRepository->revokeTokensFor(Member::class, $member->id, $siteId);

        $plainTextToken = $this->tokenGenerator->generate();

        $token = new PersonalAccessToken(
            Member::class,
            $member->id,
            $siteId,
            'auth_token',
            $plainTextToken,
            ['*']
        );

        $this->tokenRepository->create($token);

        return $plainTextToken;
    }

    public function createToken(User $user, int $siteId): string
    {
        //$token = $this->tokenRepository->getTokenForUser(User::class, $user->id, $siteId);

        //$this->tokenRepository->revokeTokensFor(User::class, $user->id, $siteId);

        $plainTextToken = $this->tokenGenerator->generate();

        $token = new PersonalAccessToken(
            User::class,
            $user->id,
            $siteId,
            'auth_token',
            $plainTextToken,
            ['*']
        );

        $this->tokenRepository->create($token);

        return $plainTextToken;
    }

    public function getUserId(): ?int
    {
        // Use the framework's global helper to get the authenticated user's ID
        // The ?? null ensures it's always an int or null
        return auth()->id() ?? null;
    }

    public function check(): bool
    {
        return auth()->check();
    }
}
