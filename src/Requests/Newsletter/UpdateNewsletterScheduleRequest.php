<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;

/**
 * Validates the payload for updating both creation and send schedules.
 * All fields are optional — only supplied fields are changed.
 * Used by NewsletterScheduleController::updateCreation() and ::updateSend().
 */
class UpdateNewsletterScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'frequency' => ['string', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'time' => ['string', 'regex:/^\d{2}:\d{2}$/'],
            'status' => ['string', 'in:active,paused'],
        ];
    }

    public function messages(): array
    {
        return [
            'frequency.in' => 'frequency must be daily, weekly, or monthly',
            'time.regex' => 'time must be in HH:MM format',
            'status.in' => 'status must be active or paused',
        ];
    }
}