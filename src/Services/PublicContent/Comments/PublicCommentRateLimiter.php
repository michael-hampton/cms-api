<?php

namespace App\Services\PublicContent\Comments;

use App\Framework\Support\Cache\Cache;

final class PublicCommentRateLimiter
{
    private const int MAX_ATTEMPTS = 5;
    private const int DECAY_SECONDS = 600;

    /**
     * @return array{allowed: bool, retry_after: int, remaining: int}
     */
    public function consume(int $siteId, ?int $memberId, string $ipAddress): array
    {
        $key = $this->key($siteId, $memberId, $ipAddress);
        $now = time();
        $state = Cache::get($key, [
            'attempts' => 0,
            'reset_at' => $now + self::DECAY_SECONDS,
        ]);

        if (!is_array($state) || (int) ($state['reset_at'] ?? 0) <= $now) {
            $state = [
                'attempts' => 0,
                'reset_at' => $now + self::DECAY_SECONDS,
            ];
        }

        $attempts = (int) ($state['attempts'] ?? 0);
        $resetAt = (int) ($state['reset_at'] ?? ($now + self::DECAY_SECONDS));

        if ($attempts >= self::MAX_ATTEMPTS) {
            return [
                'allowed' => false,
                'retry_after' => max(1, $resetAt - $now),
                'remaining' => 0,
            ];
        }

        $attempts++;
        Cache::put($key, [
            'attempts' => $attempts,
            'reset_at' => $resetAt,
        ], max(1, $resetAt - $now));

        return [
            'allowed' => true,
            'retry_after' => 0,
            'remaining' => max(0, self::MAX_ATTEMPTS - $attempts),
        ];
    }

    private function key(int $siteId, ?int $memberId, string $ipAddress): string
    {
        $identity = $memberId !== null
            ? 'member:' . $memberId
            : 'ip:' . hash('sha256', $ipAddress);

        return sprintf('public-comments:%d:%s', $siteId, $identity);
    }
}
