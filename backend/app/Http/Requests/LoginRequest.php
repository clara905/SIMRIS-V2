<?php

declare(strict_types=1);

namespace App\Http\Requests;

class LoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'login' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
            'totp' => [
                'nullable',
                'string',
                'regex:/^[0-9]{6}$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'login.required' => 'NIP atau email wajib diisi.',
            'password.required' => 'Kata sandi wajib diisi.',
            'totp.regex' => 'Kode OTP harus terdiri dari 6 digit.',
        ];
    }
}
