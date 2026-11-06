<?php
declare(strict_types=1);
namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RegisterRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'nip' => ['required', 'string', 'regex:/^[0-9]{1,18}$/', Rule::exists('PEGAWAI', 'nip')],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:USERS,email_login'],
            'id_token' => ['required', 'string', 'max:8192'],
            'password' => ['required', 'string', 'min:12', 'max:255'],
            'password_confirmation' => ['required', 'string', 'same:password'],
            'captcha_token' => ['required', 'string', 'max:4096'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'nip.required' => 'NIP wajib diisi.',
            'nip.regex' => 'NIP harus terdiri dari 1 sampai 18 digit angka.',
            'nip.exists' => 'NIP tidak ditemukan di data pegawai.',
        ];
    }
}
