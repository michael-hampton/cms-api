<?php

namespace App\Parsers;

use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\RequiredRule;

class ContactFormBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'contact-form';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'subtitle' => [new MaxLengthRule(500)],
            'showName' => [new BooleanRule()],
            'showEmail' => [new BooleanRule()],
            'showPhone' => [new BooleanRule()],
            'showSubject' => [new BooleanRule()],
            'showMessage' => [new BooleanRule()],
            'showPropertyInterest' => [new BooleanRule()],
            'submitButtonText' => [new RequiredRule(), new MaxLengthRule(50)],
            'successMessage' => [new MaxLengthRule(500)],
            'recipientEmail' => [new EmailRule()],
            'requireName' => [new BooleanRule()],
            'requireEmail' => [new BooleanRule()],
            'requirePhone' => [new BooleanRule()],
            'requireSubject' => [new BooleanRule()],
            'requireMessage' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        return [
            'title' => trim($data['title'] ?? ''),
            'subtitle' => trim($data['subtitle'] ?? ''),
            'showName' => (bool)($data['showName'] ?? true),
            'showEmail' => (bool)($data['showEmail'] ?? true),
            'showPhone' => (bool)($data['showPhone'] ?? false),
            'showSubject' => (bool)($data['showSubject'] ?? false),
            'showMessage' => (bool)($data['showMessage'] ?? true),
            'showPropertyInterest' => (bool)($data['showPropertyInterest'] ?? false),
            'submitButtonText' => $data['submitButtonText'] ?? 'Send',
            'successMessage' => $data['successMessage'] ?? 'Message sent successfully!',
            'recipientEmail' => $data['recipientEmail'] ?? '',
            'requireName' => (bool)($data['requireName'] ?? true),
            'requireEmail' => (bool)($data['requireEmail'] ?? true),
            'requirePhone' => (bool)($data['requirePhone'] ?? false),
            'requireSubject' => (bool)($data['requireSubject'] ?? false),
            'requireMessage' => (bool)($data['requireMessage'] ?? true),
            'formatted_title' => htmlspecialchars($data['title'] ?? ''),
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

    public function generateDefaultHtml(array $parsedData): string
    {
        $html = "<section class=\"contact\" style='margin-top: 20px'>";
        $html .= "<div class=\"contact-container\">";

        // Contact Info Side
        $html .= "<div class=\"contact-info\">";
        $html .= "<h3>Contact Information</h3>";
        $html .= "<p style=\"margin-bottom: 2rem;\">We're here to help with all your property needs. Reach out to us through any of the following channels.</p>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">📍</div>";
        $html .= "<div>";
        $html .= "<h4>Visit Our Office</h4>";
        $html .= "<p>123 Premium Street<br>London, SW1A 1AA</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">📞</div>";
        $html .= "<div>";
        $html .= "<h4>Call Us</h4>";
        $html .= "<p>+44 20 7123 4567<br>Mon-Fri: 9AM-6PM</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">✉️</div>";
        $html .= "<div>";
        $html .= "<h4>Email Us</h4>";
        $html .= "<p>info@premierproperties.co.uk<br>sales@premierproperties.co.uk</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"social-links\">";
        $html .= "<a href=\"#\" class=\"social-link\">📘</a>";
        $html .= "<a href=\"#\" class=\"social-link\">📷</a>";
        $html .= "<a href=\"#\" class=\"social-link\">🐦</a>";
        $html .= "<a href=\"#\" class=\"social-link\">💼</a>";
        $html .= "</div>";

        $html .= "</div>";

        // Contact Form Side
        $html .= "<div class=\"contact-form\">";
        $html .= "<h3>{$parsedData['formatted_title']}</h3>";
        $html .= "<form method=\"POST\" action=\"/contact\">";

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

        if ($parsedData['showEmail'] || $parsedData['showPhone']) {
            $html .= "<div class=\"form-row\">";
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
            $html .= "</div>";
        }

        if ($parsedData['showSubject']) {
            $required = $parsedData['requireSubject'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<select name=\"service\" {$required}>";
            $html .= "<option value=\"\">Select Service</option>";
            $html .= "<option value=\"buying\">Property Buying</option>";
            $html .= "<option value=\"selling\">Property Selling</option>";
            $html .= "<option value=\"renting\">Property Renting</option>";
            $html .= "<option value=\"investment\">Investment Advisory</option>";
            $html .= "<option value=\"valuation\">Property Valuation</option>";
            $html .= "<option value=\"other\">Other Inquiry</option>";
            $html .= "</select>";
            $html .= "</div>";
        }

        if ($parsedData['showMessage']) {
            $required = $parsedData['requireMessage'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"message\" placeholder=\"Tell us about your property needs...\" {$required}></textarea>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"cta-button\" style=\"width: 100%;\">{$parsedData['submitButtonText']}</button>";
        $html .= "</form>";
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</section>";

        return $html;
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<div class=\"contact-form-sidebar\">";

        $html .= "<h3 class=\"sidebar-form-title\">{$parsedData['formatted_title']}</h3>";
        if (!empty($parsedData['subtitle'])) {
            $html .= "<p class=\"sidebar-form-subtitle\">{$parsedData['formatted_subtitle']}</p>";
        }

        $html .= "<form method=\"POST\" action=\"/contact\" class=\"sidebar-contact-form\">";

        // Add property context fields
        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"property_enquiry\">";
        $html .= "<input type=\"hidden\" name=\"property_id\" value=\"" . ($_GET['property_id'] ?? '') . "\">";

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
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone Number\" {$required}>";
            $html .= "</div>";
        }

        if ($parsedData['showMessage']) {
            $required = $parsedData['requireMessage'] ? 'required' : '';
            $placeholder = $parsedData['showPropertyInterest'] ? 'I\'m interested in this property...' : 'Your message...';
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"message\" placeholder=\"{$placeholder}\" {$required}></textarea>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"btn btn-primary\" style=\"width: 100%\">{$parsedData['submitButtonText']}</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }
}