<?php

namespace App\Parsers\Renderers;

use App\Framework\Support\SiteContext;
use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\ContactFormBlockDto;

class ContactFormBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof ContactFormBlockDto) {
            throw new \InvalidArgumentException('Expected ContactFormBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(ContactFormBlockDto $dto): string
    {
        $html = "<div class=\"contact-form-sidebar\">";

        $html .= "<h3 class=\"sidebar-form-title\">" . $this->escape($dto->title) . "</h3>";
        if (!empty($dto->subtitle)) {
            $html .= "<p class=\"sidebar-form-subtitle\">" . $this->escape($dto->subtitle) . "</p>";
        }

        $html .= "<form method=\"POST\" action=\"/contact\" class=\"sidebar-contact-form\">";

        $html .= "<input type=\"hidden\" name=\"form_type\" value=\"property_enquiry\">";
        $html .= "<input type=\"hidden\" name=\"property_id\" value=\"" . ($_GET['property_id'] ?? '') . "\">";

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
            $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone Number\" {$required}>";
            $html .= "</div>";
        }

        if ($dto->showMessage) {
            $required = $dto->requireMessage ? 'required' : '';
            $placeholder = $dto->showPropertyInterest ? 'I\'m interested in this property...' : 'Your message...';
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"message\" placeholder=\"{$placeholder}\" {$required}></textarea>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"btn btn-primary\" style=\"width: 100%\">" . $this->escape($dto->submitButtonText) . "</button>";
        $html .= "</form>";

        $html .= "</div>";

        return $html;
    }

    private function renderDefault(ContactFormBlockDto $dto): string
    {
        $site = SiteContext::get();

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
        $html .= "<p>{$dto->contactInfo['address']['line1']}<br>";
        if (!empty($dto->contactInfo['address']['line2'])) {
            $html .= "{$dto->contactInfo['address']['line2']}<br>";
        }
        $html .= "{$dto->contactInfo['address']['city']}, {$dto->contactInfo['address']['postcode']}</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">📞</div>";
        $html .= "<div>";
        $html .= "<h4>Call Us</h4>";
        $html .= "<p>{$dto->contactInfo['phone']}<br>Mon-Fri: 9AM-6PM</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"contact-item\">";
        $html .= "<div class=\"contact-icon\">✉️</div>";
        $html .= "<div>";
        $html .= "<h4>Email Us</h4>";
        $html .= "<p>{$dto->contactInfo['email']}</p>";
        $html .= "</div>";
        $html .= "</div>";

        $html .= "<div class=\"social-links\">";
        if (!empty($dto->contactInfo['social']['facebook'])) {
            $html .= "<a href=\"{$dto->contactInfo['social']['facebook']}\" class=\"social-link\">📘</a>";
        }
        if (!empty($dto->contactInfo['social']['instagram'])) {
            $html .= "<a href=\"{$dto->contactInfo['social']['instagram']}\" class=\"social-link\">📷</a>";
        }
        if (!empty($dto->contactInfo['social']['twitter'])) {
            $html .= "<a href=\"{$dto->contactInfo['social']['twitter']}\" class=\"social-link\">🐦</a>";
        }
        if (!empty($dto->contactInfo['social']['linkedin'])) {
            $html .= "<a href=\"{$dto->contactInfo['social']['linkedin']}\" class=\"social-link\">💼</a>";
        }
        $html .= "</div>";

        $html .= "</div>";

        // Contact Form Side
        $html .= "<div class=\"contact-form\">";
        $html .= "<h3>" . $this->escape($dto->title) . "</h3>";

        if (!empty($dto->subtitle)) {
            $html .= "<p>" . $this->escape($dto->subtitle) . "</p>";
        }

        $html .= "<form method=\"POST\" action=\"/contact\">";

        if ($dto->showName) {
            $required = $dto->requireName ? 'required' : '';
            $html .= "<div class=\"form-row\">";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"first_name\" class=\"form-input\" placeholder=\"First Name\" {$required}>";
            $html .= "</div>";
            $html .= "<div class=\"form-group\">";
            $html .= "<input type=\"text\" name=\"last_name\" class=\"form-input\" placeholder=\"Last Name\" {$required}>";
            $html .= "</div>";
            $html .= "</div>";
        }

        if ($dto->showEmail || $dto->showPhone) {
            $html .= "<div class=\"form-row\">";
            if ($dto->showEmail) {
                $required = $dto->requireEmail ? 'required' : '';
                $html .= "<div class=\"form-group\">";
                $html .= "<input type=\"email\" name=\"email\" class=\"form-input\" placeholder=\"Email Address\" {$required}>";
                $html .= "</div>";
            }
            if ($dto->showPhone) {
                $required = $dto->requirePhone ? 'required' : '';
                $html .= "<div class=\"form-group\">";
                $html .= "<input type=\"tel\" name=\"phone\" class=\"form-input\" placeholder=\"Phone Number\" {$required}>";
                $html .= "</div>";
            }
            $html .= "</div>";
        }

        if ($dto->showSubject) {
            $required = $dto->requireSubject ? 'required' : '';
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

        if ($dto->showMessage) {
            $required = $dto->requireMessage ? 'required' : '';
            $html .= "<div class=\"form-group\">";
            $html .= "<textarea name=\"message\" class=\"form-textarea\" placeholder=\"Your message...\" {$required}></textarea>";
            $html .= "</div>";
        }

        $html .= "<button type=\"submit\" class=\"cta-button\" style=\"width: 100%;\">" . $this->escape($dto->submitButtonText) . "</button>";
        $html .= "</form>";
        $html .= "</div>";

        $html .= "</div>";
        $html .= "</section>";

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

    protected function getSupportedType(): string
    {
        return 'contact-form';
    }
}