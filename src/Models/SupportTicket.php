<?php

namespace App\Models;

class SupportTicket extends Model
{
    protected $table = 'support_tickets';

    protected $fillable = [
        'member_id',
        'site_id',
        'reason',
        'subscription_id',
        'brand',
        'message',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'assigned_to',
        'notes',
        'created_at',
        'updated_at'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function subscription($relation = false)
    {
        return $this->belongsTo(Subscription::class, 'subscription_id', 'id', $relation);
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function close(): bool
    {
        $this->status = 'closed';
        return $this->save();
    }
}