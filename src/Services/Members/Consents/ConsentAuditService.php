<?php

namespace App\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Models\ConsentAuditLog;
use App\Models\ConsentType;
use App\Models\Member;

class ConsentAuditService
{
    public function log(
        Member               $member,
        ConsentType          $type,
        ConsentAction        $action,
        ?bool                $previousState,
        bool                 $newState,
        ConsentActionContext $context
    ): void
    {
        $log = new ConsentAuditLog();
        $log->member_id = $member->id;
        $log->consent_type_id = $type->id;
        $log->action = $action->value;
        $log->previous_state = $previousState;
        $log->new_state = $newState;
        $log->source = $context->source;
        $log->reason = $context->reason;
        $log->admin_user_id = $context->adminUserId;
        $log->ip_address = $context->ipAddress;
        $log->user_agent = $context->userAgent;
        $log->created_at = now();

        $log->save();
    }
}