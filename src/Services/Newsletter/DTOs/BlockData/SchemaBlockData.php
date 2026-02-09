<?php

namespace App\Services\Newsletter\DTOs\BlockData;

class SchemaBlockData extends BaseBlockData
{
    public function __construct(
        public readonly string  $schemaType,
        public readonly ?string $title,
        public readonly ?string $description,
        public readonly ?string $question,
        public readonly ?string $answer,
        public readonly ?string $expansion,
        public readonly ?array  $image
    )
    {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            schemaType: $data['schemaType'] ?? 'how-to',
            title: $data['title'] ?? null,
            description: $data['description'] ?? null,
            question: $data['question'] ?? null,
            answer: $data['answer'] ?? null,
            expansion: $data['expansion'] ?? null,
            image: $data['image'] ?? null
        );
    }
}