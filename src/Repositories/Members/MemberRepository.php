<?php

namespace App\Repositories\Members;

use App\Framework\Database\Exceptions\UniqueConstraintViolationException;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Repositories\Repository;

class MemberRepository extends Repository
{

    protected function getModelClass(): string
    {
        return Member::class;
    }

    /**
     * Search members by email, first name, last name, or display name
     */
    public function searchMembers(string $search = '', int $perPage = 10, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        $query = $this->model::where('site_id', $siteId)
            ->where('is_active', true);

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('email', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('display_name', 'LIKE', "%{$search}%");
            });
        }

        return $query->select([
            'id',
            'email',
            'first_name',
            'last_name',
            'display_name',
            'email_verified_at'
        ])
            ->orderBy('first_name')
            ->orderBy('last_name')
            ->limit($perPage)
            ->get()
            ->map(function($member) {
                return [
                    'id' => $member->id,
                    'email' => $member->email,
                    'first_name' => $member->first_name,
                    'last_name' => $member->last_name,
                    'display_name' => $member->display_name,
                    'full_name' => $member->full_name,
                    'is_verified' => $member->isEmailVerified()
                ];
            });
    }

    /**
     * Get active members
     */
    public function getActiveMembers(?int $limit = null, ?int $siteId = null): Collection
    {
        $siteId = $siteId ?? $this->siteId;

        $query = $this->model::where('site_id', $siteId)
            ->where('is_active', true)
            ->orderBy('first_name')
            ->orderBy('last_name');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    public function findByEmail(string $email, ?int $siteId = null): ?Member
    {
        $email = trim(strtolower($email));

        $query = $this->model::whereRaw(
            'LOWER(TRIM(email)) = :email',
            ['email' => $email]
        );

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->first();
    }


    /**
     * Update member account details
     * Handles email uniqueness validation and email verification reset
     */
    public function updateAccountDetails(int $memberId, array $data): ?Member
    {
        $member = $this->find($memberId);

        if (!$member) {
            return null;
        }

        // Check if email is changing and if it's unique
        if (isset($data['email']) && $data['email'] !== $member->email) {
            $existing = $this->where('email', $data['email'])
                ->where('id', '!=', $memberId)
                ->first();

            if ($existing) {
                throw new \InvalidArgumentException('Email address is already in use.');
            }

            // Reset email verification when email changes
            $data['email_verified_at'] = null;
        }

        // Only allow updating specific fields
        $allowedFields = ['first_name', 'last_name', 'display_name', 'email', 'email_verified_at'];
        $updateData = array_intersect_key($data, array_flip($allowedFields));

        return $this->update($memberId, $updateData);
    }

    /**
     * Check if email is available for a member (excluding their own)
     */
    public function isEmailAvailable(string $email, ?int $excludeMemberId = null): bool
    {
        $query = $this->where('email', $email);

        if ($excludeMemberId) {
            $query->where('id', '!=', $excludeMemberId);
        }

        return $query->first() === null;
    }

    /**
     * Find multiple members by their email addresses
     *
     * @param array $emails Array of email addresses
     * @param int|null $siteId Optional site ID filter
     * @return Collection Collection of Member models
     */
    public function findByEmails(array $emails, ?int $siteId = null): Collection
    {
        $query = Member::whereIn('email', $emails);

        if ($siteId !== null) {
            $query->where('site_id', $siteId);
        }

        return $query->get();
    }

    /**
     * Create anonymous member
     *
     * @throws UniqueConstraintViolationException if email already exists
     */
    public function createAnonymousMember(string $email, int $siteId, array $data = []): Model
    {
        $now = now_datetime()->format('Y-m-d H:i:s');

        return $this->create([
            'email' => trim(strtolower($email)),
            'site_id' => $siteId,
            'anonymous' => true,
            'is_active' => true,
            'email_verified_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name']
        ]);
    }

    /**
     * Convert anonymous member to full account
     */
    public function convertToFullAccount(
        int     $memberId,
        string  $firstName,
        string  $lastName,
        ?string $password = null
    ): ?Member
    {
        $member = $this->find($memberId);

        if (!$member || !$member->anonymous) {
            return null;
        }

        $now = now_datetime()->format('Y-m-d H:i:s');

        $updateData = [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'display_name' => trim("{$firstName} {$lastName}"),
            'anonymous' => false,
            'email_verified_at' => $now,
        ];

        if ($password) {
            $updateData['password'] = password_hash($password, PASSWORD_BCRYPT);
        }

        return $this->update($memberId, $updateData);
    }

    /**
     * Get anonymous members older than X days (for cleanup)
     */
    public function getOldAnonymousMembers(int $days = 90): array
    {
        $cutoffDate = now_datetime()
            ->modify("-{$days} days")
            ->format('Y-m-d H:i:s');

        return $this->model::where('anonymous', true)
            ->where('created_at', '<', $cutoffDate)
            ->get()
            ->toArray();
    }

    public function chunkActiveForSegment(
        int      $segmentId,
        int      $chunkSize,
        callable $callback
    ): void
    {
        Member::query()
            ->where('is_active', true)
            ->whereHas('segments', function ($q) use ($segmentId) {
                $q->where('segments.id', $segmentId);
            })
            ->chunkById($chunkSize, function ($members) use ($callback) {
                $callback($members->all());
            });
    }
}