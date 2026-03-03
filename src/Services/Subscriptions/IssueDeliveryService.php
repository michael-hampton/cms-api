<?php

namespace App\Services\Subscriptions;

use App\Enums\Subscriptions\IssueScheduleStatus;
use App\Models\Model;
use App\Repositories\Subscriptions\IssueDeliveryRepository;

class IssueDeliveryService
{
    public function __construct(
        private readonly IssueDeliveryRepository $scheduleRepository,
    )
    {
    }

    public function activateSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::ACTIVE);
    }

    public function cancelSchedule(int $scheduleId): ?Model
    {
        return $this->updateScheduleStatus($scheduleId, IssueScheduleStatus::CANCELLED);
    }

    public function updateScheduleStatus(int $scheduleId, IssueScheduleStatus $status): ?Model
    {
        return $this->scheduleRepository->update($scheduleId, [
            'status' => $status->value,
        ]);
    }
}