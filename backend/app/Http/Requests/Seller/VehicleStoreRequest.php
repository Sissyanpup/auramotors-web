<?php

namespace App\Http\Requests\Seller;

use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class VehicleStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', Vehicle::class);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'integer', 'min:1980', 'max:'.(date('Y') + 1)],
            'vin' => ['nullable', 'string', 'size:17', 'regex:/^[A-HJ-NPR-Z0-9]{17}$/i'],
            'price' => ['required', 'numeric', 'min:0'],
            'mileage' => ['required', 'integer', 'min:0'],
            'location' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'specs' => ['nullable', 'array'],
            'payment_options' => ['nullable', 'array', 'max:8'],
            'payment_options.*.label' => ['required_with:payment_options', 'string', 'max:32'],
            'payment_options.*.percent' => ['required_with:payment_options', 'numeric', 'min:1', 'max:100'],
            'insurance_options' => ['nullable', 'array', 'max:8'],
            'insurance_options.*.type' => ['required_with:insurance_options', 'string', 'in:none,tlo,all_risk'],
            'insurance_options.*.label' => ['required_with:insurance_options', 'string', 'max:64'],
            'insurance_options.*.premium' => ['nullable', 'numeric', 'min:0'],
            'insurance_options.*.premium_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'photos' => ['required', 'array', 'min:1', 'max:20'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stnk' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'bpkb' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
