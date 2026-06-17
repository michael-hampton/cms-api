<?php

namespace App\Repositories\OpenCollab;

use App\Models\Invitation;
use App\Models\Model;

interface InvitationRepositoryInterface
{
    public function find(int $id, array $relations = []): ?Model;

    public function create(array $data): Model;

    public function hasPendingInviteForEmail(string $email, int $siteId): bool;

    public function findByToken(string $token): ?Invitation;

    public function markAsUsed(int $id, int $acceptedBy): void;

    public function revoke(int $id, int $revokedBy): void;
}
