<?php

namespace App\Http\Requests\Buyer;

use Illuminate\Contracts\Validation\ValidationRule;

class CheckoutRequest extends PriceQuoteRequest
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
        return [
            ...parent::rules(),
            'buyer_address' => ['required', 'string', 'max:1000'],
            'buyer_phone' => ['required', 'string', 'max:32'],
            'buyer_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
