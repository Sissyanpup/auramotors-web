<?php

namespace App\Models;

use App\Enums\SellerEntityType;
use App\Enums\SellerProfileStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ktp_path', 'npwp_path', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    'bank_name', 'bank_account_number', 'bank_account_holder_name',
    'entity_type', 'company_registration_path', 'articles_of_association_path', 'ubo_declaration_path',
])]
class SellerProfile extends Model
{
    protected $attributes = [
        'entity_type' => SellerEntityType::Individu,
        'npwp_path' => null,
        'company_registration_path' => null,
        'articles_of_association_path' => null,
        'ubo_declaration_path' => null,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'rejection_reason' => null,
        'bank_name' => null,
        'bank_account_number' => null,
        'bank_account_holder_name' => null,
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => SellerEntityType::class,
            'status' => SellerProfileStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
