<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\AgentProfileBlockDto;
use App\Parsers\Dtos\BlockDtoInterface;

class AgentProfileBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof AgentProfileBlockDto) {
            throw new \InvalidArgumentException('Expected AgentProfileBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(AgentProfileBlockDto $dto): string
    {
        $html = "<div class=\"agent-profile-sidebar\">";

        if (!empty($dto->profileImageUrl)) {
            $html .= "<div class=\"sidebar-agent-image\">";
            $html .= "<img src=\"{$dto->profileImageUrl}\" alt=\"" . $this->escape($dto->name) . "\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"sidebar-agent-info\">";
        $html .= "<h4 class=\"sidebar-agent-name\">" . $this->escape($dto->name) . "</h4>";

        if (!empty($dto->title)) {
            $html .= "<div class=\"sidebar-agent-title\">" . $this->escape($dto->title) . "</div>";
        }

        if (!empty($dto->experience)) {
            $html .= "<div class=\"sidebar-agent-experience\">{$dto->experience} experience</div>";
        }

        $html .= "<div class=\"sidebar-agent-contact\">";
        if (!empty($dto->phone)) {
            $html .= "<a href=\"tel:{$dto->phone}\" class=\"sidebar-contact-btn\">";
            $html .= "<span class=\"contact-icon\">📞</span> Call {$dto->phone}";
            $html .= "</a>";
        }
        if (!empty($dto->email)) {
            $html .= "<a href=\"mailto:{$dto->email}\" class=\"sidebar-contact-btn\">";
            $html .= "<span class=\"contact-icon\">✉️</span> Email Agent";
            $html .= "</a>";
        }
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    private function renderDefault(AgentProfileBlockDto $dto): string
    {
        $html = "<div class=\"agent-profile-block\">";

        if (!empty($dto->profileImageUrl)) {
            $html .= "<div class=\"agent-image\">";
            $html .= "<img src=\"{$dto->profileImageUrl}\" alt=\"" . $this->escape($dto->name) . "\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"agent-info\">";
        $html .= "<h3 class=\"agent-name\">" . $this->escape($dto->name) . "</h3>";

        if (!empty($dto->title)) {
            $html .= "<div class=\"agent-title\">" . $this->escape($dto->title) . "</div>";
        }

        if (!empty($dto->bio)) {
            $html .= "<div class=\"agent-bio\">" . $this->escapeWithBreaks($dto->bio) . "</div>";
        }

        $html .= "<div class=\"agent-contact\">";
        if (!empty($dto->phone)) {
            $html .= "<a href=\"tel:{$dto->phone}\" class=\"contact-link\">{$dto->phone}</a>";
        }
        if (!empty($dto->email)) {
            $html .= "<a href=\"mailto:{$dto->email}\" class=\"contact-link\">{$dto->email}</a>";
        }
        $html .= "</div>";

        if (!empty($dto->socialMedia)) {
            $html .= "<div class=\"agent-social\">";
            foreach ($dto->socialMedia as $platform => $url) {
                if (!empty($url)) {
                    $html .= "<a href=\"{$url}\" target=\"_blank\" class=\"social-link\">{$platform}</a>";
                }
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'agent-profile';
    }
}