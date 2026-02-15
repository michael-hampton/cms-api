<?php

namespace App\Services\Auth;

use App\Models\Member;
use App\Repositories\Members\MemberRepository;

/**
 * GuestMemberService
 *
 * Responsibility: Create anonymous members for new emails
 */
class GuestMemberService
{
    public function __construct(
        private readonly MemberRepository $memberRepository
    )
    {
    }

    /**
     * Create anonymous member
     *
     * Relies on DB unique constraint for (email, site_id) to prevent race conditions
     *
     * @param string $email
     * @param int $siteId
     * @return Member
     * @throws \RuntimeException if email already exists
     */
    public function createAnonymousMember(string $email, int $siteId): Member
    {
        // Normalize email
        $email = trim(strtolower($email));

        try {
            // Let the DB handle uniqueness via constraint
            return $this->memberRepository->createAnonymousMember($email, $siteId);
        } catch (\Exception $e) {
            // Race condition caught by DB constraint
            throw new \RuntimeException('Email already exists');
        }
    }

    /**
     * Convert anonymous member to full account
     *
     * @param int $memberId
     * @param string $firstName
     * @param string $lastName
     * @param string|null $password
     * @return Member|null
     */
    public function convertToFullAccount(
        int     $memberId,
        string  $firstName,
        string  $lastName,
        ?string $password = null
    ): ?Member
    {
        return $this->memberRepository->convertToFullAccount(
            $memberId,
            $firstName,
            $lastName,
            $password
        );
    }
}