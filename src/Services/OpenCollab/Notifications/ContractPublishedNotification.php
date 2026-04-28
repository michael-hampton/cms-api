<?php

namespace App\Services\OpenCollab\Notifications;

use App\Framework\Mail\Mailable;
use App\Framework\Notifications\AbstractNotification;
use App\Framework\Notifications\ConsentAwareNotification;
use App\Framework\Notifications\EmailableNotification;
use App\Models\Contract;
use App\Models\ContributorViolation;
use App\Models\User;

final class ContractPublishedNotification extends AbstractNotification
    implements EmailableNotification, ConsentAwareNotification
{
    public function __construct(
        public readonly Contract $contract,
        public readonly User     $user,
    )
    {
        parent::__construct(userId: $user->id, email: $user->email);
    }

    public function subject(): string
    {
        return "A new contract is available for you to review";
    }

    public function toMailable(): Mailable
    {
        return new ContractPublishedMail($this->contract, $this->user);
    }

    public function consentType(): string
    {
        return 'contributor.contract_published';
    }
}