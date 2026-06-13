<?php

namespace App\Console;

use App\Framework\Console\Command; // ASSUMED base class name
use App\Repositories\OpenCollab\ModerationEscalationRepository;

class MarkOverdueEscalationsCommand extends Command
{
    protected $signature = 'moderation:mark-overdue-escalations';
    public $description = 'Marks open/acknowledged/in_progress escalations past their SLA due date as overdue.';

    public function __construct(
        private readonly ModerationEscalationRepository $escalationRepository,
    ) {
    }

    public function handle(): int
    {
        $count = $this->escalationRepository->markOverdue();
        $this->info("Marked {$count} escalation(s) overdue.");
        return 0;
    }
}