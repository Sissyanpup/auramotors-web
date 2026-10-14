<?php

namespace App\Http\Requests\Buyer;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Vehicle;
use App\Support\PriceBreakdownCalculator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

/**
 * Parameter harga (skema bayar, DP, asuransi + perluasan) — dipakai endpoint
 * simulasi harga publik dan sebagai dasar CheckoutRequest supaya validasinya
 * identik dengan yang benar-benar ditagihkan.
 */
class PriceQuoteRequest extends FormRequest
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
            'insurance_addons' => ['nullable', 'array'],
            'insurance_addons.*' => [
                'string',
                'distinct',
                Rule::in(array_keys(config('pricing.insurance.addons'))),
                function (string $attribute, mixed $value, Closure $fail) {
                    $type = InsuranceType::tryFrom((string) $this->input('insurance_type'));
                    if ($type !== null && ! app(PriceBreakdownCalculator::class)->addonAllowedFor((string) $value, $type)) {
                        $fail('Perluasan jaminan ini tidak tersedia untuk jenis asuransi yang dipilih.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'dp_percent.in' => 'Persentase DP yang dipilih tidak tersedia untuk kendaraan ini.',
            'insurance_type.in' => 'Opsi asuransi yang dipilih tidak tersedia untuk kendaraan ini.',
        ];
    }

    public function scheme(): PaymentScheme
    {
        return PaymentScheme::from($this->validated('payment_scheme'));
    }

    public function insuranceType(): InsuranceType
    {
        return InsuranceType::from($this->validated('insurance_type'));
    }

    /**
     * @return array<int, string>
     */
    public function insuranceAddons(): array
    {
        return $this->insuranceType() === InsuranceType::None
            ? []
            : array_values($this->validated('insurance_addons') ?? []);
    }
}
