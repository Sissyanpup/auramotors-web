<?php

namespace App\Models;

use App\Enums\TransactionDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['transaction_id', 'type', 'path', 'generated_at'])]
class TransactionDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => TransactionDocumentType::class,
            'generated_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
