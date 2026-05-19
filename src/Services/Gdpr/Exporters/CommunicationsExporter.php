<?php

namespace App\Services\Gdpr\Exporters;

use App\Models\CommunicationLog;
use App\Models\Member;

final class CommunicationsExporter implements MemberDataExporter
{
    public function key(): string
    {
        return 'communications';
    }

    public function export(Member $member): array
    {
        return CommunicationLog::where('member_id', $member->id)
            ->orderBy('sent_at', 'desc')
            ->get()
            ->map(fn(CommunicationLog $c) => [
                'id'            => $c->id,
                'type'          => $c->type,
                'channel'       => $c->channel,
                'subject'       => $c->subject,
                'status'        => $c->status,
                'template_name' => $c->template_name,
                'campaign_name' => $c->campaign_name,
                'sent_at'       => $c->sent_at?->format('Y-m-d H:i:s'),
                'opened_at'     => $c->opened_at?->format('Y-m-d H:i:s'),
            ])
            ->toArray();
    }
}