<?php

namespace App\Models;

use App\Enums\BuyerIdType;
use App\Enums\BuyerProfileStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id_type', 'id_number', 'id_document_path', 'address_proof_path', 'proof_of_funds_path',
    'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
])]
class BuyerProfile extends Model
{
    protected $attributes = [
        'status' => BuyerProfileStatus::Pending,
        'reviewed_by' => null,
        'reviewed_at' => null,
        'rejection_reason' => null,
    ];

    protected function casts(): array
    {
        return [
            'id_type' => BuyerIdType::class,
            'status' => BuyerProfileStatus::class,
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
