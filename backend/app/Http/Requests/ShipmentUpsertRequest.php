<?php

namespace App\Http\Requests;

use App\Enums\ShippingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ShipmentUpsertRequest extends FormRequest
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
            'origin_country' => ['required', 'string', 'size:2'],
            'destination_country' => ['required', 'string', 'size:2'],
            'shipping_mode' => ['required', Rule::in(array_column(ShippingMode::cases(), 'value'))],
            'carrier_name' => ['required', 'string', 'max:120'],
            'tracking_number' => ['nullable', 'string', 'max:64'],
            'estimated_arrival' => ['nullable', 'date'],
            'cargo_insurer_name' => ['nullable', 'string', 'max:120'],
            'cargo_policy_number' => ['nullable', 'string', 'max:64'],
            'cargo_coverage_amount' => ['nullable', 'numeric', 'min:0'],
            'cargo_certificate' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
