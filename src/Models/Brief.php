<?php

namespace App\Models;

class Brief extends Model
{
    protected $table = 'briefs';

    protected $fillable = [
        'title',
        'description',
        'owner_id',
        'category_id',
        'site_id',
        'status',
        'converted_page_id',
        'converted_at',
        'target_word_count',
        'seo_keywords',
        'template_id',
        'last_activity_at',
        'last_activity_user_id',
        'parent_brief_id',
        'target_audience',
        'is_active'
    ];

    protected $casts = [
        'converted_at' => 'datetime'
    ];

    protected $alwaysInclude = [
        'attachments',
        'comments',
        'owner',
        'category'
    ];

    public function attachments(bool $relation = false)
    {
        return $this->hasMany(BriefAttachment::class, 'brief_id', 'id', $relation)
            ->orderBy('sort_order');
    }

    public function comments(bool $relation = false)
    {
        return $this->hasMany(BriefComment::class, 'brief_id', 'id', $relation)
            ->whereNull('parent_comment_id')
            ->orderBy('created_at', 'desc');
    }

    public function owner(bool $relation = false)
    {
        return $this->belongsTo(User::class, 'owner_id', 'id', $relation);
    }

    public function category(bool $relation = false)
    {
        return $this->belongsTo(Category::class, 'category_id', 'id', $relation);
    }

    public function convertedPage(bool $relation = false)
    {
        return $this->belongsTo(Page::class, 'converted_page_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        return $this->is_active;
    }

    public function isConverted(): bool
    {
        return $this->status === 'converted';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function template()
    {
        return $this->belongsTo(BriefTemplate::class, 'template_id');
    }

//    public function collaborators()
//    {
//        return $this->hasMany(BriefCollaborator::class);
//    }

    public function tasks()
    {
        return $this->hasMany(BriefTask::class);
    }

    public function versions()
    {
        return $this->hasMany(BriefVersion::class)->orderBy('version_number', 'desc');
    }

    public function relationships(bool $relation = false)
    {
        return $this->hasMany(BriefRelationship::class, null, null, $relation);
    }

    public function activityLog()
    {
        return $this->hasMany(BriefActivityLog::class)->orderBy('created_at', 'desc');
    }

    public function parentBrief()
    {
        return $this->belongsTo(Brief::class, 'parent_brief_id');
    }

    public function childBriefs()
    {
        return $this->hasMany(Brief::class, 'parent_brief_id');
    }

    public function lastActivityUser()
    {
        return $this->belongsTo(User::class, 'last_activity_user_id');
    }

    public function collaborators($relation = false)
    {
        return $this->morphMany(Collaborator::class, 'collaboratable', $relation);
    }

    public function hasCollaborator(int $userId): bool
    {
        if (!$this->relationLoaded('collaborators')) {
            $this->load(['collaborators']);
        }

        return $this->collaborators->contains('user_id', $userId);
    }

    public function getCollaboratorRole(int $userId): ?string
    {
        if (!$this->relationLoaded('collaborators')) {
            $this->load(['collaborators']);
        }

        $collaborator = $this->collaborators->firstWhere('user_id', $userId);
        return $collaborator ? $collaborator->role : null;
    }
}