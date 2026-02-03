<?php

namespace App\Services\Billing\Order;

use App\Models\Model;
use App\Repositories\Members\MemberRepository;
use App\Services\Shared\NameParser;

class OrderMemberResolver
{
    public function __construct(
        private readonly MemberRepository $memberRepository,
        private readonly NameParser       $nameParser
    )
    {
    }

    public function resolve(array $data, int $siteId): ?Model
    {
        // If user_id provided, fetch member
        if (!empty($data['user_id'])) {
            $member = $this->memberRepository->find($data['user_id']);
            if (!$member) {
                throw new \InvalidArgumentException("User ID {$data['user_id']} not found");
            }
            return $member;
        }

        // If no email, can't create/find member
        if (empty($data['customer_email'])) {
            return null;
        }

        // Check if member exists
        $existingMember = $this->memberRepository->findByEmail($data['customer_email']);
        if ($existingMember) {
            return $existingMember;
        }

        // Parse name
        $nameParts = $this->nameParser->parse($data['customer_name'] ?? '');
        if (empty($nameParts)) {
            return null;
        }

        // Create new member
        return $this->memberRepository->create([
            'site_id' => $siteId,
            'email' => $data['customer_email'],
            'first_name' => $nameParts['first_name'],
            'last_name' => $nameParts['last_name'],
            'password' => password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
            'is_active' => true,
        ]);
    }
}