<?php

namespace App\Http\Controllers;

use App\Documents\DocumentSigning;
use App\Enums\TransactionDocumentType;
use App\Http\Requests\DocumentSignatureRequest;
use App\Http\Requests\SignatureRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;

class SignatureController extends Controller
{
    /**
     * Endpoint lama (Epic 7B): menandatangani SPA saja. Dipertahankan untuk
     * kompatibilitas — sekarang diteruskan ke DocumentSigning yang sama.
     */
    public function store(SignatureRequest $request, Transaction $transaction, DocumentSigning $signing): TransactionResource
    {
        $this->authorize('view', $transaction);

        $signing->sign(
            $transaction,
            $request->user(),
            [TransactionDocumentType::SalesPurchaseAgreement],
            $request->binaryPng(),
            $request->ip(),
        );

        return $this->respond($transaction);
    }

    /**
     * Menandatangani satu atau beberapa surat transaksi sekaligus (SPA, SPPA,
     * Surat Kuasa Balik Nama, BAST, Kuitansi, Surat Pernyataan) dengan satu
     * gambar tanda tangan dari canvas signature pad.
     */
    public function storeForDocuments(DocumentSignatureRequest $request, Transaction $transaction, DocumentSigning $signing): TransactionResource
    {
        $this->authorize('view', $transaction);

        $signing->sign(
            $transaction,
            $request->user(),
            $request->documentTypes(),
            $request->binaryPng(),
            $request->ip(),
        );

        return $this->respond($transaction);
    }

    private function respond(Transaction $transaction): TransactionResource
    {
        return new TransactionResource($transaction->fresh(['vehicle.photos', 'documents', 'documentSignatures', 'statusHistories.actor']));
    }
}
