<?php

namespace App\Requests\OpenCollab;

use App\Framework\Http\FormRequest;

class MarkPayoutPaidRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'reference' => ['string', 'max:255'],
            'notes' => ['string', 'max:1000'],
        ];
    }
}