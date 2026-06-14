<?php

namespace App\Resources\OpenCollab;

use App\Models\ModerationEscalation;
use BackedEnum;

class EscalationResource
{
    public function __construct(private readonly ModerationEscalation $escalation)
    {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->escalation->id,
            'page_id' => $this->escalation->page_id,
            'queue_entry_id' => $this->escalation->queue_entry_id,
            'category' => $this->enumValue($this->escalation->category),
            'severity' => $this->enumValue($this->escalation->severity),
            'assigned_team' => $this->escalation->assigned_team,
            'assigned_user_id' => $this->escalation->assigned_user_id,
            'status' => $this->enumValue($this->escalation->status),
            'due_at' => $this->escalation->due_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->escalation->created_at?->format('Y-m-d H:i:s'),
            'acknowledged_at' => $this->escalation->acknowledged_at?->format('Y-m-d\TH:i:sP'),
            'resolved_at' => $this->escalation->resolved_at?->format('Y-m-d\TH:i:sP'),
            'resolution' => $this->escalation->resolution,
            'resolution_notes' => $this->escalation->resolution_notes,
        ];
    }

    private function enumValue(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
