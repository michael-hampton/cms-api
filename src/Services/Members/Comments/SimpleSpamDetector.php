<?php

namespace App\Services\Members\Comments;

use App\DTO\Comments\CreateCommentDTO;
use App\Services\Members\Comments\Contracts\SpamDetectionInterface;

class SimpleSpamDetector implements SpamDetectionInterface
{
    private const SPAM_KEYWORDS = [
        'viagra', 'cialis', 'casino', 'lottery', 'prize'
    ];

    private const MAX_LINKS = 3;

    public function isSpam(CreateCommentDTO $dto): bool
    {
        $content = strtolower($dto->content);

        // Check for spam keywords
        foreach (self::SPAM_KEYWORDS as $keyword) {
            if (str_contains($content, $keyword)) {
                return true;
            }
        }

        // Check for excessive links
        $linkCount = substr_count($content, 'http://') + substr_count($content, 'https://');
        if ($linkCount > self::MAX_LINKS) {
            return true;
        }

        return false;
    }
}