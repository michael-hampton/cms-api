<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterCreationSchedule;

class NewsletterCreationScheduleUpdated
{
    public function __construct(
        public readonly NewsletterCreationSchedule $schedule
    )
    {
    }
}