<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterSendSchedule;

class NewsletterSendScheduleCreated
{
    public function __construct(
        public readonly NewsletterSendSchedule $schedule
    )
    {
    }
}