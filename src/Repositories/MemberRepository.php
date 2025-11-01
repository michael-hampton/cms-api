<?php

namespace App\Repositories;

use App\Framework\Support\Collection;
use App\Models\Member;

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

    public function findByEmail(string $email): ?Member
    {
        return $this->where('email', $email)->first();
    }
}