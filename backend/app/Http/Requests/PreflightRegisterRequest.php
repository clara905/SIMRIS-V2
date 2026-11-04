<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class PreflightRegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nip' => ['required', 'string', 'regex:/^[0-9]{18}$/', Rule::exists('PEGAWAI', 'nip')],
            'nama_lengkap' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nip.required' => 'NIP wajib diisi.',
            'nip.regex' => 'NIP harus terdiri dari tepat 18 digit angka.',
            'nip.exists' => 'NIP tidak ditemukan di data pegawai.',
        ];
    }
}
