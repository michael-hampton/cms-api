<?php

namespace App\Models;

class PageHistory extends Model
{
    public $timestamps = false;
    protected $table = 'page_history';
    protected $fillable = [
        'page_id',
        'user_id',
        'site_id',
        'action',
        'description',
        'changes',
        'snapshot',
        'ip_address',
        'user_agent',
        'created_at'
    ];
    protected $casts = [
        'changes' => 'array',
        'snapshot' => 'array',
        'created_at' => 'date'
    ];

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function user($relation = false)
    {
        return $this->belongsTo(User::class, 'user_id', 'id', $relation);
    }

    public function getChangeSummary(): string
    {
        if (!$this->changes) {
            return $this->description ?? 'No changes recorded';
        }

        $changes = $this->changes;
        $summary = [];

        if (isset($changes['title'])) {
            $summary[] = 'Title changed';
        }
        if (isset($changes['status'])) {
            $summary[] = "Status: {$changes['status']['old']} → {$changes['status']['new']}";
        }
        if (isset($changes['blocks_added'])) {
            $summary[] = "{$changes['blocks_added']} block(s) added";
        }
        if (isset($changes['blocks_removed'])) {
            $summary[] = "{$changes['blocks_removed']} block(s) removed";
        }
        if (isset($changes['blocks_modified'])) {
            $summary[] = "{$changes['blocks_modified']} block(s) modified";
        }

        return !empty($summary) ? implode(', ', $summary) : ($this->description ?? 'Page updated');
    }

    public function getUserName(): string
    {
        if (is_array($this->user)) {
            return $this->user['name'];
        }

        if ($this->user) {
            return $this->user->name;
        }
        return 'System';
    }

    public function getActionLabel(): string
    {
        $labels = [
            'created' => 'Created',
            'updated' => 'Updated',
            'published' => 'Published',
            'unpublished' => 'Unpublished',
            'duplicated' => 'Duplicated',
            'deleted' => 'Deleted',
            'restored' => 'Restored'
        ];

        return $labels[$this->action] ?? ucfirst($this->action);
    }
}