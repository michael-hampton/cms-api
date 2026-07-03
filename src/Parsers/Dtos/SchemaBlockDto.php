<?php

namespace App\Parsers\Dtos;

use InvalidArgumentException;

final class SchemaBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = [
        'schemaType'
    ];

    private const ALLOWED_SCHEMA_TYPES = ['how-to', 'question'];
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_DESCRIPTION_LENGTH = 1000;
    private const MAX_QUESTION_LENGTH = 255;
    private const MAX_ANSWER_LENGTH = 2000;
    private const MAX_EXPANSION_LENGTH = 5000;

    public function __construct(
        public string $schemaType,
        public string $title,
        public string $description,
        public ?array $image,
        public string $question,
        public string $answer,
        public string $expansion,
        public bool   $showExpansion
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'schemaType' => 'how-to',
            'title' => '',
            'description' => '',
            'image' => null,
            'question' => '',
            'answer' => '',
            'expansion' => '',
            'showExpansion' => false
        ]);

        $schemaType = self::validateEnum(
            $data['schemaType'],
            self::ALLOWED_SCHEMA_TYPES,
            'how-to',
            'schemaType'
        );

        // Validate based on schema type
        if ($schemaType === 'how-to') {
            if (empty(trim($data['title']))) {
                throw new InvalidArgumentException('Title is required for how-to schema');
            }
        } elseif ($schemaType === 'question') {
            if (empty(trim($data['question']))) {
                throw new InvalidArgumentException('Question is required for question schema');
            }
            if (empty(trim($data['answer']))) {
                throw new InvalidArgumentException('Answer is required for question schema');
            }
        }

        $title = self::truncate(trim($data['title']), self::MAX_TITLE_LENGTH);
        $description = self::truncate(trim($data['description']), self::MAX_DESCRIPTION_LENGTH);
        $question = self::truncate(trim($data['question']), self::MAX_QUESTION_LENGTH);
        $answer = self::truncate(trim($data['answer']), self::MAX_ANSWER_LENGTH);
        $expansion = self::truncate(trim($data['expansion']), self::MAX_EXPANSION_LENGTH);

        return new self(
            $schemaType,
            $title,
            $description,
            $data['image'] ?: null,
            $question,
            $answer,
            $expansion,
            $schemaType === 'question'
        );
    }

    private static function truncate(string $value, int $maxLength): string
    {
        if (strlen($value) > $maxLength) {
            if (self::$debugMode) {
                error_log("WARNING: Value exceeds max length, truncating");
            }
            return substr($value, 0, $maxLength);
        }
        return $value;
    }

    public function toArray(): array
    {
        $data = [
            'schemaType' => $this->schemaType,
            'title' => $this->title,
            'description' => $this->description,
            'image' => $this->image,
            'question' => $this->question,
            'answer' => $this->answer,
            'expansion' => $this->expansion,
            'showExpansion' => $this->showExpansion
        ];

        if ($this->schemaType === 'question') {
            $data['question_word_count'] = str_word_count($this->question);
            $data['answer_word_count'] = str_word_count(strip_tags($this->answer));
            $data['expansion_word_count'] = str_word_count(strip_tags($this->expansion));
        } else {
            $data['title_word_count'] = str_word_count($this->title);
            $data['description_word_count'] = str_word_count(strip_tags($this->description));
        }

        return $data;
    }

    public function getType(): string
    {
        return 'schema';
    }
}