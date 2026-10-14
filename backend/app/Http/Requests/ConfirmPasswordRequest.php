<?php

namespace App\Http\Requests;

class ConfirmPasswordRequest extends ApiRequest
{
    public function rules(): array
    {
        return ['password' => ['required', 'string', 'max:255']];
    }
}
