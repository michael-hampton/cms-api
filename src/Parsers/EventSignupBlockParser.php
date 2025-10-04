<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class EventSignupBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'event-signup';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'showName' => [new BooleanRule()],
            'showEmail' => [new BooleanRule()],
            'showPhone' => [new BooleanRule()],
            'showCompany' => [new BooleanRule()],
            'showDietaryReqs' => [new BooleanRule()],
            'showAccessibilityReqs' => [new BooleanRule()],
            'submitButtonText' => [new RequiredRule(), new MaxLengthRule(50)],
            'successMessage' => [new MaxLengthRule(500)],
            'recipientEmail' => [new EmailRule()],
            'requireName' => [new BooleanRule()],
            'requireEmail' => [new BooleanRule()],
            'requirePhone' => [new BooleanRule()],
            'requireCompany' => [new BooleanRule()],
            'autoConfirmation' => [new BooleanRule()],
            'trackCapacity' => [new BooleanRule()],
            'maxSignups' => [new MaxLengthRule(10)]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => trim($data['title'] ?? 'Event Registration'),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'showName' => (bool)($data['showName'] ?? true),
            'showEmail' => (bool)($data['showEmail'] ?? true),
            'showPhone' => (bool)($data['showPhone'] ?? false),
            'showCompany' => (bool)($data['showCompany'] ?? false),
            'showDietaryReqs' => (bool)($data['showDietaryReqs'] ?? false),
            'showAccessibilityReqs' => (bool)($data['showAccessibilityReqs'] ?? false),
            'submitButtonText' => $data['submitButtonText'] ?? 'Register Now',
            'successMessage' => $data['successMessage'] ?? 'Registration successful!',
            'recipientEmail' => $data['recipientEmail'] ?? '',
            'requireName' => (bool)($data['requireName'] ?? true),
            'requireEmail' => (bool)($data['requireEmail'] ?? true),
            'requirePhone' => (bool)($data['requirePhone'] ?? false),
            'requireCompany' => (bool)($data['requireCompany'] ?? false),
            'autoConfirmation' => (bool)($data['autoConfirmation'] ?? true),
            'trackCapacity' => (bool)($data['trackCapacity'] ?? false),
            'maxSignups' => (int)($data['maxSignups'] ?? 0),
            'formatted_title' => htmlspecialchars($data['title'] ?? 'Event Registration'),
            'formatted_subtitle' => htmlspecialchars($data['subtitle'] ?? ''),
            'context' => $data['context'] ?? 'default',
        ];
    }

    public function generateHtml(array $parsedData): string
    {
        $context = $parsedData['context'] ?? 'default';

        if ($context === 'sidebar') {
            return $this->generateSidebarHtml($parsedData);
        }

        return $this->generateDefaultHtml($parsedData);
    }

    private function generateDefaultHtml(array $parsedData): string
    {
        $html = "<section class=\"event-signup-section\">";
        $html .= "<div class=\"signup-container\">";

        $html .= "<div class=\"signup-header\">";
        $html .= "<h2>{$parsedData['formatted_title']}</h2>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"signup-subtitle\">{$parsedData['formatted_subtitle']}</p>";
        }
        $html .= "</div>";

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"event-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"event_registration\">";

        if ($parsedData['showName']) {
            $required = $parsedData['requireName'] ? 'required' : '';
            $html .= "<div class=\"form-row\">";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"first_name\" placeholder=\"First Name\" {$required}>";
            $html .= "</div>";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"last_name\" placeholder=\"Last Name\" {$required}>";
            $html .= "</div>";
            $html .= "</div>";
        }

        if ($parsedData['showEmail']) {
            $required = $parsedData['requireEmail'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"email\" name=\"email\" placeholder=\"Email Address\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showPhone']) {
            $required = $parsedData['requirePhone'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone Number\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showCompany']) {
            $required = $parsedData['requireCompany'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"company\" placeholder=\"Company/Organization\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showDietaryReqs']) {
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"dietary_requirements\" placeholder=\"Dietary Requirements (Optional)\" rows=\"3\"></textarea>";
            $html .= "</div>";
        }

        if ($parsedData['showAccessibilityReqs']) {
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

        $html .= "<button type=\"submit\" class=\"btn btn-primary signup-btn\">{$parsedData['submitButtonText']}</button>";
        $html .= "</form>";

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<div class=\"event-signup-sidebar\">";
        $html .= "<h3 class=\"sidebar-signup-title\">{$parsedData['formatted_title']}</h3>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"sidebar-signup-subtitle\">{$parsedData['formatted_subtitle']}</p>";
        }

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"sidebar-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"event_registration\">";

        if ($parsedData['showName']) {
            $required = $parsedData['requireName'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"name\" placeholder=\"Your Name\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showEmail']) {
            $required = $parsedData['requireEmail'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"email\" name=\"email\" placeholder=\"Email Address\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showPhone']) {
            $required = $parsedData['requirePhone'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone\" {$required}>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"btn btn-primary\" style=\"width: 100%\">{$parsedData['submitButtonText']}</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }
}