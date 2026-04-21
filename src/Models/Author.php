<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Models\Concerns\HasCloneHistory;
use App\Models\Concerns\TracksCreator;

class Author extends Model
{
    use HasCloneHistory, TracksCreator;

    protected $table = 'authors';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'bio',
        'avatar',
        'website',
        'twitter',
        'linkedin',
        'facebook',
        'status',
        'site_id',
        'clone_history',
        'expertise',
        'location',
        'education',
        'awards',
        'seniority_date',
        'is_active',
        'is_guest'
    ];

    protected $casts = [
        'created_at' => 'date',
        'updated_at' => 'date',
        'clone_history' => 'array',
        'location' => 'array',
        'education' => 'array',
        'awards' => 'array',
        'seniority_date' => 'date',
        'is_active' => 'boolean'
    ];

    protected $appends = [
        'url',
        'total_published_articles',
        'total_published_reviews',
        'years_of_experience',
    ];

    public function pages($relation = false)
    {
        return $this->belongsToMany(Page::class, 'page_authors', 'author_id', 'id', $relation);
    }

    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function getUrlAttribute(): string
    {
        return '/authors/' . $this->slug;
    }

    public function getTotalPublishedArticlesAttribute(): int
    {
        return $this->pages(true)
            ->where('status', 'published')
            ->count();
    }

    public function getTotalPublishedReviewsAttribute(): int
    {
        return $this->pages(true)
            ->where('status', 'published')
            ->where('page_type', 'review')
            ->count();
    }

    public function getYearsOfExperienceAttribute(): ?int
    {
        if (!$this->seniority_date) {
            return null;
        }

        $now = new \DateTime();
        $interval = $this->seniority_date->diff($now);

        return $interval->y;
    }

    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug(QueryBuilder $query, string $slug): QueryBuilder
    {
        return $query->where('slug', $slug);
    }

    public function getCounts(): array
    {
        $data = parent::toArray();
        $data['url'] = $this->getUrlAttribute();
        $data['total_published_articles'] = $this->getTotalPublishedArticlesAttribute();
        $data['total_published_reviews'] = $this->getTotalPublishedReviewsAttribute();
        $data['years_of_experience'] = $this->getYearsOfExperienceAttribute();
        return $data;
    }
}