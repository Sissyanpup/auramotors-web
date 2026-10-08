<?php

namespace App\Http\Requests\Buyer;

use App\Enums\BuyerIdType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BuyerKycRequest extends FormRequest
{
    /**
     * The precise create-vs-resubmit rule (BuyerProfilePolicy) is checked
     * explicitly in the controller, since this request is shared by both actions.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Buyer;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id_type' => ['required', Rule::in(array_column(BuyerIdType::cases(), 'value'))],
            'id_number' => ['required', 'string', 'max:64'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'address_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'proof_of_funds' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
