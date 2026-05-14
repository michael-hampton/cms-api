<?php

namespace App\Listeners\Members;

use App\Events\Members\MemberDetailsChanged;
use App\Jobs\SyncMemberToStripeJob;

final class SyncMemberToStripeListener
{
    public function handle(MemberDetailsChanged $event): void
    {
        dispatch(SyncMemberToStripeJob::for(
            $event->memberId,
            $event->addressId
        ))->dispatchNow();
    }
}