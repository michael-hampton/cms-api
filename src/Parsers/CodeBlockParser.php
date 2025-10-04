<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Validation\Custom\ProgrammingLanguageRule;

class CodeBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'code';
    }

    public function getValidationRules(): array
    {
        return [
            'language' => [
                new RequiredRule(),
                new ProgrammingLanguageRule()
            ],
            'code' => [
                new RequiredRule(),
                new MaxLengthRule(50000) // 50KB max code length
            ]
        ];
    }

    public function parse(array $data): array
    {
        $language = strtolower(trim($data['language'] ?? 'javascript'));
        $code = $data['code'] ?? '';

        return [
            'language' => $language,
            'code' => $code,
            'language_display' => $this->getLanguageDisplayName($language),
            'line_count' => $this->countLines($code),
            'char_count' => strlen($code),
            'byte_size' => strlen(mb_convert_encoding($code, 'UTF-8', 'ISO-8859-1')),
            'formatted_code' => htmlspecialchars($code, ENT_QUOTES, 'UTF-8'),
            'has_syntax_highlighting' => $this->supportsSyntaxHighlighting($language),
            'file_extension' => $this->getFileExtension($language),
            'complexity_score' => $this->calculateComplexityScore($code),
            'is_empty' => empty(trim($code))
        ];
    }

    private function getLanguageDisplayName(string $language): string
    {
        $displayNames = [
            'javascript' => 'JavaScript',
            'typescript' => 'TypeScript',
            'python' => 'Python',
            'java' => 'Java',
            'csharp' => 'C#',
            'php' => 'PHP',
            'ruby' => 'Ruby',
            'go' => 'Go',
            'c++' => 'C++',
            'html' => 'HTML',
            'css' => 'CSS'
        ];

        return $displayNames[$language] ?? ucfirst($language);
    }

    private function countLines(string $code): int
    {
        if (empty(trim($code))) {
            return 0;
        }
        return count(explode("\n", $code));
    }

    private function supportsSyntaxHighlighting(string $language): bool
    {
        $supportedLanguages = [
            'javascript', 'typescript', 'python', 'java', 'csharp',
            'php', 'ruby', 'go', 'c++', 'html', 'css'
        ];
        return in_array($language, $supportedLanguages);
    }

    private function getFileExtension(string $language): string
    {
        $extensions = [
            'javascript' => '.js',
            'typescript' => '.ts',
            'python' => '.py',
            'java' => '.java',
            'csharp' => '.cs',
            'php' => '.php',
            'ruby' => '.rb',
            'go' => '.go',
            'c++' => '.cpp',
            'html' => '.html',
            'css' => '.css'
        ];

        return $extensions[$language] ?? '.txt';
    }

    private function calculateComplexityScore(string $code): int
    {
        if (empty(trim($code))) {
            return 0;
        }

        $score = 0;
        $lines = explode("\n", $code);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine) || strpos($trimmedLine, '//') === 0 || strpos($trimmedLine, '#') === 0) {
                continue; // Skip empty lines and comments
            }

            // Add points for various complexity indicators
            $score += substr_count($line, '{') + substr_count($line, '}');
            $score += substr_count($line, 'if ') + substr_count($line, 'else');
            $score += substr_count($line, 'for ') + substr_count($line, 'while ');
            $score += substr_count($line, 'function ') + substr_count($line, 'def ');
            $score += substr_count($line, 'class ') + substr_count($line, 'interface ');
        }

        return min($score, 100); // Cap at 100 for reasonable scoring
    }

    public function generateHtml(array $parsedData): string
    {
        return '';
    }
}