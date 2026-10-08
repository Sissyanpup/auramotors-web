<?php

namespace App\Http\Requests\Seller;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BankAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The precise ownership check (SellerProfilePolicy::manageBankAccount)
     * is done explicitly in the controller, since it needs the profile.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Seller;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'bank_name' => ['required', 'string', 'max:100'],
            'bank_account_number' => ['required', 'string', 'max:50'],
            'bank_account_holder_name' => ['required', 'string', 'max:150'],
        ];
    }
}
