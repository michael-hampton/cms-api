<?php

namespace App\Models;

class Video extends Model
{
    protected $table = 'videos';

    protected $fillable = [
        'filename',
        'original_name',
        'file_path',
        'url',
        'mime_type',
        'file_size',
        'duration',
        'width',
        'height',
        'thumbnails',
        'title',
        'description',
        'site_id',
        'deleted_at',
        'created_at'
    ];

    protected $casts = [
        'thumbnails' => 'json',
        'file_size' => 'int',
        'duration' => 'float',
        'width' => 'int',
        'height' => 'int'
    ];

    public function getThumbnails(): array
    {
        return is_string($this->thumbnails)
            ? json_decode($this->thumbnails, true)
            : ($this->thumbnails ?? []);
    }

    public function updateMetadata(array $metadata): bool
    {
        $allowedFields = ['title', 'description'];
        $updateData = array_intersect_key($metadata, array_flip($allowedFields));

        if (empty($updateData)) {
            return true;
        }

        return $this->update($updateData);
    }

    public function isUsed(): bool
    {
        // Check if video is referenced in any content blocks
        // This would query your usage tracking table
        return false; // Implement based on your usage tracking
    }

    public function softDelete(): bool
    {
        return $this->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function addUsage(string $usableType, int $usableId, ?string $context = null): void
    {
        // Implement usage tracking
    }

    public function removeUsage(string $usableType, int $usableId, ?string $context = null): void
    {
        // Implement usage removal
    }

    public function getFormattedDuration(): string
    {
        $seconds = (int)$this->duration;
        $minutes = floor($seconds / 60);
        $seconds = $seconds % 60;

        return sprintf('%d:%02d', $minutes, $seconds);
    }

    public function getFormattedSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' bytes';
    }
}