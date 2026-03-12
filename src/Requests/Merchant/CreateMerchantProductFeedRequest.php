<?php

namespace App\Requests\Merchant;

use App\Framework\Http\FormRequest;

class CreateMerchantProductFeedRequest extends FormRequest
{

    public function rules(): array
    {
        return [
            'feed_url' => 'required|url',
            'feed_type' => 'required|string',
        ];
    }
}