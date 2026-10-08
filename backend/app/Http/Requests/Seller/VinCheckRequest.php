<?php

namespace App\Http\Requests\Seller;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VinCheckRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // VIN standar internasional: 17 karakter alfanumerik (tanpa I/O/Q untuk mencegah kekeliruan
            // dengan 1/0). Ini regex format bukan validasi keaslian — keaslian di-check via VinCheckService.
            'vin' => ['required', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
        ];
    }
}
