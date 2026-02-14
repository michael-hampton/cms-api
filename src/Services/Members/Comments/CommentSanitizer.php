<?php

namespace App\Services\Members\Comments;

class CommentSanitizer
{
    public function sanitize(string $content): string
    {
        // Remove scripts and potentially harmful HTML
        $content = strip_tags($content, '<p><br><strong><em><a>');

        // Sanitize URLs in links
        $content = preg_replace_callback(
            '/<a\s+href="([^"]+)"/',
            function ($matches) {
                $url = $matches[1];
                if (stripos($url, 'javascript:') === 0) {
                    $url = '#';
                }
                $url = filter_var($url, FILTER_SANITIZE_URL);
                return '<a href="' . $url . '"';
            },
            $content
        );

        return trim($content);
    }
}