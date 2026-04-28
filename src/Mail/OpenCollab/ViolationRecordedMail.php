<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\ContributorViolation;
use App\Models\User;

class ViolationRecordedMail extends Mailable
{
    public function __construct(
        private readonly ContributorViolation $violation,
        private readonly User                 $user,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('A policy violation has been recorded')
            ->markdown('emails.open-collab.violation-recorded', [
                'user' => $this->user,
                'violation' => $this->violation,
            ]);
    }
}