<?php

namespace App\Parsers\Renderers;

use App\Parsers\Dtos\BlockDtoInterface;
use App\Parsers\Dtos\EventBlockDto;

class EventBlockRenderer extends BaseBlockRenderer
{
    public function render(BlockDtoInterface $dto): string
    {
        if (!$dto instanceof EventBlockDto) {
            throw new \InvalidArgumentException('Expected EventBlockDto');
        }

        if ($dto->context === 'sidebar') {
            return $this->renderSidebar($dto);
        }

        return $this->renderDefault($dto);
    }

    private function renderSidebar(EventBlockDto $dto): string
    {
        $html = "<div class=\"event-sidebar\">";
        $html .= "<h3 class=\"sidebar-event-title\">" . $this->escape($dto->title) . "</h3>";

        $html .= "<div class=\"sidebar-event-details\">";
        $html .= "<div class=\"sidebar-detail\">";
        $html .= "<strong>Date:</strong><br>";
        $html .= $this->formatDateTime($dto->startDate, $dto->startTime);
        $html .= "</div>";

        if (!empty($dto->location)) {
            $html .= "<div class=\"sidebar-detail\">";
            $html .= "<strong>Location:</strong><br>";
            $html .= $this->escape($dto->location);
            $html .= "</div>";
        }

        $html .= "<div class=\"sidebar-detail\">";
        $html .= "<strong>Price:</strong><br>";
        if ($dto->ticketPrice == 0) {
            $html .= "<span class=\"free-event\">FREE</span>";
        } else {
            $html .= "<span class=\"ticket-price\">{$dto->currency}" . number_format($dto->ticketPrice, 2) . "</span>";
        }
        $html .= "</div>";
        $html .= "</div>";

        if (!empty($dto->ticketUrl)) {
            $buttonText = $dto->ticketPrice == 0 ? 'Register' : 'Get Tickets';
            $html .= "<a href=\"{$dto->ticketUrl}\" target=\"_blank\" class=\"btn btn-primary\" style=\"width: 100%; margin-top: 1rem;\">";
            $html .= $buttonText;
            $html .= "</a>";
        }

        $html .= "</div>";
        return $html;
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

    private function renderDefault(EventBlockDto $dto): string
    {
        $html = "<section class=\"event-block\">";

        if (!empty($dto->image)) {
            $html .= "<div class=\"event-image\">";
            $html .= "<img src=\"{$dto->image['src']}\" alt=\"" . $this->escape($dto->title) . "\" class=\"event-img\">";
            $html .= "</div>";
        }

        $html .= "<div class=\"event-content\">";

        $html .= "<div class=\"event-header\">";
        $html .= "<h2 class=\"event-title\">" . $this->escape($dto->title) . "</h2>";

        if (!empty($dto->category)) {
            $html .= "<span class=\"event-category\">" . $this->escape($dto->category) . "</span>";
        }
        $html .= "</div>";

        $html .= "<div class=\"event-details\">";

        // Date and Time
        $html .= "<div class=\"event-detail-item\">";
        $html .= "<div class=\"detail-icon\">📅</div>";
        $html .= "<div class=\"detail-content\">";
        $html .= "<strong>Date & Time:</strong><br>";
        $html .= $this->formatDateTime($dto->startDate, $dto->startTime);

        if ($dto->startDate !== $dto->endDate && !empty($dto->endDate)) {
            $html .= "<br>to " . $this->formatDateTime($dto->endDate, $dto->endTime);
        } elseif (!empty($dto->endTime)) {
            $html .= " - " . $dto->endTime;
        }
        $html .= "</div>";
        $html .= "</div>";

        // Location
        if (!empty($dto->location)) {
            $html .= "<div class=\"event-detail-item\">";
            $html .= "<div class=\"detail-icon\">📍</div>";
            $html .= "<div class=\"detail-content\">";
            $html .= "<strong>Location:</strong><br>";
            $html .= $this->escape($dto->location);

            if (!empty($dto->address)) {
                $html .= "<br><small>" . $this->escape($dto->address) . "</small>";
            }

            if (!empty($dto->mapUrl)) {
                $html .= "<br><a href=\"{$dto->mapUrl}\" target=\"_blank\" class=\"map-link\">View Map</a>";
            }
            $html .= "</div>";
            $html .= "</div>";
        }

        // Pricing
        $html .= "<div class=\"event-detail-item\">";
        $html .= "<div class=\"detail-icon\">🎫</div>";
        $html .= "<div class=\"detail-content\">";
        $html .= "<strong>Tickets:</strong><br>";

        if ($dto->ticketPrice == 0) {
            $html .= "<span class=\"free-event\">FREE EVENT</span>";
        } else {
            $html .= "<span class=\"ticket-price\">{$dto->currency}" . number_format($dto->ticketPrice, 2) . "</span>";
        }

        if ($dto->capacity > 0) {
            $html .= "<br><small>Capacity: {$dto->capacity} people</small>";
        }
        $html .= "</div>";
        $html .= "</div>";

        // Organizer
        if (!empty($dto->organizerName)) {
            $html .= "<div class=\"event-detail-item\">";
            $html .= "<div class=\"detail-icon\">👤</div>";
            $html .= "<div class=\"detail-content\">";
            $html .= "<strong>Organizer:</strong><br>";
            $html .= $this->escape($dto->organizerName);

            if (!empty($dto->organizerEmail)) {
                $html .= "<br><a href=\"mailto:{$dto->organizerEmail}\">{$dto->organizerEmail}</a>";
            }

            if (!empty($dto->organizerPhone)) {
                $html .= "<br><a href=\"tel:{$dto->organizerPhone}\">{$dto->organizerPhone}</a>";
            }
            $html .= "</div>";
            $html .= "</div>";
        }

        $html .= "</div>";

        // Description
        if (!empty($dto->description)) {
            $html .= "<div class=\"event-description\">";
            $html .= "<h3>About This Event</h3>";
            $html .= "<div class=\"description-content\">" . $this->escapeWithBreaks($dto->description) . "</div>";
            $html .= "</div>";
        }

        // Action buttons
        $html .= "<div class=\"event-actions\">";

        if (!empty($dto->ticketUrl)) {
            $buttonText = $dto->ticketPrice == 0 ? 'Register Now' : 'Buy Tickets';
            $html .= "<a href=\"{$dto->ticketUrl}\" target=\"_blank\" class=\"btn btn-primary event-ticket-btn\">";
            $html .= $buttonText;
            $html .= "</a>";
        }

        if ($dto->showSignupForm) {
            $html .= "<button type=\"button\" class=\"btn btn-secondary\" onclick=\"showEventSignupForm()\">Sign Up for Updates</button>";
        }

        $html .= "</div>";
        $html .= "</div>";
        $html .= "</section>";

        if ($dto->showSignupForm) {
            $html .= $this->renderSignupForm($dto);
        }

        return $html;
    }

    private function renderSignupForm(EventBlockDto $dto): string
    {
        $html = "<div id=\"event-signup-modal\" class=\"event-modal\" style=\"display: none;\">";
        $html .= "<div class=\"event-modal-content\">";
        $html .= "<span class=\"event-modal-close\" onclick=\"hideEventSignupForm()\">&times;</span>";
        $html .= "<h3>Sign Up for Event Updates</h3>";
        $html .= "<p>Get notified about updates for: <strong>" . $this->escape($dto->title) . "</strong></p>";

        $html .= "<form method=\"POST\" action=\"/event-signup\" class=\"event-signup-form\">";
        $html .= "<input type=\"hidden\" name=\"event_title\" value=\"" . $this->escape($dto->title) . "\">";
        $html .= "<input type=\"hidden\" name=\"event_date\" value=\"{$dto->startDate}\">";

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

    protected function getSupportedType(): string
    {
        return 'event';
    }
}