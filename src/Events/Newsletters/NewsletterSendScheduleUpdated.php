<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterSendSchedule;

class NewsletterSendScheduleUpdated
{
    public function __construct(
        public readonly NewsletterSendSchedule $schedule
    )
    {
    }
}