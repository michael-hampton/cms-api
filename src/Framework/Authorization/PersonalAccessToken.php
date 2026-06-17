<?php

namespace App\Framework\Authorization;

use DateTime;

class PersonalAccessToken
{
    private ?int $id;
    private string $tokenableType;
    private int $tokenableId;
    private int $siteId;
    private string $name;
    private string $token;
    private ?array $abilities;
    private ?DateTime $lastUsedAt;
    private ?DateTime $expiresAt;
    private DateTime $createdAt;
    private DateTime $updatedAt;

    public function __construct(
        string $tokenableType,
        int $tokenableId,
        int $siteId,
        string $name,
        string $token,
        ?array $abilities = null,
        ?DateTime $expiresAt = null,
        ?int $id = null
    ) {
        $this->id = $id;
        $this->tokenableType = $tokenableType;
        $this->tokenableId = $tokenableId;
        $this->siteId = $siteId;
        $this->name = $name;
        $this->token = $token;
        $this->abilities = $abilities;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new DateTime();
        $this->updatedAt = new DateTime();
    }

    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new DateTime();
    }

    public function can(string $ability): bool
    {
        return $this->abilities === null
            || in_array('*', $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }

    public function markAsUsed(): void
    {
        $this->lastUsedAt = new DateTime();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getToken(): string { return $this->token; }
    public function getSiteId(): int { return $this->siteId; }
    public function getTokenableId(): int { return $this->tokenableId; }
    public function getTokenableType(): string { return $this->tokenableType; }
    public function getAbilities(): ?array { return $this->abilities; }
    public function getExpiresAt(): ?DateTime { return $this->expiresAt; }
}
