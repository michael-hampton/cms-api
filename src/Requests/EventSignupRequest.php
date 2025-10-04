<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;
use App\Framework\Support\Str;
use App\Repositories\CategoryRepository;

class CreateEventSignupRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'event_title' => 'max:255',
            'event_date' => 'date',
            'name' => 'max:255',
            'first_name' => 'max:100',
            'last_name' => 'max:100',
            'email' => 'required|email|max:255',
            'phone' => 'max:20',
            'company' => 'max:255',
            'dietary_requirements' => 'max:500',
            'accessibility_requirements' => 'max:500',
            'newsletter' => 'boolean',
            'terms' => 'required|boolean',
            'notifications' => 'array'
        ];
    }
}