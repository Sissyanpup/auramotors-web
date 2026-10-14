<?php

namespace App\Documents;

use App\Enums\TransactionDocumentType;
use App\Models\Transaction;
use App\Models\TransactionDocumentSignature;
use App\Models\User;
use App\Support\SignedDocumentUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Tanda tangan elektronik per dokumen (Sprint 10). Buyer/seller menandatangani
 * satu atau beberapa surat sekaligus dengan satu gambar tanda tangan dari
 * canvas. Dokumen hanya bisa ditandatangani setelah PDF-nya diterbitkan —
 * jadi tahap transaksi otomatis menentukan surat mana yang sudah bisa diteken
 * (mis. BAST & Surat Kuasa baru terbit setelah pembayaran masuk escrow).
 *
 * SPA tetap juga menulis kolom `*_signature_path` di tabel transactions karena
 * Bill of Sale & UI lama membaca dari sana.
 */
class DocumentSigning
{
    public function __construct(private readonly DocumentGenerator $docs) {}

    /**
     * @param  array<int, TransactionDocumentType>  $types
     */
    public function sign(Transaction $transaction, User $user, array $types, string $png, ?string $ipAddress): void
    {
        $role = $this->roleOf($transaction, $user);
        abort_if($role === null, 403, 'Hanya buyer atau seller transaksi yang bisa menandatangani.');

        $transaction->load(['documents', 'documentSignatures']);
        $roleLabel = $role === 'buyer' ? 'pembeli' : 'penjual';

        foreach ($types as $type) {
            abort_unless(
                in_array($role, $type->requiredSigners(), true),
                422,
                "{$type->label()} tidak memerlukan tanda tangan {$roleLabel}."
            );
            abort_unless(
                $transaction->documents->contains('type', $type),
                409,
                "{$type->label()} belum diterbitkan untuk transaksi ini."
            );
            abort_if(
                $this->hasSigned($transaction, $type, $role),
                409,
                "Anda sudah menandatangani {$type->label()}. Tanda tangan tidak dapat diubah."
            );
        }

        $path = 'signatures/'.$transaction->id.'/'.$role.'-'.Str::random(8).'.png';
        Storage::disk('local')->put($path, $png);

        DB::transaction(function () use ($transaction, $user, $types, $role, $path, $ipAddress) {
            foreach ($types as $type) {
                TransactionDocumentSignature::create([
                    'transaction_id' => $transaction->id,
                    'document_type' => $type,
                    'role' => $role,
                    'user_id' => $user->id,
                    'signature_path' => $path,
                    'ip_address' => $ipAddress,
                    'signed_at' => now(),
                ]);

                if ($type === TransactionDocumentType::SalesPurchaseAgreement) {
                    $transaction->update([
                        $role.'_signature_path' => $path,
                        $role.'_signed_at' => now(),
                    ]);
                }
            }
        });

        // Regenerasi PDF supaya tanda tangan yang baru langsung tercetak.
        $fresh = $transaction->fresh(['documentSignatures']);
        foreach ($types as $type) {
            $this->docs->generate($fresh, $type);
        }

        // Bill of Sale memuat tanda tangan SPA dan hanya ada setelah serah-terima tercatat.
        if (in_array(TransactionDocumentType::SalesPurchaseAgreement, $types, true)
            && $fresh->escrow_status !== null && $fresh->buyer_confirmed_at && $fresh->seller_confirmed_at) {
            $this->docs->generateBillOfSale($fresh);
        }
    }

    /**
     * Daftar dokumen yang perlu ditandatangani beserta statusnya per pihak,
     * untuk panel "Pusat Tanda Tangan" di frontend.
     *
     * @return array<int, array<string, mixed>>
     */
    public function checklist(Transaction $transaction): array
    {
        $transaction->loadMissing(['documents', 'documentSignatures', 'buyer', 'seller']);

        $items = [];
        foreach ($transaction->documents as $document) {
            if (! $document->type->isSignable()) {
                continue;
            }

            $signers = [];
            foreach ($document->type->requiredSigners() as $role) {
                $signedAt = $this->signedAt($transaction, $document->type, $role);
                $signers[] = [
                    'role' => $role,
                    'name' => $transaction->{$role}?->name,
                    'signed_at' => $signedAt,
                ];
            }

            $items[] = [
                'type' => $document->type->value,
                'label' => $document->type->label(),
                'folder' => $document->type->folder(),
                'download_url' => SignedDocumentUrl::for('transaction-documents.show', [$transaction->id, $document->id]),
                'signers' => $signers,
                'is_complete' => collect($signers)->every(fn (array $signer) => $signer['signed_at'] !== null),
            ];
        }

        // Urutkan sesuai urutan enum supaya alur baca konsisten (kontrak dulu, kuitansi terakhir).
        $order = array_map(fn (TransactionDocumentType $type) => $type->value, TransactionDocumentType::cases());
        usort($items, fn (array $a, array $b) => array_search($a['type'], $order, true) <=> array_search($b['type'], $order, true));

        return $items;
    }

    /**
     * @return 'buyer'|'seller'|null
     */
    public function roleOf(Transaction $transaction, User $user): ?string
    {
        return match ($user->id) {
            (int) $transaction->buyer_id => 'buyer',
            (int) $transaction->seller_id => 'seller',
            default => null,
        };
    }

    private function hasSigned(Transaction $transaction, TransactionDocumentType $type, string $role): bool
    {
        return $this->signedAt($transaction, $type, $role) !== null;
    }

    private function signedAt(Transaction $transaction, TransactionDocumentType $type, string $role): mixed
    {
        $signature = $transaction->documentSignatures
            ->first(fn (TransactionDocumentSignature $sig) => $sig->document_type === $type && $sig->role === $role);

        if ($signature !== null) {
            return $signature->signed_at;
        }

        // SPA yang ditandatangani sebelum tabel signature per dokumen ada.
        if ($type === TransactionDocumentType::SalesPurchaseAgreement) {
            return $transaction->{$role.'_signed_at'};
        }

        return null;
    }
}
