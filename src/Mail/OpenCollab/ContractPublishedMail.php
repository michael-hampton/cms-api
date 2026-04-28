<?php

namespace App\Mail\OpenCollab;

use App\Framework\Mail\Mailable;
use App\Models\Contract;
use App\Models\User;

class ContractPublishedMail extends Mailable
{
    public function __construct(
        private readonly Contract $contract,
        private readonly User     $user,
    )
    {
        parent::__construct();
    }

    public function build(): self
    {
        return $this
            ->subject('A new contract is available')
            ->markdown('emails.open-collab.contract-published', [
                'user' => $this->user,
                'contract' => $this->contract,
                'url' => rtrim(config('app.url'), '/') . '/contracts/' . $this->contract->id,
            ]);
    }
}