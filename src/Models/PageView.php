<?php

namespace App\Models;

class PageView extends Model
{
    protected $table = 'page_views';

    protected $fillable = [
        'page_id',
        'member_id',
        'site_id',
        'ip_address',
        'user_agent',
        'referer',
        'viewed_at'
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
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

    public static function recordView(
        int $pageId,
        ?int $memberId,
        int $siteId,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $referer = null
    ): self {
        return self::create([
            'page_id' => $pageId,
            'member_id' => $memberId,
            'site_id' => $siteId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'viewed_at' => date('Y-m-d H:i:s')
        ]);
    }

    public static function getUniqueViewCount(int $pageId): int
    {
        return self::where('page_id', $pageId)
            ->countDistinct('ip_address');
    }

    public static function getTotalViewCount(int $pageId): int
    {
        return self::where('page_id', $pageId)->count();
    }

    public static function getMemberViewCount(int $memberId, int $siteId): int
    {
        return self::where('member_id', $memberId)
            ->where('site_id', $siteId)
            ->countDistinct('page_id');
    }
}