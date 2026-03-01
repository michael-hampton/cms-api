<?php

namespace App\Models;

use App\Enums\Newsletters\NewsletterIssueStatus;

/**
 * Represents a newsletter issue — a content draft that can be reviewed,
 * edited, and eventually sent to recipients.
 *
 * Lifecycle: draft → ready → sent
 *
 * An issue captures a content snapshot at creation time. Sending it creates
 * a NewsletterSend record and transitions the issue to `sent`.
 *
 * @property int $id
 * @property int $newsletter_id
 * @property int $site_id
 * @property string $subject
 * @property array|null $content_blocks
 * @property string|null $html_snapshot
 * @property string $status
 * @property int|null $send_id
 * @property string|null $scheduled_at
 * @property string|null $sent_at
 * @property string $created_at
 * @property string $updated_at
 */
class NewsletterIssue extends Model
{
    protected $table = 'newsletter_issues';

    protected $fillable = [
        'newsletter_id',
        'site_id',
        'subject',
        'content_blocks',
        'html_snapshot',
        'status',
        'send_id',
        'scheduled_at',
        'sent_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'content_blocks' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function newsletter()
    {
        return $this->belongsTo(Newsletter::class);
    }

    public function send()
    {
        return $this->belongsTo(NewsletterSend::class, 'send_id');
    }

    public function isDraft(): bool
    {
        return $this->status === NewsletterIssueStatus::Draft->value;
    }

    public function isReady(): bool
    {
        return $this->status === NewsletterIssueStatus::Ready->value;
    }

    public function isSent(): bool
    {
        return $this->status === NewsletterIssueStatus::Sent->value;
    }

    public function isSendable(): bool
    {
        return in_array($this->status, [
            NewsletterIssueStatus::Draft->value,
            NewsletterIssueStatus::Ready->value,
        ], true);
    }
}