<?php

namespace App\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Models\ConsentType;
use App\Models\Member;
use App\Models\Model;
use App\Repositories\Members\Consents\ConsentAuditLogRepository;

class ConsentAuditService
{
    public function __construct(
        private readonly ConsentAuditLogRepository $auditLogRepository
    )
    {
    }

    public function log(
        Member               $member,
        ConsentType          $type,
        ConsentAction        $action,
        ?bool                $previousState,
        bool                 $newState,
        ConsentActionContext $context
    ): Model
    {
        return $this->auditLogRepository->create([
            'member_id' => $member->id,
            'consent_type_id' => $type->id,
            'action' => $action->value,
            'previous_state' => $previousState,
            'new_state' => $newState,
            'source' => $context->source,
            'reason' => $context->reason,
            'admin_user_id' => $context->adminUserId,
            'ip_address' => $context->ipAddress,
            'user_agent' => $context->userAgent,
            'created_at' => now(),
        ]);
    }
}