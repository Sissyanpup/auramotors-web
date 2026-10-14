<?php

namespace App\Http\Requests;

use App\Enums\TransactionDocumentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

/**
 * Tanda tangan untuk satu atau beberapa dokumen sekaligus. Kelayakan pihak
 * (buyer/seller) & status dokumen divalidasi di App\Documents\DocumentSigning.
 */
class DocumentSignatureRequest extends SignatureRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $signableTypes = array_map(
            fn (TransactionDocumentType $type) => $type->value,
            array_filter(TransactionDocumentType::cases(), fn (TransactionDocumentType $type) => $type->isSignable())
        );

        return [
            ...parent::rules(),
            'document_types' => ['required', 'array', 'min:1'],
            'document_types.*' => ['required', 'string', 'distinct', Rule::in($signableTypes)],
        ];
    }

    /**
     * @return array<int, TransactionDocumentType>
     */
    public function documentTypes(): array
    {
        return array_map(
            fn (string $value) => TransactionDocumentType::from($value),
            $this->validated('document_types')
        );
    }
}
