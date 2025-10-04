<?php

namespace App\Validation\Custom;

use App\Framework\Validation\Rules\BaseValidationRule;

class ProgrammingLanguageRule extends BaseValidationRule
{
    private array $allowedLanguages = [
        'javascript',
        'typescript',
        'python',
        'java',
        'csharp',
        'php',
        'ruby',
        'go',
        'c++',
        'html',
        'css'
    ];

    public function validate($value, array $data = []): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return in_array($value, $this->allowedLanguages, true);
    }

    protected function getDefaultMessage(): string
    {
        return 'The programming language must be one of: ' . implode(', ', $this->allowedLanguages);
    }
}