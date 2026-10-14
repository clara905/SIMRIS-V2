<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function messages(): array
    {
        return ['required' => ':attribute wajib diisi.', 'string' => ':attribute harus berupa teks.', 'integer' => ':attribute harus berupa bilangan bulat.', 'boolean' => ':attribute harus bernilai benar atau salah.', 'email' => ':attribute harus berupa alamat email yang valid.', 'unique' => ':attribute sudah digunakan.', 'exists' => ':attribute tidak ditemukan.', 'date' => ':attribute harus berupa tanggal yang valid.', 'date_format' => 'Format :attribute tidak sesuai :format.', 'after_or_equal' => ':attribute tidak boleh sebelum :date.', 'confirmed' => 'Konfirmasi :attribute tidak sama.', 'min' => ':attribute minimal :min karakter.', 'max' => ':attribute melebihi batas :max.', 'in' => ':attribute tidak termasuk pilihan yang diperbolehkan.', 'regex' => 'Format :attribute tidak valid.', 'prohibited' => ':attribute tidak boleh dikirim.', 'different' => ':attribute harus berbeda dari :other.'];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Data yang dikirim tidak valid.', 'errors' => $validator->errors()], 422));
    }
}
