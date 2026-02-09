<?php

namespace App\Parsers\Dtos;

final class CodeBlockDto extends BaseBlockDto
{
    private const KNOWN_KEYS = ['language', 'code'];

    public function __construct(
        public string $language,
        public string $code
    )
    {
    }

    public static function fromArray(array $data): self
    {
        self::validateKeys($data, self::KNOWN_KEYS);

        $data = self::applyDefaults($data, [
            'language' => 'javascript',
            'code' => ''
        ]);

        return new self(
            strtolower(trim($data['language'])),
            $data['code']
        );
    }

    public function toArray(): array
    {
        return [
            'language' => $this->language,
            'code' => $this->code,
            'language_display' => $this->getLanguageDisplayName(),
            'line_count' => $this->countLines(),
            'char_count' => strlen($this->code),
            'byte_size' => strlen(mb_convert_encoding($this->code, 'UTF-8', 'ISO-8859-1')),
            'formatted_code' => htmlspecialchars($this->code, ENT_QUOTES, 'UTF-8'),
            'has_syntax_highlighting' => $this->supportsSyntaxHighlighting(),
            'file_extension' => $this->getFileExtension(),
            'complexity_score' => $this->calculateComplexityScore(),
            'is_empty' => empty(trim($this->code))
        ];
    }

    public function getLanguageDisplayName(): string
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

        return $displayNames[$this->language] ?? ucfirst($this->language);
    }

    private function countLines(): int
    {
        if (empty(trim($this->code))) {
            return 0;
        }
        return count(explode("\n", $this->code));
    }

    private function supportsSyntaxHighlighting(): bool
    {
        $supportedLanguages = [
            'javascript', 'typescript', 'python', 'java', 'csharp',
            'php', 'ruby', 'go', 'c++', 'html', 'css'
        ];
        return in_array($this->language, $supportedLanguages);
    }

    private function getFileExtension(): string
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

        return $extensions[$this->language] ?? '.txt';
    }

    private function calculateComplexityScore(): int
    {
        if (empty(trim($this->code))) {
            return 0;
        }

        $score = 0;
        $lines = explode("\n", $this->code);

        foreach ($lines as $line) {
            $trimmedLine = trim($line);
            if (empty($trimmedLine) || strpos($trimmedLine, '//') === 0 || strpos($trimmedLine, '#') === 0) {
                continue;
            }

            $score += substr_count($line, '{') + substr_count($line, '}');
            $score += substr_count($line, 'if ') + substr_count($line, 'else');
            $score += substr_count($line, 'for ') + substr_count($line, 'while ');
            $score += substr_count($line, 'function ') + substr_count($line, 'def ');
            $score += substr_count($line, 'class ') + substr_count($line, 'interface ');
        }

        return min($score, 100);
    }

    public function getType(): string
    {
        return 'code';
    }
}