<?php

namespace App\Models;

class NewsletterSendRecipient extends Model
{
    const STATUS_PENDING = 'pending';
    const STATUS_SENT = 'sent';
    const STATUS_FAILED = 'failed';
    const STATUS_BOUNCED = 'bounced';
    protected $table = 'newsletter_send_recipients';
    protected $fillable = [
        'newsletter_send_id',
        'email',
        'status',
        'error_message',
        'sent_at',
        'attempts',
        'last_attempt_at',
        'unsubscribe_token'
    ];

    public function send()
    {
        return $this->belongsTo(NewsletterSend::class, 'newsletter_send_id');
    }

    public function markAsSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now_datetime()->format('Y-m-d H:i:s'),
            'last_attempt_at' => now_datetime()->format('Y-m-d H:i:s'),
            'attempts' => $this->attempts + 1
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'last_attempt_at' => now_datetime()->format('Y-m-d H:i:s'),
            'attempts' => $this->attempts + 1
        ]);
    }

    public function canRetry(int $maxAttempts = 3): bool
    {
        return $this->status === self::STATUS_FAILED
            && $this->attempts < $maxAttempts;
    }

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    public function scopeSent($query)
    {
        return $query->where('status', self::STATUS_SENT);
    }

    public function newsletter($relation = false)
    {
        return $this->belongsTo(Newsletter::class, 'newsletter_id', 'id', $relation);
    }
}