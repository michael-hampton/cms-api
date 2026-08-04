<?php

declare(strict_types=1);

namespace App\Resources;

use App\Framework\Resource\JsonResource;

class AdHocFulfilmentRequestResource extends JsonResource
{
    public function toArray(): array
    {
        return [
            'id' => $this->getAttribute('id'),
            'process' => $this->getAttribute('process'),
            'print_batch_id' => $this->getAttribute('print_batch_id'),
            'print_batch_status' => $this->whenLoaded(
                'printBatch',
                fn($batch) => $batch->status,
            ),
            'requested_by_user_id' => $this->getAttribute('requested_by_user_id'),
            'requested_by_name' => $this->whenLoaded(
                'requestedBy',
                fn($user) => $user->name ?? $user->email ?? null,
            ),
            'created_at' => $this->getAttribute('created_at')?->format('Y-m-d H:i:s'),
        ];
    }
}