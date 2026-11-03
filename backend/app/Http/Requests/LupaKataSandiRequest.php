<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LupaKataSandiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'captcha_token' => ['required', 'string', 'max:4096'],
        ];
    }
}