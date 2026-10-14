<?php

namespace App\Http\Requests;

class PasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['current_password' => ['required', 'string'], 'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed', 'different:current_password']];
    }
}
