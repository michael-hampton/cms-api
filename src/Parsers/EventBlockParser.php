<?php
// App/Parsers/EventBlockParser.php

namespace App\Parsers;

use App\Framework\Validation\Rules\ArrayRule;
use App\Framework\Validation\Rules\BooleanRule;
use App\Framework\Validation\Rules\DateRule;
use App\Framework\Validation\Rules\EmailRule;
use App\Framework\Validation\Rules\MaxLengthRule;
use App\Framework\Validation\Rules\MinRule;
use App\Framework\Validation\Rules\RequiredRule;
use App\Framework\Validation\Rules\UrlRule;

class EventBlockParser extends BaseBlockParser
{
    public function getType(): string
    {
        return 'event';
    }

    public function getValidationRules(): array
    {
        return [
            'title' => [new RequiredRule(), new MaxLengthRule(255)],
            'description' => [new MaxLengthRule(2000)],
            'startDate' => [new RequiredRule(), new DateRule()],
            'endDate' => [new DateRule()],
            'startTime' => [new MaxLengthRule(10)],
            'endTime' => [new MaxLengthRule(10)],
            'location' => [new MaxLengthRule(500)],
            'address' => [new MaxLengthRule(500)],
            'mapUrl' => [new UrlRule()],
            'ticketPrice' => [new MinRule(0)],
            'currency' => [new MaxLengthRule(10)],
            'ticketUrl' => [new UrlRule()],
            'capacity' => [new MinRule(1)],
            'organizerName' => [new MaxLengthRule(255)],
            'organizerEmail' => [new EmailRule()],
            'organizerPhone' => [new MaxLengthRule(20)],
            'category' => [new MaxLengthRule(100)],
            'image' => [new ArrayRule()],
            'showSignupForm' => [new BooleanRule()],
            'featured' => [new BooleanRule()]
        ];
    }

    public function parse(array $data): array
    {
        $startDate = $data['startDate'] ?? '';
        $endDate = $data['endDate'] ?? $startDate;
        $startTime = $data['startTime'] ?? '';
        $endTime = $data['endTime'] ?? '';

        return [
            'title' => trim($data['title'] ?? ''),
            'description' => trim($data['description'] ?? ''),
            'startDate' => $startDate,
            'endDate' => $endDate,
            'startTime' => $startTime,
            'endTime' => $endTime,
            'location' => trim($data['location'] ?? ''),
            'address' => trim($data['address'] ?? ''),
            'mapUrl' => $data['mapUrl'] ?? '',
            'ticketPrice' => (float)($data['ticketPrice'] ?? 0),
            'currency' => $data['currency'] ?? '£',
            'ticketUrl' => $data['ticketUrl'] ?? '',
            'capacity' => (int)($data['capacity'] ?? 0),
            'organizerName' => trim($data['organizerName'] ?? ''),
            'organizerEmail' => $data['organizerEmail'] ?? '',
            'organizerPhone' => $data['organizerPhone'] ?? '',
            'category' => trim($data['category'] ?? ''),
            'image' => $data['image'] ?? '',
            'showSignupForm' => (bool)($data['showSignupForm'] ?? false),
            'featured' => (bool)($data['featured'] ?? false),
            'formatted_description' => nl2br(htmlspecialchars($data['description'] ?? '')),
            'formatted_start_datetime' => $this->formatDateTime($startDate, $startTime),
            'formatted_end_datetime' => $this->formatDateTime($endDate, $endTime),
            'is_free' => ((float)($data['ticketPrice'] ?? 0)) == 0,
            'is_multi_day' => $startDate !== $endDate && !empty($endDate),
            'has_time' => !empty($startTime),
            'context' => $data['context'] ?? 'default',
        ];
    }

    private function formatDateTime(string $date, string $time = ''): string
    {
        if (empty($date)) return '';

        $dateObj = new \DateTime($date);
        $formatted = $dateObj->format('F j, Y');

        if (!empty($time)) {
            $formatted .= ' at ' . $time;
        }

        return $formatted;
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
        $html = "<section class=\"event-block\">";

        if (!empty($parsedData['image'])) {
            $html .= "<div class=\"event-image\">";
            $html .= "<img src=\"{$parsedData['image']['src']}\" alt=\"{$parsedData['title']}\" class=\"event-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"event-content\">";

        $html .= "<div class=\"event-header\">";
        $html .= "<h2 class=\"event-title\">{$parsedData['title']}</h2>";

        if (!empty($parsedData['category'])) {
            $html .= "<span class=\"event-category\">{$parsedData['category']}</span>";
        }
        $html .= "</div>";

        $html .= "<div class=\"event-details\">";

        // Date and Time
        $html .= "<div class=\"event-detail-item\">";
        $html .= "<div class=\"detail-icon\">📅</div>";
        $html .= "<div class=\"detail-content\">";
        $html .= "<strong>Date & Time:</strong><br>";
        $html .= $parsedData['formatted_start_datetime'];

        if ($parsedData['is_multi_day']) {
            $html .= "<br>to " . $parsedData['formatted_end_datetime'];
        } elseif (!empty($parsedData['endTime'])) {
            $html .= " - " . $parsedData['endTime'];
        }
        $html .= "</div>";
        $html .= "</div>";

        // Location
        if (!empty($parsedData['location'])) {
            $html .= "<div class=\"event-detail-item\">";
            $html .= "<div class=\"detail-icon\">📍</div>";
            $html .= "<div class=\"detail-content\">";
            $html .= "<strong>Location:</strong><br>";
            $html .= $parsedData['location'];

            if (!empty($parsedData['address'])) {
                $html .= "<br><small>{$parsedData['address']}</small>";
            }

            if (!empty($parsedData['mapUrl'])) {
                $html .= "<br><a href=\"{$parsedData['mapUrl']}\" target=\"_blank\" class=\"map-link\">View Map</a>";
            }
            $html .= "</div>";
            $html .= "</div>";
        }

        // Pricing
        $html .= "<div class=\"event-detail-item\">";
        $html .= "<div class=\"detail-icon\">🎫</div>";
        $html .= "<div class=\"detail-content\">";
        $html .= "<strong>Tickets:</strong><br>";

        if ($parsedData['is_free']) {
            $html .= "<span class=\"free-event\">FREE EVENT</span>";
        } else {
            $html .= "<span class=\"ticket-price\">{$parsedData['currency']}{$parsedData['ticketPrice']}</span>";
        }

        if ($parsedData['capacity'] > 0) {
            $html .= "<br><small>Capacity: {$parsedData['capacity']} people</small>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Organizer
        if (!empty($parsedData['organizerName'])) {
            $html .= "<div class=\"event-detail-item\">";
            $html .= "<div class=\"detail-icon\">👤</div>";
            $html .= "<div class=\"detail-content\">";
            $html .= "<strong>Organizer:</strong><br>";
            $html .= $parsedData['organizerName'];

            if (!empty($parsedData['organizerEmail'])) {
                $html .= "<br><a href=\"mailto:{$parsedData['organizerEmail']}\">{$parsedData['organizerEmail']}</a>";
            }

            if (!empty($parsedData['organizerPhone'])) {
                $html .= "<br><a href=\"tel:{$parsedData['organizerPhone']}\">{$parsedData['organizerPhone']}</a>";
            }
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        // Description
        if (!empty($parsedData['description'])) {
            $html .= "<div class=\"event-description\">";
            $html .= "<h3>About This Event</h3>";
            $html .= "<div class=\"description-content\">{$parsedData['formatted_description']}</div>";
            $html .= "</div>";
        }

        // Action buttons
        $html .= "<div class=\"event-actions\">";

        if (!empty($parsedData['ticketUrl'])) {
            $html .= "<a href=\"{$parsedData['ticketUrl']}\" target=\"_blank\" class=\"btn btn-primary event-ticket-btn\">";
            $html .= $parsedData['is_free'] ? 'Register Now' : 'Buy Tickets';
            $html .= "</a>";
        }

        if ($parsedData['showSignupForm']) {
            $html .= "<button type=\"button\" class=\"btn btn-secondary\" onclick=\"showEventSignupForm()\">Sign Up for Updates</button>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</section>";

        // Add signup form if enabled
        if ($parsedData['showSignupForm']) {
            $html .= $this->generateSignupForm($parsedData);
        }

        return $html;
    }

    private function generateSidebarHtml(array $parsedData): string
    {
        $html = "<div class=\"event-sidebar\">";
        $html .= "<h3 class=\"sidebar-event-title\">{$parsedData['title']}</h3>";

        $html .= "<div class=\"sidebar-event-details\">";
        $html .= "<div class=\"sidebar-detail\">";
        $html .= "<strong>Date:</strong><br>";
        $html .= $parsedData['formatted_start_datetime'];
        $html .= "</div>";

        if (!empty($parsedData['location'])) {
            $html .= "<div class=\"sidebar-detail\">";
            $html .= "<strong>Location:</strong><br>";
            $html .= $parsedData['location'];
            $html .= "</div>";
        }

        $html .= "<div class=\"sidebar-detail\">";
        $html .= "<strong>Price:</strong><br>";
        if ($parsedData['is_free']) {
            $html .= "<span class=\"free-event\">FREE</span>";
        } else {
            $html .= "<span class=\"ticket-price\">{$parsedData['currency']}{$parsedData['ticketPrice']}</span>";
        }
        $html .= "</div>";
        $html .= "</div>";

        if (!empty($parsedData['ticketUrl'])) {
            $html .= "<a href=\"{$parsedData['ticketUrl']}\" target=\"_blank\" class=\"btn btn-primary\" style=\"width: 100%; margin-top: 1rem;\">";
            $html .= $parsedData['is_free'] ? 'Register' : 'Get Tickets';
            $html .= "</a>";
        }

        $html .= "</div>";
        return $html;
    }

    private function generateSignupForm(array $parsedData): string
    {
        $html = "<div id=\"event-signup-modal\" class=\"event-modal\" style=\"display: none;\">";
        $html .= "<div class=\"event-modal-content\">";
        $html .= "<span class=\"event-modal-close\" onclick=\"hideEventSignupForm()\">&times;</span>";
        $html .= "<h3>Sign Up for Event Updates</h3>";
        $html .= "<p>Get notified about updates for: <strong>{$parsedData['title']}</strong></p>";

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"event-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"event_title\" value=\"{$parsedData['title']}\">";
        $html .= "<input type=\"hidden\" name=\"event_date\" value=\"{$parsedData['startDate']}\">";

        $html .= "<div class=\"form-group\">";
        $html .= "<input type=\"text\" name=\"name\" placeholder=\"Your Name\" required>";
        $html .= "</div>";

        $html .= "<div class=\"form-group\">";
        $html .= "<input type=\"email\" name=\"email\" placeholder=\"Your Email\" required>";
        $html .= "</div>";

        $html .= "<div class=\"form-group\">";
        $html .= "<input type=\"tel\" name=\"phone\" placeholder=\"Phone Number (Optional)\">";
        $html .= "</div>";

        $html .= "<div class=\"form-group\">";
        $html .= "<label><input type=\"checkbox\" name=\"notifications[]\" value=\"reminders\"> Send me event reminders</label>";
        $html .= "</div>";

        $html .= "<div class=\"form-group\">";
        $html .= "<label><input type=\"checkbox\" name=\"notifications[]\" value=\"similar_events\"> Notify me of similar events</label>";
        $html .= "</div>";

        $html .= "<button type=\"submit\" class=\"btn btn-primary\" style=\"width: 100%;\">Sign Me Up</button>";
        $html .= "</form>";

        $html .= "</div>";
        $html .= "</div>";

        // Add JavaScript for modal
        $html .= "<script>";
        $html .= "function showEventSignupForm() {";
        $html .= "  document.getElementById('event-signup-modal').style.display = 'block';";
        $html .= "}";
        $html .= "function hideEventSignupForm() {";
        $html .= "  document.getElementById('event-signup-modal').style.display = 'none';";
        $html .= "}";
        $html .= "window.onclick = function(event) {";
        $html .= "  var modal = document.getElementById('event-signup-modal');";
        $html .= "  if (event.target == modal) {";
        $html .= "    modal.style.display = 'none';";
        $html .= "  }";
        $html .= "}";
        $html .= "</script>";

        return $html;
    }
}