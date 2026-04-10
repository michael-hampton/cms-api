<?php

namespace App\Resources\OpenCollab;

use App\Framework\Resource\JsonResource;

class InvitationResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'email' => $this->getAttribute('email'),
            'status' => $this->resolveStatus(),
            'expires_at' => $this->getAttribute('expires_at'),
            'used_at' => $this->getAttribute('used_at'),
            'revoked_at' => $this->getAttribute('revoked_at'),
            'created_at' => $this->getAttribute('created_at'),
        ];
    }

    private function resolveStatus(): string
    {
        if ($this->getAttribute('used_at')) {
            return 'used';
        }

        if ($this->getAttribute('revoked_at')) {
            return 'revoked';
        }

        $expiresAt = $this->getAttribute('expires_at');
        if ($expiresAt && strtotime($expiresAt) < time()) {
            return 'expired';
        }

        return 'pending';
    }
}