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

    public function find(int $id): ?SubscriptionCommunicationLetterCode
    {
        return SubscriptionCommunicationLetterCode::find($id);
    }

    public function findByCode(string $letterCode): ?SubscriptionCommunicationLetterCode
    {
        return SubscriptionCommunicationLetterCode::where('letter_code', $letterCode)->first();
    }

    public function all()
    {
        return SubscriptionCommunicationLetterCode::orderBy('letter_code')->get();
    }

    public function create(int $communicationId, string $letterCode, ?string $description): SubscriptionCommunicationLetterCode
    {
        return SubscriptionCommunicationLetterCode::create([
            'subscription_communication_id' => $communicationId,
            'letter_code' => $letterCode,
            'description' => $description,
        ]);
    }

    public function update(int $id, string $letterCode, ?string $description): ?SubscriptionCommunicationLetterCode
    {
        $code = $this->find($id);

        if ($code === null) {
            return null;
        }

        $code->update([
            'letter_code' => $letterCode,
            'description' => $description,
        ]);

        return $code;
    }

    public function delete(int $id): bool
    {
        $code = $this->find($id);

        if ($code === null) {
            return false;
        }

        return (bool) $code->delete();
    }
}
