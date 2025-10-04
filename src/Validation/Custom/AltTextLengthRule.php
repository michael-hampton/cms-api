<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class AltTextLengthRule extends BaseValidationRule
{
    private $minLength = 2;
    private $maxLength = 125; // Standard recommendation for alt text
    private $minWords = 1;
    private $maxWords = 15;

    public function __construct($minLength = 2, $maxLength = 125)
    {
        $this->minLength = $minLength;
        $this->maxLength = $maxLength;
    }

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return false; // Alt text is required
        }

        $value = trim($value);
        $length = strlen($value);
        $wordCount = str_word_count($value);

        // Check character length
        if ($length < $this->minLength || $length > $this->maxLength) {
            return false;
        }

        // Check word count
        if ($wordCount < $this->minWords || $wordCount > $this->maxWords) {
            return false;
        }

        // Check for quality - avoid common bad practices
        if ($this->hasRedundantPhrases($value)) {
            return false;
        }

        // Alt text should not be just whitespace or special characters
        if (!preg_match('/[a-zA-Z0-9]/', $value)) {
            return false;
        }

        return true;
    }

    private function hasRedundantPhrases(string $value): bool
    {
        $redundantPhrases = [
            'image of',
            'picture of',
            'photo of',
            'graphic of',
            'screenshot of',
            'illustration of',
            'image showing',
            'picture showing',
            'image depicts',
            'image contains'
        ];

        $lowerValue = strtolower($value);

        foreach ($redundantPhrases as $phrase) {
            if (strpos($lowerValue, $phrase) === 0) {
                return true;
            }
        }

        return false;
    }

    protected function getDefaultMessage(): string
    {
        return "Alt text must be between {$this->minLength} and {$this->maxLength} characters, " .
            "contain {$this->minWords} to {$this->maxWords} words, and avoid redundant phrases like 'image of'.";
    }

    public function getMinLength(): int
    {
        return $this->minLength;
    }

    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function getMinWords(): int
    {
        return $this->minWords;
    }

    public function getMaxWords(): int
    {
        return $this->maxWords;
    }

    public function setWordLimits(int $minWords, int $maxWords): void
    {
        $this->minWords = $minWords;
        $this->maxWords = $maxWords;
    }
}