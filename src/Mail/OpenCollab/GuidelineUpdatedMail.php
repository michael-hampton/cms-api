<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Guideline;
use App\Models\User;

class GuidelineUpdatedMail extends Mailable
{
    public function __construct(
        private readonly Guideline $guideline,
        private readonly User      $user,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('Guidelines have been updated')
            ->markdown('emails.open-collab.guideline-updated', [
                'user' => $this->user,
                'guideline' => $this->guideline,
                'url' => rtrim(config('app.url'), '/') . '/guidelines',
            ]);
    }
}