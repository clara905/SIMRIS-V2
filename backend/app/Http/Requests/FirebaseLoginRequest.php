<?php

declare(strict_types=1);

namespace App\Http\Requests;

class FirebaseLoginRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'captcha_token' => ['required', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email wajib diisi.',
            'email.email' => 'Format email tidak valid.',
            'password.required' => 'Kata sandi wajib diisi.',
            'captcha_token.required' => 'Selesaikan captcha terlebih dahulu.',
        ];
    }
}
