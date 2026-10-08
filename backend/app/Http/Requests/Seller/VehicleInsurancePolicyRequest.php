<?php

namespace App\Http\Requests\Seller;

use App\Enums\VehiclePolicyType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleInsurancePolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Ownership divalidasi di controller (vehicle bind + seller_id check).
        return $this->user() !== null;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'policy_type' => ['required', Rule::in(array_column(VehiclePolicyType::cases(), 'value'))],
            'insurer_name' => ['required', 'string', 'max:120'],
            'policy_number' => ['required', 'string', 'max:64'],
            'coverage_amount' => ['required', 'numeric', 'min:0'],
            // Wajib hanya untuk Agreed Value (kendaraan langka/klasik).
            'agreed_value_amount' => ['required_if:policy_type,agreed_value', 'nullable', 'numeric', 'min:0'],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['required', 'date', 'after:valid_from'],
            'certificate' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
