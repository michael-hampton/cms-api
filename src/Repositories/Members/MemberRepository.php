<?php

namespace App\Repositories\Members;

use App\Enums\Member\MemberStatus;
use App\Framework\Database\Exceptions\UniqueConstraintViolationException;
use App\Framework\Support\Collection;
use App\Models\Member;
use App\Models\Model;
use App\Models\Payment;
use App\Models\Subscription;
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



// ─────────────────────────────────────────────────────────────────────────────
// ADD THESE METHODS to App\Repositories\Members\MemberRepository
//
// Replaces the previous MemberRepository_duplicate_methods.php patch.
// Includes both the duplicate-detection queries (Ticket 1) and the merge
// state mutations (Ticket 4).
// ─────────────────────────────────────────────────────────────────────────────

    // =========================================================================
    // Duplicate detection queries
    // =========================================================================

    /**
     * Find members whose normalised email matches the given member's email,
     * excluding the member itself.
     *
     * Uses LOWER(TRIM(email)) until a normalised_email column exists.
     *
     * @return \App\Framework\Support\Collection<\App\Models\Member>
     */
    public function findPossibleDuplicatesByEmail(Member $member): Collection
    {
        $normalisedEmail = strtolower(trim($member->email));

        return $this->model::whereRaw(
            'LOWER(TRIM(email)) = :email',
            ['email' => $normalisedEmail]
        )
            ->where('id', '!=', $member->id)
            ->where('site_id', $member->site_id)
            ->get();
    }

    /**
     * Find members sharing the same non-empty phone number, excluding self.
     *
     * @return \App\Framework\Support\Collection<\App\Models\Member>
     */
    public function findPossibleDuplicatesByPhone(\App\Models\Member $member): \App\Framework\Support\Collection
    {
        if (empty($member->phone)) {
            return collect();
        }

        return $this->model::where('phone', $member->phone)
            ->where('id', '!=', $member->id)
            ->where('site_id', $member->site_id)
            ->get();
    }

    /**
     * Find members sharing the same Stripe customer ID, excluding self.
     *
     * @return \App\Framework\Support\Collection<\App\Models\Member>
     */
    public function findPossibleDuplicatesByStripeCustomerId(\App\Models\Member $member): \App\Framework\Support\Collection
    {
        if (empty($member->stripe_customer_id)) {
            return collect();
        }

        return $this->model::where('stripe_customer_id', $member->stripe_customer_id)
            ->where('id', '!=', $member->id)
            ->where('site_id', $member->site_id)
            ->get();
    }

    /**
     * Find members with the same normalised last name AND the same billing
     * postcode (via the default billing address), excluding self.
     *
     * @return \App\Framework\Support\Collection<\App\Models\Member>
     */
    public function findPossibleDuplicatesByNameAndPostcode(Member $member): Collection
    {
        if (empty($member->last_name)) {
            return collect();
        }

        $postcode = \App\Models\Address::where('member_id', $member->id)
            ->where('is_default', 1)
            ->whereIn('type', ['billing', 'both'])
            ->whereNotNull('postcode')
            ->value('postcode');

        if (empty($postcode)) {
            return collect();
        }

        $normalisedLastName = strtolower(trim($member->last_name));
        $normalisedPostcode = strtolower(str_replace(' ', '', $postcode));

        return $this->model::join('addresses', 'addresses.member_id', '=', 'members.id')
            ->where('members.id', '!=', $member->id)
            ->where('members.site_id', $member->site_id)
            ->where('addresses.is_default', 1)
            ->whereIn('addresses.type', ['billing', 'both'])
            ->whereRaw("LOWER(TRIM(members.last_name)) = '{$normalisedLastName}'")
            ->whereRaw("LOWER(REPLACE(addresses.postcode, ' ', '')) = '{$normalisedPostcode}'")
            ->select('members.*')
            ->get();
    }

    /**
     * Mark a member as merged: deactivate them and record which account
     * absorbed them.
     *
     * This is the only write that touches member status during a merge.
     * Called inside the transaction boundary in MemberMergeService.
     */
    public function markAsMerged(
        int    $memberId,
        int    $mergedIntoMemberId,
        int    $mergedBy,
        string $mergedAt,
    ): ?Model
    {
        return $this->update($memberId, [
            'is_active' => false,
            'status' => MemberStatus::Merged->value,
            'merged_into_member_id' => $mergedIntoMemberId,
            'merged_at' => $mergedAt,
            'merged_by' => $mergedBy,
        ]);
    }

    /**
     * Reassign all orders from the secondary member to the primary member.
     * Orders use user_id as the member FK.
     *
     * Returns the number of rows updated.
     */
    public function reassignOrders(int $fromMemberId, int $toMemberId): int
    {
        return \App\Models\Order::where('user_id', $fromMemberId)
            ->update(['user_id' => $toMemberId]);
    }

    /**
     * Reassign all subscriptions from the secondary member to the primary.
     *
     * Returns the number of rows updated.
     */
    public function reassignSubscriptions(int $fromMemberId, int $toMemberId): int
    {
        return \App\Models\Subscription::where('member_id', $fromMemberId)
            ->update(['member_id' => $toMemberId]);
    }

    /**
     * Reassign all payments from the secondary member to the primary.
     * Payments have a direct member_id column.
     *
     * Returns the number of rows updated.
     */
    public function reassignPayments(int $fromMemberId, int $toMemberId): int
    {
        return \App\Models\Payment::where('member_id', $fromMemberId)
            ->update(['member_id' => $toMemberId]);
    }

    /**
     * Reassign notes from the secondary member to the primary member.
     *
     * Returns the number of rows updated.
     */
    public function reassignNotes(int $fromMemberId, int $toMemberId): int
    {
        return \App\Models\MemberNote::where('member_id', $fromMemberId)
            ->update(['member_id' => $toMemberId]);
    }

    /**
     * Copy addresses from the secondary member to the primary, skipping any
     * address where an existing primary address already matches on both type
     * and normalised postcode (deduplication).
     *
     * Returns the number of addresses copied.
     */
    public function mergeAddresses(int $fromMemberId, int $toMemberId): int
    {
        $secondaryAddresses = \App\Models\Address::where('member_id', $fromMemberId)->get();

        $existingAddresses = \App\Models\Address::where('member_id', $toMemberId)->get();

        $existingSignatures = $existingAddresses->map(fn($a) => $this->addressSignature($a))->all();

        $copied = 0;

        foreach ($secondaryAddresses as $address) {
            $signature = $this->addressSignature($address);

            if (in_array($signature, $existingSignatures, true)) {
                continue;
            }

            \App\Models\Address::create([
                'member_id' => $toMemberId,
                'type' => $address->type,
                'address_line_1' => $address->address_line_1,
                'address_line_2' => $address->address_line_2,
                'city' => $address->city,
                'state' => $address->state,
                'postcode' => $address->postcode,
                'country' => $address->country,
                'label' => $address->label,
                'is_default' => false, // never override the primary's default
            ]);

            $existingSignatures[] = $signature;
            $copied++;
        }

        return $copied;
    }

    // ─── Private helpers ──────────────────────────────────────────────────────

    /**
     * Produces a deduplication key for an address: type + normalised postcode.
     */
    private function addressSignature(\App\Models\Address $address): string
    {
        $normalisedPostcode = strtolower(str_replace(' ', '', (string)$address->postcode));
        return $address->type . '|' . $normalisedPostcode;
    }

    public function countActiveSubscriptions(int $memberId): int
    {
        return Subscription::where('member_id', $memberId)
            ->whereIn('status', ['active', 'trialing'])
            ->count();
    }

    public function hasPendingPayments(int $memberId): bool
    {
        return Payment::where('member_id', $memberId)
            ->whereIn('status', ['pending', 'processing'])
            ->exists();
    }
}