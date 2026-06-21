<?php

namespace App\Repositories\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Framework\Support\Collection;
use App\Models\IssueDelivery;

class PlanIssueScheduleRepository
{
    public function findWithinDeliveryWindow(
        int $subscriptionPlanId,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): Collection {
        $startDate = $start->format('Y-m-d H:i:s');
        $endDate = $end->format('Y-m-d H:i:s');

        return IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->whereNull('subscription_id')
            ->where('status', IssueScheduleStatus::ACTIVE->value)
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($query) use ($startDate, $endDate) {
                    $query->whereNotNull('estimated_delivery_date')
                        ->where('estimated_delivery_date', '>=', $startDate)
                        ->where('estimated_delivery_date', '<=', $endDate);
                })->orWhere(function ($query) use ($startDate, $endDate) {
                    $query->whereNull('estimated_delivery_date')
                        ->where('on_sale_date', '>=', $startDate)
                        ->where('on_sale_date', '<=', $endDate);
                });
            })
            ->orderBy('estimated_delivery_date', 'asc')
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->get();
    }

    public function findForAccount(
        int $subscriptionPlanId,
        \DateTimeInterface $fromDate,
        array $includedIssueIds = []
    ): Collection {
        $from = $fromDate->format('Y-m-d H:i:s');
        $query = IssueDelivery::where('subscription_plan_id', $subscriptionPlanId)
            ->whereNull('subscription_id')
            ->where('status', IssueScheduleStatus::ACTIVE->value);

        $query->where(function ($query) use ($from, $includedIssueIds) {
            $query->where(function ($query) use ($from) {
                $query->whereNotNull('estimated_delivery_date')
                    ->where('estimated_delivery_date', '>=', $from);
            })->orWhere(function ($query) use ($from) {
                $query->whereNull('estimated_delivery_date')
                    ->where('on_sale_date', '>=', $from);
            });

            if (!empty($includedIssueIds)) {
                $query->orWhereIn('id', $includedIssueIds);
            }
        });

        return $query
            ->orderBy('estimated_delivery_date', 'asc')
            ->orderBy('on_sale_date', 'asc')
            ->orderBy('issue_number', 'asc')
            ->get();
    }
}
