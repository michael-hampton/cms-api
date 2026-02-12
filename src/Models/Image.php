<?php

namespace App\Models;

use App\Framework\Database\QueryBuilder;
use App\Framework\Support\Collection;
use App\Models\Concerns\TracksCreator;

class Image extends Model
{
    use TracksCreator;

    protected $table = 'images';

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'url',
        'mime_type',
        'file_size',
        'width',
        'height',
        'alt_text',
        'caption',
        'description',
        'is_active',
        'created_at',
        'updated_at',
        'site_id',
        'name',
        'credit',
        'image_rights',
        'is_archived'
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'is_active' => 'boolean',
        'created_at' => 'date',
        'updated_at' => 'date',
        'is_archived' => 'boolean'
    ];

    protected $hidden = ['file_path'];

    public function categories(bool $relation = false)
    {
        return $this->belongsToMany(
            ImageCategory::class,
            'image_category_pivot',
            'image_id',
            'category_id',
            $relation
        );
    }

    public function usage(bool $relation = false)
    {
        return $this->hasMany(ImageUsage::class, 'image_id', 'id', $relation);
    }

    // Scopes
    public function scopeActive(QueryBuilder $query): QueryBuilder
    {
        return $query->where('is_active', 1);
    }

    public function scopeByMimeType(QueryBuilder $query, string $mimeType): QueryBuilder
    {
        return $query->where('mime_type', 'LIKE', $mimeType . '%');
    }

    public function scopeImages(QueryBuilder $query): QueryBuilder
    {
        return $query->where('mime_type', 'LIKE', 'image/%');
    }

    public function scopeBySize(QueryBuilder $query, ?int $minSize = null, ?int $maxSize = null): QueryBuilder
    {
        if ($minSize) {
            $query->where('file_size', '>=', $minSize);
        }
        if ($maxSize) {
            $query->where('file_size', '<=', $maxSize);
        }
        return $query;
    }

    public function scopeSearch(QueryBuilder $query, string $term): QueryBuilder
    {
        return $query->where(function($q) use ($term) {
            $q->where('original_name', 'LIKE', "%{$term}%")
                ->orWhere('alt_text', 'LIKE', "%{$term}%")
                ->orWhere('caption', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%");
        });
    }

    public function scopeRecent(QueryBuilder $query, int $days = 30): QueryBuilder
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        return $query->where('created_at', '>=', $date);
    }

    // Accessors
    public function getUrlAttribute(): string
    {
        // Return the stored URL or construct from file path
        return $this->attributes['url'] ?? $this->constructUrl();
    }

    public function getFormattedSizeAttribute(): string
    {
        return $this->formatFileSize($this->file_size);
    }

    public function getDimensionsAttribute(): ?string
    {
        if ($this->width && $this->height) {
            return "{$this->width}x{$this->height}";
        }
        return null;
    }

    public function getIsImageAttribute(): bool
    {
        return strpos($this->mime_type, 'image/') === 0;
    }

    // Helper methods
    public function isUsed(): bool
    {
        return $this->usage()->count() > 0;
    }

    public function getUsageCount(): int
    {
        return $this->usage()->count();
    }

    public function getUsageByType(string $type): Collection
    {
        return $this->usage()->where('usable_type', $type)->get();
    }

    public function addUsage(string $usableType, int $usableId, ?string $context = null): void
    {
        ImageUsage::create([
            'image_id' => $this->id,
            'usable_type' => $usableType,
            'usable_id' => $usableId,
            'context' => $context
        ]);
    }

    public function removeUsage(string $usableType, int $usableId, ?string $context = null): void
    {
        $query = ImageUsage::where('image_id', $this->id)
            ->where('usable_type', $usableType)
            ->where('usable_id', $usableId);

        if ($context) {
            $query->where('context', $context);
        }

        $query->delete();
    }

    public function updateMetadata(array $metadata): bool
    {
        $allowedFields = ['alt_text', 'caption', 'description', 'name'];
        $updateData = array_intersect_key($metadata, array_flip($allowedFields));

        if (empty($updateData)) {
            return false;
        }

        return $this->update($updateData);
    }

    public function softDelete(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function restore(): bool
    {
        return $this->update(['is_active' => true]);
    }

    private function constructUrl(): string
    {
        $baseUrl = rtrim(config('app.url', ''), '/');
        $storagePath = ltrim($this->file_path, '/');
        return "{$baseUrl}/storage/{$storagePath}";
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes === 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $factor = floor(log($bytes, 1024));

        return sprintf("%.1f %s", $bytes / pow(1024, $factor), $units[$factor]);
    }

    public function toArrayWithUsage(): array
    {
        $data = $this->toArray();
        $data['usage_count'] = $this->getUsageCount();
        $data['is_used'] = $this->isUsed();
        $data['formatted_size'] = $this->formatted_size;
        $data['dimensions'] = $this->dimensions;
        $data['is_image'] = $this->is_image;

        return $data;
    }

    public function tags(bool $relation = false)
    {
       return $this->hasMany(ImageTag::class, 'image_id', 'id', $relation);
    }

    public function syncTags(array $tagIds): void
    {
        ImageTag::where('image_id', $this->id)
            ->delete();

        if (empty($tagIds)) {
            return;
        }

        // Prepare rows for bulk insert
        $rows = array_map(fn($tagId) => [
            'image_id'   => $this->id,
            'tag_id'     => $tagId,
            'created_at' => date('Y-m-d H:i:s'),
        ], $tagIds);

        // Insert all at once
        ImageTag::query()->insertMany($rows);
    }
}