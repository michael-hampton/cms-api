<?php

namespace App\DTO\Consents;

class MemberConsentSnapshot
{
    public function __construct(
        public readonly int        $consentTypeId,
        public readonly string     $code,
        public readonly string     $name,
        public readonly string     $description,
        public readonly string     $category,
        public readonly bool       $required,
        public readonly ?int       $retentionDays,
        public readonly bool       $isGranted,
        public readonly ?\DateTime $grantedAt,
        public readonly ?\DateTime $expiresAt,
        public readonly ?string    $channel
    )
    {
    }

    public function toArray(): array
    {
        return [
            'consent_type' => [
                'id' => $this->consentTypeId,
                'code' => $this->code,
                'name' => $this->name,
                'description' => $this->description,
                'category' => $this->category,
                'required' => $this->required,
                'retention_days' => $this->retentionDays
            ],
            'is_granted' => $this->isGranted,
            'granted_at' => $this->grantedAt,
            'expires_at' => $this->expiresAt,
            'channel' => $this->channel
        ];
    }
}