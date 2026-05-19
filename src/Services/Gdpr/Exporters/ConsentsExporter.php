<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\ConsentAuditLog;
use App\Models\Member;
use App\Models\MemberConsent;

final class ConsentsExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'consents';
    }

    public function export(Member $member): array
    {
        $current = MemberConsent::where('member_id', $member->id)
            ->get()
            ->map(fn(MemberConsent $c) => [
                'consent_type_id' => $c->consent_type_id,
                'is_granted'      => $c->is_granted,
                'channel'         => $c->channel,
                'granted_at'      => $c->granted_at?->format('Y-m-d H:i:s'),
                'revoked_at'      => $c->revoked_at?->format('Y-m-d H:i:s'),
                'expires_at'      => $c->expires_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        $history = ConsentAuditLog::where('member_id', $member->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn(ConsentAuditLog $l) => [
                'consent_type_id' => $l->consent_type_id,
                'action'          => $l->action,
                'previous_state'  => $l->previous_state,
                'new_state'       => $l->new_state,
                'source'          => $l->source,
                'ip_address'      => $l->ip_address,
                'created_at'      => $l->created_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();

        return [
            'current' => $current,
            'history' => $history,
        ];
    }
}