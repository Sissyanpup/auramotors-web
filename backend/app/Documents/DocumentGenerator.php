<?php

namespace App\Documents;

use App\Enums\TransactionDocumentType;
use App\Models\Transaction;
use App\Models\TransactionDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generator PDF dokumen transaksi (Sprint 7 / Epic 7). Setiap dokumen disimpan
 * ke disk `local` di folder `transaction-documents/{transaction_id}/` dan
 * dicatat di tabel `transaction_documents` sebagai referensi. Regenerasi
 * dokumen tipe yang sama akan menghapus baris & file lama supaya tidak
 * menumpuk versi ganda.
 */
class DocumentGenerator
{
    public function generateCommercialInvoice(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller']);
        $pdf = Pdf::loadView('pdfs.commercial-invoice', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::CommercialInvoice, $pdf->output());
    }

    public function generateDepositReceipt(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller']);
        $pdf = Pdf::loadView('pdfs.deposit-receipt', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::DepositReceipt, $pdf->output());
    }

    public function generateBillOfSale(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller']);
        $pdf = Pdf::loadView('pdfs.bill-of-sale', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::BillOfSale, $pdf->output());
    }

    public function generateSalesPurchaseAgreement(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller']);
        $pdf = Pdf::loadView('pdfs.sales-purchase-agreement', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::SalesPurchaseAgreement, $pdf->output());
    }

    public function generateEscrowDisbursementNote(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller.sellerProfile', 'payouts']);
        $pdf = Pdf::loadView('pdfs.escrow-disbursement-note', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::EscrowDisbursementNote, $pdf->output());
    }

    public function generateTaxInvoice(Transaction $transaction): TransactionDocument
    {
        $transaction->loadMissing(['vehicle', 'buyer', 'seller']);
        $pdf = Pdf::loadView('pdfs.tax-invoice', ['t' => $transaction]);

        return $this->store($transaction, TransactionDocumentType::TaxInvoice, $pdf->output());
    }

    private function store(Transaction $transaction, TransactionDocumentType $type, string $binary): TransactionDocument
    {
        // Hapus dokumen tipe yang sama supaya tidak duplikat kalau di-regenerate.
        $existing = $transaction->documents()->where('type', $type)->get();
        foreach ($existing as $doc) {
            Storage::disk('local')->delete($doc->path);
            $doc->delete();
        }

        $filename = $type->value.'-'.Str::random(8).'.pdf';
        $path = 'transaction-documents/'.$transaction->id.'/'.$filename;
        Storage::disk('local')->put($path, $binary);

        return $transaction->documents()->create([
            'type' => $type,
            'path' => $path,
            'generated_at' => now(),
        ]);
    }
}
