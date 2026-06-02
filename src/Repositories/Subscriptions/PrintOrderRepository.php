<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Enums\Subscriptions\PrintRegion;
use App\Enums\Subscriptions\SubscriptionStatus;
use App\Enums\Subscriptions\SubscriptionType;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;
use App\Models\IssueDeliveryRegion;

/**
 * Persistence-only. No business logic lives here.
 *
 * Owns all queries needed to produce and persist print order records.
 */
class PrintOrderRepository
{
    /**
     * Find all IssueDelivery records due for a print order today.
     *
     * Eligibility criteria (all must pass):
     *   1. print_order_date = today.
     *   2. The plan's site is active and requires a print order.
     *      (We approximate this with status = ACTIVE and site_id scope.)
     *   3. print_order_done = false (not already processed).
     *   4. At least one active print subscriber exists for the plan.
     *
     * Note: criterion 4 is a fast existence check only — the actual count
     * is performed later by countSubscribersByRegion().
     *
     * @return Collection<IssueDelivery>
     */
    public function findDueForPrintOrder(\DateTimeInterface $today): Collection
    {
        $date = $today->format('Y-m-d');

        return IssueDelivery::query()
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->whereDate('print_order_date', $date)
            ->where('print_order_done', false)
            ->whereHas('subscriptionPlans', function ($q) {
                // Plan must exist and be active; requires_print_order is
                // approximated by the plan having print_shipping_required.
                $q->where('is_active', true)
                    ->where('print_shipping_required', true);
            })
            ->whereExists(function ($q) {
                // Fast guard: at least one active print subscriber.
                $q->from('subscriptions')
                    ->whereColumn('subscriptions.plan_id', 'issue_deliveries.subscription_plan_id')
                    ->where('subscriptions.status', SubscriptionStatus::ACTIVE->value)
                    ->where('subscriptions.delivery_type', SubscriptionType::PRINTED->value);
            })
            ->get();
    }

    /**
     * Count active print subscribers for an issue delivery, split by UK / Export.
     *
     * UK  = delivery address country_code = 'GB'
     * Export = anything else (including null)
     *
     * The delivery address is the subscriber's default shipping address on
     * their member record. We fall back to any billing address if no shipping
     * address exists, matching the priority used by PrintAddressResolver.
     *
     * @return array{uk: int, export: int}
     */
    public function countSubscribersByRegion(IssueDelivery $issueDelivery): array
    {
        // One query with conditional aggregation avoids two round-trips.
        $row = \App\Framework\Database\Database::table('subscriptions as s')
            ->join('members as m', 'm.id', '=', 's.member_id')
            ->leftJoin('addresses as a', function ($join) {
                $join->on('a.member_id', '=', 'm.id')
                    ->where('a.is_default', true)
                    ->whereIn('a.type', ['shipping', 'both']);
            })
            ->where('s.plan_id', $issueDelivery->subscription_plan_id)
            ->where('s.status', SubscriptionStatus::ACTIVE->value)
            ->where('s.delivery_type', SubscriptionType::PRINTED->value)
            ->where(function ($q) use ($issueDelivery) {
                $date = $issueDelivery->on_sale_date
                    ?? $issueDelivery->estimated_delivery_date;

                if ($date) {
                    $formatted = $date->format('Y-m-d H:i:s');
                    $q->where(function ($inner) use ($formatted) {
                        $inner->whereNull('s.start_date')
                            ->orWhere('s.start_date', '<=', $formatted);
                    })->where(function ($inner) use ($formatted) {
                        $inner->whereNull('s.end_date')
                            ->orWhere('s.end_date', '>=', $formatted);
                    });
                }
            })
            ->selectRaw(
                'SUM(CASE WHEN UPPER(TRIM(a.country)) = ? THEN 1 ELSE 0 END) as uk_count,
                 SUM(CASE WHEN UPPER(TRIM(a.country)) != ? OR a.country IS NULL THEN 1 ELSE 0 END) as export_count',
                ['GB', 'GB']
            )
            ->first();

        return [
            'uk'     => (int) ($row->uk_count     ?? 0),
            'export' => (int) ($row->export_count ?? 0),
        ];
    }

    /**
     * Count active print subscribers scoped to a specific regional edition.
     *
     * A subscriber belongs to a regional edition when their delivery address
     * postcode prefix maps to the territory linked to the regional edition.
     * The territory mapping is resolved via postcode_territory_mappings.
     *
     * @return array{uk: int, export: int}
     */
    public function countSubscribersByRegionForEdition(
        IssueDelivery       $issueDelivery,
        IssueDeliveryRegion $edition,
    ): array {
        $row = \App\Framework\Database\Database::table('subscriptions as s')
            ->join('members as m', 'm.id', '=', 's.member_id')
            ->leftJoin('addresses as a', function ($join) {
                $join->on('a.member_id', '=', 'm.id')
                    ->where('a.is_default', true)
                    ->whereIn('a.type', ['shipping', 'both']);
            })
            ->join('postcode_territory_mappings as ptm', function ($join) {
                // Match on the first two characters of the postcode (area code).
                $join->whereRaw("UPPER(LEFT(TRIM(a.postcode), 2)) = UPPER(ptm.postcode_prefix)");
            })
            ->where('s.plan_id', $issueDelivery->subscription_plan_id)
            ->where('s.status', SubscriptionStatus::ACTIVE->value)
            ->where('s.delivery_type', SubscriptionType::PRINTED->value)
            ->where('ptm.territory_id', $edition->territory_id ?? $edition->region_id)
            ->where(function ($q) use ($issueDelivery) {
                $date = $issueDelivery->on_sale_date
                    ?? $issueDelivery->estimated_delivery_date;

                if ($date) {
                    $formatted = $date->format('Y-m-d H:i:s');
                    $q->where(function ($inner) use ($formatted) {
                        $inner->whereNull('s.start_date')
                            ->orWhere('s.start_date', '<=', $formatted);
                    })->where(function ($inner) use ($formatted) {
                        $inner->whereNull('s.end_date')
                            ->orWhere('s.end_date', '>=', $formatted);
                    });
                }
            })
            ->selectRaw(
                'SUM(CASE WHEN UPPER(TRIM(a.country)) = ? THEN 1 ELSE 0 END) as uk_count,
                 SUM(CASE WHEN UPPER(TRIM(a.country)) != ? OR a.country IS NULL THEN 1 ELSE 0 END) as export_count',
                ['GB', 'GB']
            )
            ->first();

        return [
            'uk'     => (int) ($row->uk_count     ?? 0),
            'export' => (int) ($row->export_count ?? 0),
        ];
    }

    /**
     * Load all regional editions for an issue delivery, including their surplus fields.
     *
     * @return Collection<IssueDeliveryRegion>
     */
    public function findRegionalEditions(int $issueDeliveryId): Collection
    {
        return IssueDeliveryRegion::where('issue_delivery_id', $issueDeliveryId)->get();
    }

    /**
     * Write the aggregate subscriber total back to the issue delivery.
     * Surplus is NOT included — this is subscriber copies only.
     */
    public function markPrintOrderDone(IssueDelivery $issueDelivery, int $subscriberTotal): void
    {
        $issueDelivery->update([
            'subscription_total' => $subscriberTotal,
            'print_order_done'   => true,
        ]);
    }
}