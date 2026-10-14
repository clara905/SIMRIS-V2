<?php

namespace App\Http\Requests;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['login' => ['required', 'string', 'max:255'], 'password' => ['required', 'string', 'max:255'], 'totp' => ['nullable', 'string', 'regex:/^[0-9]{6}$/']];
    }
}
