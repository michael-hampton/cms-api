<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;

class PersonBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        $displayType = $dto->displayType ?? 'contact';
        $contextClass = $dto->context === 'sidebar' ? ' person-sidebar' : '';

        if ($displayType === 'contact') {
            // Contact info layout
            $html = "<div class=\"person-block person-display-contact{$contextClass}\">";
            $html .= "<div class=\"contact-info\">";
            $html .= "<h3>Contact Information</h3>";

            if (!empty($dto->phone)) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">📞</span>";
                $html .= "<div>";
                $html .= "<strong>Phone</strong><br>";
                $html .= "<a href=\"tel:{$dto->phone}\">{$dto->phone}</a>";
                $html .= "</div>";
                $html .= "</div>";
            }

            if (!empty($dto->email)) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">✉️</span>";
                $html .= "<div>";
                $html .= "<strong>Email</strong><br>";
                $html .= "<a href=\"mailto:{$dto->email}\">$dto->email}</a>";
                $html .= "</div>";
                $html .= "</div>";
            }

            if (!empty($dto->address)) {
                $html .= "<div class=\"contact-item\">";
                $html .= "<span class=\"contact-icon\">📍</span>";
                $html .= "<div>";
                $html .= "<strong>Address</strong><br>";
                $html .= $dto->address;
                $html .= "</div>";
                $html .= "</div>";
            }

            $html .= "</div>";
            $html .= "</div>";

            return $html;
        }

        // Default to profile layout for any other displayType or if not specified
        $html = "<div class=\"person-block person-display-profile{$contextClass}\">";

        if ($dto->image) {
            $html .= "<div class=\"person-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"{$dto->name}\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"person-info\">";
        $html .= "<h3 class=\"person-name\">{$dto->name}</h3>";

        if (!empty($dto->role)) {
            $html .= "<div class=\"person-role\">{$dto->role}</div>";
        }

        if (!empty($dto->bio)) {
            $html .= "<div class=\"person-bio\">{$dto->bio}</div>";
        }

        if (!empty($dto->email) || !empty($dto->phone)) {
            $html .= "<div class=\"person-contact\">";
            if (!empty($dto->email)) {
                $html .= "<a href=\"mailto:{$dto->email}\" class=\"contact-link\">Email</a>";
            }
            if (!empty($dto->phone)) {
                $html .= "<a href=\"tel:{$dto->phone}\" class=\"contact-link\">Call</a>";
            }
            $html .= "</div>";
        }

        $html .= "</div>";
        $html .= "</div>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'person';
    }
}