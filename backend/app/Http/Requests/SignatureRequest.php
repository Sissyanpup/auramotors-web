<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership + tahap transaksi divalidasi di controller (buyer/seller only + belum tanda tangan).
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Tanda tangan dikirim sebagai PNG data URL dari canvas frontend.
            'signature' => ['required', 'string', 'starts_with:data:image/png;base64,', 'max:200000'],
        ];
    }

    /**
     * Ekstrak binary PNG dari data URL. Data URL max 200 KB (rules) → binary max ~150 KB
     * setelah decode base64. Cukup untuk canvas signature 600x200 px.
     */
    public function binaryPng(): string
    {
        $raw = (string) $this->validated('signature');
        $prefix = 'data:image/png;base64,';
        $b64 = substr($raw, strlen($prefix));

        return (string) base64_decode($b64, true);
    }
}
