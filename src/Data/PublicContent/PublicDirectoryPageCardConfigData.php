<?php

declare(strict_types=1);

namespace App\Data\PublicContent;

final readonly class PublicDirectoryPageCardConfigData
{
    public function __construct(
        public bool $showImage,
        public bool $showSummary,
        public bool $showCategories,
        public bool $showTags,
        public bool $showAuthors,
        public bool $showPublishedDate,
        public int $categoryLimit,
        public int $tagLimit,
        public int $authorLimit,
        public int $summaryLength,
    ) {
    }
}
