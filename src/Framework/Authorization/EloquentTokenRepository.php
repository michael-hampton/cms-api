<?php

namespace App\Framework\Authorization;

use App\Framework\Database\Database;
use App\Models\Model;
use DateTime;

class EloquentTokenRepository
{
    public function create(PersonalAccessToken $token): PersonalAccessToken
    {
        $attributes = [
            'tokenable_type' => $token->getTokenableType(),
            'tokenable_id' => $token->getTokenableId(),
            'site_id' => $token->getSiteId(),
            'name' => 'auth_token',
            'token' => hash('sha256', $token->getToken()),
            'abilities' => json_encode($token->getAbilities()),
            'expires_at' => $token->getExpiresAt()?->format('Y-m-d H:i:s'),
            'created_at' => (new DateTime())->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTime())->format('Y-m-d H:i:s')
        ];

        if ($this->hasUserIdColumn() && $token->getTokenableType() === \App\Models\User::class) {
            $attributes['user_id'] = $token->getTokenableId();
        }

        $personalAccessToken = \App\Models\PersonalAccessToken::create($attributes);

        return new PersonalAccessToken(
            $token->getTokenableType(),
            $token->getTokenableId(),
            $token->getSiteId(),
            'auth_token',
            $token->getToken(),
            $token->getAbilities(),
            null,
            $personalAccessToken->id
        );
    }

    public function findByToken(string $token, ?int $siteId = null): ?PersonalAccessToken
    {
        $hashedToken = hash('sha256', $token);

        $record = \App\Models\PersonalAccessToken::where('token', $hashedToken)
            ->when(!empty($siteId), function ($query) use ($siteId) {
                $query->where('site_id', $siteId);
            })
            ->first();

        if (!$record) {
            return null;
        }

        return new PersonalAccessToken(
            $record->tokenable_type,
            $record->tokenable_id,
            $record->site_id,
            $record->name,
            $token,
            !empty($record->abilities) ? json_decode($record->abilities, true) : null,
            $record->expires_at ? new DateTime($record->expires_at) : null,
            $record->id
        );
    }

    public function revokeUserTokens(int $userId, int $siteId): void
    {
        $this->revokeTokensFor(\App\Models\User::class, $userId, $siteId);
    }

    public function revokeTokensFor(string $tokenableType, int $tokenableId, int $siteId): void
    {
        \App\Models\PersonalAccessToken::where('tokenable_type', $tokenableType)
            ->where('tokenable_id', $tokenableId)
            ->where('site_id', $siteId)
            ->delete();
    }

    public function getTokenForUser(string $tokenableType, int $tokenableId, int $siteId): Model
    {
        return \App\Models\PersonalAccessToken::where('tokenable_type', $tokenableType)
            ->where('tokenable_id', $tokenableId)
            ->where('site_id', $siteId)
            ->first();
    }

    public function updateLastUsed(int $tokenId): void
    {
        $token = \App\Models\PersonalAccessToken::find($tokenId);

        $token->update([
                'last_used_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'updated_at' => (new DateTime())->format('Y-m-d H:i:s'),
            ]);
    }

    public function deleteExpiredTokens(): bool
    {
        $tokens = \App\Models\PersonalAccessToken::where('expires_at', '<', (new DateTime())->format('Y-m-d H:i:s'))->get();

        foreach ($tokens as $token) {
            $token->delete();
        }

        return true;
    }

    private function hasUserIdColumn(): bool
    {
        static $hasUserIdColumn;

        if ($hasUserIdColumn !== null) {
            return $hasUserIdColumn;
        }

        try {
            $result = Database::getInstance()->query(
                "SHOW COLUMNS FROM `personal_access_tokens` LIKE 'user_id'"
            );

            $hasUserIdColumn = $result->rowCount() > 0;
        } catch (\Throwable) {
            $hasUserIdColumn = false;
        }

        return $hasUserIdColumn;
    }
}
