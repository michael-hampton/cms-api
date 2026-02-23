<?php

namespace App\Events\Newsletters;

use App\Models\NewsletterCreationSchedule;

class NewsletterCreationScheduleCreated
{
    public function __construct(
        public readonly NewsletterCreationSchedule $schedule
    )
    {
    }
}