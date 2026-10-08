<?php

namespace App\Http\Requests\Seller;

use App\Enums\SellerEntityType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SellerKycRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * The precise create-vs-resubmit rule (SellerProfilePolicy) is checked
     * explicitly in the controller, since this request is shared by both actions.
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
            'entity_type' => ['required', Rule::in(array_column(SellerEntityType::cases(), 'value'))],
            'ktp' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'npwp' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            // Entity docs wajib hanya kalau entity_type = perusahaan.
            'company_registration' => ['required_if:entity_type,perusahaan', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'articles_of_association' => ['required_if:entity_type,perusahaan', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'ubo_declaration' => ['required_if:entity_type,perusahaan', 'nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ];
    }
}
