<?php

namespace App\Models;

class PageLike extends Model
{
    protected $table = 'page_likes';

    protected $fillable = [
        'page_id',
        'member_id',
        'site_id',
        'liked_at'
    ];

    protected $casts = [
        'liked_at' => 'datetime',
    ];

    public $timestamps = false;

    public function page($relation = false)
    {
        return $this->belongsTo(Page::class, 'page_id', 'id', $relation);
    }

    public function member($relation = false)
    {
        return $this->belongsTo(Member::class, 'member_id', 'id', $relation);
    }

    public function site($relation = false)
    {
        return $this->belongsTo(Site::class, 'site_id', 'id', $relation);
    }

    public static function toggle(int $pageId, int $memberId, int $siteId): array
    {
        $existing = self::where('page_id', $pageId)
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->first();

        if ($existing) {
            $existing->delete();
            return [
                'like' => $existing,
                'liked' => false,
                'like_count' => self::getLikeCount($pageId)
            ];
        }

        $likeObject = self::create([
            'page_id' => $pageId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'liked_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'like' => $likeObject,
            'liked' => true,
            'like_count' => self::getLikeCount($pageId)
        ];
    }

    public static function isLikedBy(int $pageId, int $memberId, int $siteId): bool
    {
        return self::where('page_id', $pageId)
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->exists();
    }

    public static function getLikeCount(int $pageId): int
    {
        return self::where('page_id', $pageId)->count();
    }

    public static function getMemberLikeCount(int $memberId, int $siteId): int
    {
        return self::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->count();
    }

    public static function getMemberLikedPages(int $memberId, int $siteId, ?int $limit = null)
    {
        $query = self::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->with(['page'])
            ->orderBy('liked_at', 'desc');

        if ($limit) {
            $query->limit($limit);
        }

        return $query->get();
    }

    // Add these static methods to your existing PageLike model

    public static function toggleAction(int $pageId, int $memberId, int $siteId, string $action = 'like'): array
    {
        $existing = static::where('page_id', $pageId)
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('action', $action)
            ->first();

        if ($existing) {
            $existing->delete();
            $count = static::where('page_id', $pageId)->where('action', $action)->count();
            return ['active' => false, 'count' => $count, 'liked' => false];
        }

        $result = static::create([
            'page_id' => $pageId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'action' => $action,
            'liked_at' => now_datetime(),
        ]);

        $count = static::where('page_id', $pageId)->where('action', $action)->count();
        return ['active' => true, 'count' => $count, 'liked' => true, 'like' => $result];
    }

    public static function hasAction(int $pageId, int $memberId, int $siteId, string $action): bool
    {
        return static::where('page_id', $pageId)
            ->where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('action', $action)
            ->exists();
    }

    public static function getMemberActionPages(int $memberId, int $siteId, string $action, ?int $limit = null)
    {
        $query = static::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->where('action', $action)
            ->with(['page.categories'])
            ->orderBy('liked_at', 'desc');

        if ($limit) $query->limit($limit);

        return $query->get();
    }
}