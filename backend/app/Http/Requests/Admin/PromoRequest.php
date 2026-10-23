<?php

namespace App\Http\Requests\Admin;

use App\Models\Promo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromoRequest extends FormRequest
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
        /** @var Promo|null $promo */
        $promo = $this->route('promo');

        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in([Promo::KIND_AUTO, Promo::KIND_VOUCHER])],
            'code' => ['nullable', 'required_if:kind,'.Promo::KIND_VOUCHER, 'alpha_dash', 'max:32', Rule::unique('promos', 'code')->ignore($promo?->id)],
            'benefit' => ['required', Rule::in([Promo::BENEFIT_DISCOUNT, Promo::BENEFIT_FREE_ADDON, Promo::BENEFIT_CREDIT_RATE])],
            'discount_type' => ['nullable', 'required_if:benefit,'.Promo::BENEFIT_DISCOUNT, Rule::in(['percent', 'fixed'])],
            'discount_value' => ['nullable', 'required_if:benefit,'.Promo::BENEFIT_DISCOUNT, 'numeric', 'min:0', Rule::when($this->input('discount_type') === 'percent', ['max:50'])],
            'max_discount' => ['nullable', 'numeric', 'min:0'],
            'addon_code' => ['nullable', 'required_if:benefit,'.Promo::BENEFIT_FREE_ADDON, Rule::in(array_keys(config('pricing.insurance.addons')))],
            // Diisi dalam poin persen (mis. 2 = bunga turun 2%/tahun), disimpan sebagai pecahan.
            'rate_cut_percent' => ['nullable', 'required_if:benefit,'.Promo::BENEFIT_CREDIT_RATE, 'numeric', 'min:0.1', 'max:10'],
            'min_vehicle_price' => ['nullable', 'numeric', 'min:0'],
            'brand' => ['nullable', 'string', 'max:60'],
            'badge' => ['nullable', 'string', 'max:30'],
            'description' => ['nullable', 'string', 'max:500'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'quota' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required_if' => 'Voucher wajib punya kode promo.',
            'discount_value.max' => 'Potongan persen maksimal 50%.',
            'ends_at.after_or_equal' => 'Tanggal berakhir harus setelah tanggal mulai.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function promoData(): array
    {
        $data = $this->safe()->except('rate_cut_percent');
        $benefit = $data['benefit'];

        return [
            ...$data,
            'code' => isset($data['code']) ? mb_strtoupper($data['code']) : null,
            'discount_type' => $benefit === Promo::BENEFIT_DISCOUNT ? $data['discount_type'] : null,
            'discount_value' => $benefit === Promo::BENEFIT_DISCOUNT ? $data['discount_value'] : null,
            'max_discount' => $benefit === Promo::BENEFIT_DISCOUNT ? ($data['max_discount'] ?? null) : null,
            'addon_code' => $benefit === Promo::BENEFIT_FREE_ADDON ? $data['addon_code'] : null,
            'rate_cut' => $benefit === Promo::BENEFIT_CREDIT_RATE ? round((float) $this->validated('rate_cut_percent') / 100, 4) : null,
            'is_active' => $this->boolean('is_active', true),
        ];
    }
}
