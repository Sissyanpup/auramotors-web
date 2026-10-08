<?php

namespace App\Http\Requests\Auth;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateKtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ktp_number' => [
                'required',
                'string',
                'digits:16',
                Rule::unique('users', 'ktp_number')->ignore($this->user()->id),
            ],
            'ktp_name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ktp_number.digits' => 'NIK harus terdiri dari 16 digit angka.',
            'ktp_number.unique' => 'NIK ini sudah terdaftar pada akun lain.',
        ];
    }
}
