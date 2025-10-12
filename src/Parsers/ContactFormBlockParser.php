<?php

namespace App\Parsers;

use App\Framework\Support\SiteContext;
use App\Framework\Validation\Rules\ArrayRule;
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
            'requireMessage' => [new BooleanRule()],
            'override_email' => [new EmailRule()],
            'override_phone' => [new MaxLengthRule(50)],
            'override_address' => [new ArrayRule()],
            'override_social' => [new ArrayRule()]
        ];
    }

    public function parse(array $data): array
    {
        $site = SiteContext::get();
        $contactInfo = $site ? $site->getContactInfo() : $this->getDefaultContactInfo();

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
            'contact_info' => [
                'email' => $data['override_email'] ?? $contactInfo['email'],
                'phone' => $data['override_phone'] ?? $contactInfo['phone'],
                'address' => $data['override_address'] ?? $contactInfo['address'],
                'social' => $data['override_social'] ?? $contactInfo['social']
            ]
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
        $contactInfo = $parsedData['contact_info'];

        $html = "<section class=\"contact\">";
        $html .= "<div class=\"contact-container\">";

        // Contact Info Side
        $html .= "<div class=\"contact-info\">";
        $html .= "<h3>Contact Information</h3>";
        $html .= "<p>We're here to help with all your cooking questions. Reach out to us through any of the following channels.</p>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">📍</div>";
        $html .= "<div>";
        $html .= "<h4>Visit Our Studio</h4>";
        $html .= "<p>{$contactInfo['address']['line1']}<br>";
        if (!empty($contactInfo['address']['line2'])) {
            $html .= "{$contactInfo['address']['line2']}<br>";
        }
        $html .= "{$contactInfo['address']['city']}, {$contactInfo['address']['postcode']}</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">📞</div>";
        $html .= "<div>";
        $html .= "<h4>Call Us</h4>";
        $html .= "<p>{$contactInfo['phone']}<br>Mon-Fri: 9AM-6PM</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">✉️</div>";
        $html .= "<div>";
        $html .= "<h4>Email Us</h4>";
        $html .= "<p>{$contactInfo['email']}</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"social-links\">";
        if (!empty($contactInfo['social']['facebook'])) {
            $html .= "<a href=\"{$contactInfo['social']['facebook']}\" class=\"social-link\">📘</a>";
        }
        if (!empty($contactInfo['social']['instagram'])) {
            $html .= "<a href=\"{$contactInfo['social']['instagram']}\" class=\"social-link\">📷</a>";
        }
        if (!empty($contactInfo['social']['twitter'])) {
            $html .= "<a href=\"{$contactInfo['social']['twitter']}\" class=\"social-link\">🐦</a>";
        }
        if (!empty($contactInfo['social']['linkedin'])) {
            $html .= "<a href=\"{$contactInfo['social']['linkedin']}\" class=\"social-link\">💼</a>";
        }
        $html .= "</div>";

        $html .= "</div>";

        // Contact Form Side
        $html .= "<div class=\"contact-form\">";
        $html .= "<h3>{$parsedData['formatted_title']}</h3>";

        if (!empty($parsedData['subtitle'])) {
            $html .= "<p>{$parsedData['formatted_subtitle']}</p>";
        }

        $html .= "<form method=\"POST\" action=\"/contact\">";

        if ($parsedData['showName']) {
            $required = $parsedData['requireName'] ? 'required' : '';
            $html .= "<div class=\"form-row\">";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"first_name\" class=\"form-input\" placeholder=\"First Name\" {$required}>";
            $html .= "</div>";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"last_name\" class=\"form-input\" placeholder=\"Last Name\" {$required}>";
            $html .= "</div>";
            $html .= "</div>";
        }

        if ($parsedData['showEmail'] || $parsedData['showPhone']) {
            $html .= "<div class=\"form-row\">";
            if ($parsedData['showEmail']) {
                $required = $parsedData['requireEmail'] ? 'required' : '';
                $html .= "<div class=\"form-group\">";
                $html .= "<input type=\"email\" name=\"email\" class=\"form-input\" placeholder=\"Email Address\" {$required}>";
                $html .= "</div>";
            }
            if ($parsedData['showPhone']) {
                $required = $parsedData['requirePhone'] ? 'required' : '';
                $html .= "<div class=\"form-group\">";
                $html .= "<input type=\"tel\" name=\"phone\" class=\"form-input\" placeholder=\"Phone Number\" {$required}>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        if ($parsedData['showSubject']) {
            $required = $parsedData['requireSubject'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<select name=\"subject\" class=\"form-select\" {$required}>";
            $html .= "<option value=\"\">Select Subject</option>";
            $html .= "<option value=\"recipe\">Recipe Question</option>";
            $html .= "<option value=\"product\">Product Inquiry</option>";
            $html .= "<option value=\"event\">Cooking Class</option>";
            $html .= "<option value=\"other\">Other</option>";
            $html .= "</select>";
            $html .= "</div>";
        }


        if ($parsedData['showMessage']) {
            $required = $parsedData['requireMessage'] ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"message\" class=\"form-textarea\" placeholder=\"Your message...\" {$required}></textarea>";
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

    private function getDefaultContactInfo(): array
    {
        return [
            'email' => 'hello@example.com',
            'phone' => '+44 20 7123 4567',
            'address' => [
                'line1' => '123 Example Street',
                'line2' => '',
                'city' => 'London',
                'postcode' => 'SW1A 1AA',
                'country' => 'UK'
            ],
            'social' => [
                'facebook' => '#',
                'instagram' => '#',
                'twitter' => '#',
                'linkedin' => '#'
            ]
        ];
    }
}