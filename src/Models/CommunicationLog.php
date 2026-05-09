<?php

namespace App\Models;

class CommunicationLog extends Model
{
    protected $table = 'communication_logs';

    protected $fillable = [
        'member_id',
        'type',
        'channel',
        'subject',
        'preview',
        'status',
        'template_name',
        'campaign_name',
        'sent_at',
        'opened_at',
    ];

    protected $casts = [
        'member_id' => 'int',
        'sent_at' => 'datetime',
        'opened_at' => 'datetime',
    ];

    // ── Status helpers ─────────────────────────────────────────────

    public function isSent(): bool
    {
        return $this->status === 'sent';
    }

    public function isDelivered(): bool
    {
        return $this->status === 'delivered';
    }

    public function isOpened(): bool
    {
        return $this->status === 'opened';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isBounced(): bool
    {
        return $this->status === 'bounced';
    }

    public function isUnsubscribed(): bool
    {
        return $this->status === 'unsubscribed';
    }

    // ── Type helpers ───────────────────────────────────────────────

    public function isTransactional(): bool
    {
        return $this->type === 'transactional';
    }

    public function isMarketing(): bool
    {
        return $this->type === 'marketing';
    }

    // ── Domain helpers ─────────────────────────────────────────────

    public function wasOpened(): bool
    {
        return $this->opened_at !== null;
    }

    public function wasSent(): bool
    {
        return $this->sent_at !== null;
    }
}