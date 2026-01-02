<?php

namespace App\Requests;

use App\Framework\Http\FormRequest;

class MemberRegistrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            ///'first_name' => 'required|min:2|max:100',
            // 'last_name' => 'required|min:2|max:100',
            // 'email' => 'required|email|max:255',
            //'password' => 'required|min:8|confirmed',
            // 'terms' => 'accepted'
        ];
    }
}