<?php

namespace App\Models;

use App\Enums\TransactionDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu tanda tangan elektronik satu pihak (buyer/seller) pada satu dokumen
 * transaksi. Append-only: unique (transaction, document_type, role) mencegah
 * tanda tangan ditukar setelah tersimpan.
 */
#[Fillable(['transaction_id', 'document_type', 'role', 'user_id', 'signature_path', 'ip_address', 'signed_at'])]
class TransactionDocumentSignature extends Model
{
    protected function casts(): array
    {
        return [
            'document_type' => TransactionDocumentType::class,
            'signed_at' => 'datetime',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
