<?php

namespace App\DTO\OpenCollab;

class ExtractedDocumentContent
{
    public function __construct(
        public readonly ?string $content,
        public readonly string $format,
        public readonly string $status,
        public readonly ?string $error = null,
    ) {
    }
}
