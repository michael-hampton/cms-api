<?php

namespace App\Requests\Newsletter;

use App\Framework\Http\FormRequest;

/**
 * Validates the payload for creating both creation and send schedules.
 * Used by NewsletterScheduleController::storeCreation() and ::storeSend().
 */
class CreateNewsletterScheduleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'day_of_week' => ['nullable', 'integer', 'min:0', 'max:6'],
            'day_of_month' => ['nullable', 'integer', 'min:1', 'max:31'],
            'time' => ['required', 'string', 'regex:/^\d{2}:\d{2}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'frequency.required' => 'frequency is required',
            'frequency.in' => 'frequency must be daily, weekly, or monthly',
            'time.required' => 'time is required',
            'time.regex' => 'time must be in HH:MM format',
        ];
    }

    /**
     * Cross-field rules that depend on the chosen frequency.
     * Runs after the base rules pass.
     */
    public function after(): array
    {
        return [
            function (self $request) {
                $frequency = $request->get('frequency');

                if ($frequency === 'weekly' && !$request->has('day_of_week')) {
                    $request->addError('day_of_week', 'day_of_week is required for weekly schedules');
                }

                if ($frequency === 'monthly' && empty($request->get('day_of_month'))) {
                    $request->addError('day_of_month', 'day_of_month is required for monthly schedules');
                }
            },
        ];
    }
}