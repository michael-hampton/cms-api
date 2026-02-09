<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\CodeBlockDto;

class CodeBlockRenderer extends BaseBlockRenderer
{
    protected function getSupportedType(): string
    {
        return 'code';
    }

    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof CodeBlockDto) {
            return '';
        }

        $html = "<div class=\"code-block code-language-{$dto->language}\">";
        $html .= "<div class=\"code-header\">";
        $html .= "<span class=\"code-language\">{$dto->getLanguageDisplayName()}</span>";
        $html .= "<button class=\"code-copy-btn\" onclick=\"copyCode(this)\">Copy</button>";
        $html .= "</div>";

        $html .= "<pre class=\"code-pre\"><code class=\"language-{$dto->language}\">";
        $html .= $this->escape($dto->code);
        $html .= "</code></pre>";

        $html .= "</div>";

        return $html;
    }
}