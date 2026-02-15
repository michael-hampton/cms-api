<?php

namespace App\Services\Auth;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;

/**
 * MemberResolver
 *
 * Responsibility: Check if email exists and return member if so
 */
class MemberResolver
{
    public function __construct(
        private readonly MemberRepository $memberRepository
    )
    {
    }

    /**
     * Resolve member by email
     *
     * @param string $email
     * @param int|null $siteId
     * @return Member|null Returns member if exists, null otherwise
     */
    public function resolveByEmail(string $email, ?int $siteId = null): ?Member
    {
        // Normalize email (trim, lowercase)
        $email = $this->normalizeEmail($email);

        return $this->memberRepository->findByEmail($email, $siteId);
    }

    /**
     * Normalize email (trim and lowercase)
     *
     * @param string $email
     * @return string
     */
    private function normalizeEmail(string $email): string
    {
        return trim(strtolower($email));
    }

    /**
     * Check if email exists
     *
     * @param string $email
     * @param int|null $siteId
     * @return bool
     */
    public function emailExists(string $email, ?int $siteId = null): bool
    {
        $email = $this->normalizeEmail($email);
        $member = $this->memberRepository->findByEmail($email, $siteId);

        return !empty($member);
    }
}