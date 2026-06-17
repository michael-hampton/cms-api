<?php

declare(strict_types=1);

namespace App\Services\Subscriptions\Import;

use App\DTO\Subscriptions\BulkSubscriptionImportRow;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use App\Services\Subscriptions\CrmSubscriptionCreationService;
use Throwable;

final class BulkSubscriptionImportService
{
    public function __construct(
        private readonly MemberRepository $members,
        private readonly CrmSubscriptionCreationService $subscriptions,
    ) {
    }

    public function import(iterable $rows, int $siteId, bool $continueOnError = true): array
    {
        $result = ['processed' => 0, 'succeeded' => 0, 'failed' => 0, 'errors' => []];

        foreach ($rows as $entry) {
            ++$result['processed'];
            $line = (int)($entry['line'] ?? $result['processed'] + 1);

            try {
                /** @var BulkSubscriptionImportRow $row */
                $row = $entry['row'];
                $member = $this->resolveMember($row, $siteId);

                $this->subscriptions->createSubscription(
                    memberId: (int)$member->id,
                    planId: $row->planId,
                    paymentMethodId: $row->paymentMethodId,
                    siteId: $siteId,
                    deliveryAddress: $row->address,
                    pricingId: $row->pricingTierId,
                    offerType: $row->offerType,
                );

                ++$result['succeeded'];
            } catch (Throwable $exception) {
                ++$result['failed'];
                $result['errors'][] = [
                    'line' => $line,
                    'email' => isset($row) ? $row->email : null,
                    'message' => $exception->getMessage(),
                ];

                if (!$continueOnError) {
                    throw $exception;
                }
            }
        }

        return $result;
    }

    private function resolveMember(BulkSubscriptionImportRow $row, int $siteId): Member
    {
        $existing = $this->members->findByEmail($row->email, $siteId);
        if ($existing) {
            return $existing;
        }

        /** @var Member $member */
        $member = $this->members->create([
            'site_id' => $siteId,
            'email' => $row->email,
            'first_name' => $row->firstName,
            'last_name' => $row->lastName,
            'display_name' => trim($row->firstName . ' ' . $row->lastName),
            'anonymous' => false,
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        return $member;
    }
}
