<?php

declare(strict_types=1);

namespace App\Repositories\Subscriptions;

use App\Models\SubscriptionCommunicationLetterCode;

class SubscriptionCommunicationLetterCodeRepository
{
    public function findForCommunication(int $communicationId): ?SubscriptionCommunicationLetterCode
    {
        return SubscriptionCommunicationLetterCode::where('subscription_communication_id', $communicationId)->first();
    }
}
