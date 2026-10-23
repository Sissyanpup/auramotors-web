<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Setoran angsuran yang dicatat admin di luar portal bank (mis. tunai di
 * kantor leasing atau transfer manual).
 */
class RecordInstallmentPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isAdmin();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
