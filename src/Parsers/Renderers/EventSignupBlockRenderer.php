<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\EventSignupBlockDto;

class EventSignupBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        $context = $dto->context ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($dto);
        }

        return $this->generateDefaultHtml($dto);
    }

    private function generateSidebarHtml(BlockDtoInterface $dto): string
    {
        $html = "<div class=\"event-signup-sidebar\">";
        $html .= "<h3 class=\"sidebar-signup-title\">{$dto->title}</h3>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"sidebar-signup-subtitle\">{$dto->subtitle}</p>";
        }

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"sidebar-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"event_registration\">";

        if ($dto->showName) {
            $required = $dto->requireName ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"name\" placeholder=\"Your Name\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showEmail) {
            $required = $dto->requireEmail ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"email\" name=\"email\" placeholder=\"Email Address\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showPhone) {
            $required = $dto->requirePhone ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone\" {$required}>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"btn btn-primary\" style=\"width: 100%\">{$dto->submitButtonText}</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }

    private function generateDefaultHtml(EventSignupBlockDto $dto): string
    {
        $html = "<section class=\"event-signup-section\">";
        $html .= "<div class=\"signup-container\">";

        $html .= "<div class=\"signup-header\">";
        $html .= "<h2>{$dto->title}</h2>";

        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"signup-subtitle\">{$dto->subtitle}</p>";
        }
        $html .= "</div>";

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"event-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"event_registration\">";

        if ($dto->showName) {
            $required = $dto->requireName ? 'required' : '';
            $html .= "<div class=\"form-row\">";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"first_name\" placeholder=\"First Name\" {$required}>";
            $html .= "</div>";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"last_name\" placeholder=\"Last Name\" {$required}>";
            $html .= "</div>";
            $html .= "</div>";
        }

        if ($dto->showEmail) {
            $required = $dto->requireEmail ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"email\" name=\"email\" placeholder=\"Email Address\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showPhone) {
            $required = $dto->requirePhone ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone Number\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showCompany) {
            $required = $dto->requireCompany ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"company\" placeholder=\"Company/Organization\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showDietaryReqs) {
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"dietary_requirements\" placeholder=\"Dietary Requirements (Optional)\" rows=\"3\"></textarea>";
            $html .= "</div>";
        }

        if ($dto->showAccessibilityReqs) {
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"accessibility_requirements\" placeholder=\"Accessibility Requirements (Optional)\" rows=\"3\"></textarea>";
            $html .= "</div>";
        }

        $html .= "<div class=\"form-group\">";
        $html .= "<label class=\"checkbox-label\">";
        $html .= "<input type=\"checkbox\" name=\"newsletter\" value=\"1\"> I'd like to receive updates about future events";
        $html .= "</label>";
        $html .= "</div>";

        $html .= "<div class=\"form-group\">";
        $html .= "<label class=\"checkbox-label\">";
        $html .= "<input type=\"checkbox\" name=\"terms\" value=\"1\" required> I agree to the terms and conditions";
        $html .= "</label>";
        $html .= "</div>";

        $html .= "<button type=\"submit\" class=\"btn btn-primary signup-btn\">{$dto->submitButtonText}</button>";
        $html .= "</form>";

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    protected function getSupportedType(): string
    {
        return 'event-signup';
    }
}