<?php

namespace App\Http\Requests\Buyer;

use App\Enums\InsuranceType;
use App\Enums\PaymentScheme;
use App\Models\Vehicle;
use App\Promotions\PromoService;
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

        // Kredit memakai kebijakan DP leasing (min. 20%), bukan preset DP penjual,
        // dan wajib berasuransi selama tenor.
        $isCredit = $this->input('payment_scheme') === PaymentScheme::Credit->value;
        if ($isCredit) {
            $paymentPercents = array_map('floatval', config('pricing.credit.dp_options'));
            $insuranceTypes = array_values(array_diff($insuranceTypes, [InsuranceType::None->value]));
        }

        return [
            'payment_scheme' => ['required', new Enum(PaymentScheme::class)],
            'dp_percent' => ['required', 'numeric', Rule::in($paymentPercents)],
            'tenor_months' => ['nullable', 'required_if:payment_scheme,'.PaymentScheme::Credit->value, 'integer', Rule::in(array_keys(config('pricing.credit.flat_rates')))],
            'insurance_type' => ['required', new Enum(InsuranceType::class), Rule::in($insuranceTypes)],
            'promo_code' => [
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail) use ($vehicle) {
                    $error = app(PromoService::class)->voucherError(
                        (string) $value,
                        $vehicle,
                        PaymentScheme::tryFrom((string) $this->input('payment_scheme')),
                        InsuranceType::tryFrom((string) $this->input('insurance_type')),
                    );
                    if ($error !== null) {
                        $fail($error);
                    }
                },
            ],
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
            'insurance_type.in' => $this->input('payment_scheme') === PaymentScheme::Credit->value
                ? 'Kredit leasing wajib memakai asuransi (All Risk atau TLO) selama tenor.'
                : 'Opsi asuransi yang dipilih tidak tersedia untuk kendaraan ini.',
            'tenor_months.required_if' => 'Pilih tenor kredit.',
            'tenor_months.in' => 'Tenor kredit yang tersedia: 12, 24, 36, 48, atau 60 bulan.',
        ];
    }

    public function scheme(): PaymentScheme
    {
        return PaymentScheme::from($this->validated('payment_scheme'));
    }

    public function promoCode(): ?string
    {
        $code = $this->validated('promo_code');

        return $code === null || trim((string) $code) === '' ? null : mb_strtoupper(trim((string) $code));
    }

    /**
     * Promo yang berlaku (auto terbaik + voucher) dalam bentuk input kalkulator.
     *
     * @return array<int, array<string, mixed>>
     */
    public function promotions(): array
    {
        $promos = app(PromoService::class);

        return $promos->calculatorInput(
            $promos->resolveForCheckout($this->route('vehicle'), $this->scheme(), $this->insuranceType(), $this->promoCode())
        );
    }

    public function tenorMonths(): ?int
    {
        return $this->scheme() === PaymentScheme::Credit ? (int) $this->validated('tenor_months') : null;
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
