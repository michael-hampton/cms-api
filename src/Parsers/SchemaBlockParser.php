<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredIfRule;
use App\Framework\Validation\Rules\RequiredRule;

class SchemaBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'schema';
    }

    public function getValidationRules(): array
    {
        return [
            'schemaType' => [
                new RequiredRule(),
                new MaxLengthRule(50)
            ],
            'title' => [
                new RequiredIfRule('schemaType', 'how-to'),
                new MaxLengthRule(255)
            ],
            'description' => [
                new MaxLengthRule(1000)
            ],
            'image' => [
                new ArrayRule()
            ],
            'question' => [
                new RequiredIfRule('schemaType', 'question'),
                new MaxLengthRule(255)
            ],
            'answer' => [
                new RequiredIfRule('schemaType', 'question'),
                new MaxLengthRule(2000)
            ],
            'expansion' => [
                new MaxLengthRule(5000)
            ]
        ];
    }

    public function parse(array $data): array
    {
        $schemaType = $data['schemaType'] ?? 'how-to';
        $title = trim($data['title'] ?? '');
        $description = trim($data['description'] ?? '');
        $question = trim($data['question'] ?? '');
        $answer = trim($data['answer'] ?? '');
        $expansion = trim($data['expansion'] ?? '');

        $parsed = [
            'schemaType' => $schemaType,
            'title' => $title,
            'description' => $description,
            'image' => $data['image'] ?? null,
            'question' => $question,
            'answer' => $answer,
            'expansion' => $expansion,
            'showExpansion' => $schemaType === 'question'
        ];

        // Calculate word counts based on schema type
        if ($schemaType === 'question') {
            $parsed['question_word_count'] = str_word_count($question);
            $parsed['answer_word_count'] = str_word_count(strip_tags($answer));
            $parsed['expansion_word_count'] = str_word_count(strip_tags($expansion));
            $parsed['total_word_count'] = $parsed['question_word_count'] + $parsed['answer_word_count'] + $parsed['expansion_word_count'];

            // Format content for display
            $parsed['formatted_answer'] = nl2br(htmlspecialchars($answer));
            $parsed['formatted_expansion'] = nl2br(htmlspecialchars($expansion));
        } else {
            $parsed['title_word_count'] = str_word_count($title);
            $parsed['description_word_count'] = str_word_count(strip_tags($description));
            $parsed['total_word_count'] = $parsed['title_word_count'] + $parsed['description_word_count'];

            // Format content for display
            $parsed['formatted_description'] = nl2br(htmlspecialchars($description));
        }

        return $parsed;
    }

    public function getSupportedSchemaTypes(): array
    {
        return ['how-to', 'question'];
    }

    public function generateHtml(array $parsedData): string
    {
        $html = "<div class=\"schema-block schema-type-{$parsedData['schemaType']}\">";

        if ($parsedData['schemaType'] === 'question') {
            $html .= "<div class=\"schema-question-block\">";
            $html .= "<h3 class=\"schema-question\">{$parsedData['question']}</h3>";
            $html .= "<div class=\"schema-answer\">{$parsedData['formatted_answer']}</div>";

            if ($parsedData['showExpansion'] && !empty($parsedData['expansion'])) {
                $html .= "<div class=\"schema-expansion\">{$parsedData['formatted_expansion']}</div>";
            }

            $html .= "</div>";
        } else {
            $html .= "<div class=\"schema-howto-block\">";

            if (!empty($parsedData['image'])) {
                $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['title']}\" class=\"schema-image\">";
            }

            $html .= "<h3 class=\"schema-title\">{$parsedData['title']}</h3>";

            if (!empty($parsedData['description'])) {
                $html .= "<div class=\"schema-description\">{$parsedData['formatted_description']}</div>";
            }

            $html .= "</div>";
        }

        $html .= "</div>";

        return $html;
    }
}