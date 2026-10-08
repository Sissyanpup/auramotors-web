<?php

namespace App\Http\Requests\Buyer;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Vehicle;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isBuyer() && $this->user()->hasCompletedKtp();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Vehicle $vehicle */
        $vehicle = $this->route('vehicle');

        $paymentPercents = array_map(
            fn (array $opt) => (float) $opt['percent'],
            $vehicle->effectivePaymentOptions()
        );

        $insuranceTypes = array_map(
            fn (array $opt) => (string) $opt['type'],
            $vehicle->effectiveInsuranceOptions()
        );

        return [
            'payment_scheme' => ['required', new Enum(PaymentScheme::class)],
            'dp_percent' => ['required', 'numeric', Rule::in($paymentPercents)],
            'insurance_type' => ['required', new Enum(InsuranceType::class), Rule::in($insuranceTypes)],
            'buyer_address' => ['required', 'string', 'max:1000'],
            'buyer_phone' => ['required', 'string', 'max:32'],
            'buyer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'dp_percent.in' => 'Persentase DP yang dipilih tidak tersedia untuk kendaraan ini.',
            'insurance_type.in' => 'Opsi asuransi yang dipilih tidak tersedia untuk kendaraan ini.',
        ];
    }
}
