<?php

namespace App\Documents;

use App\Enums\InsuranceType;
use App\Enums\TransactionDocumentType;
use App\Models\Transaction;
use App\Models\TransactionDocument;
use App\Support\PriceBreakdownCalculator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generator PDF dokumen transaksi (Sprint 7 / Epic 7, diperluas Sprint 10).
 * Setiap dokumen disimpan ke disk `local` di folder
 * `transaction-documents/{transaction_id}/` dan dicatat di tabel
 * `transaction_documents`. Regenerasi dokumen tipe yang sama menghapus baris &
 * file lama supaya tidak menumpuk versi ganda.
 *
 * Semua template menerima data yang sama (lihat viewData()): transaksi, rincian
 * harga dari PriceBreakdownCalculator, tanda tangan para pihak, identitas badan
 * hukum dari config/legal.php, nomor dokumen, dan kode verifikasi.
 */
class DocumentGenerator
{
    /** Prefix nomor dokumen — menggantikan prefix "INV" pada nomor invoice. */
    private const NUMBER_PREFIX = [
        'price_breakdown' => 'RHB',
        'insurance_application' => 'SPPA',
        'bank_payment_advice' => 'PAY',
        'escrow_fund_confirmation' => 'ESC',
        'seller_ownership_statement' => 'SPK',
        'ownership_transfer_poa' => 'SKBN',
        'handover_minutes' => 'BAST',
        'sale_receipt' => 'KWT',
    ];

    public function __construct(private readonly PriceBreakdownCalculator $pricing) {}

    public function generate(Transaction $transaction, TransactionDocumentType $type): TransactionDocument
    {
        // Instance hasil create() belum memuat kolom ber-default DB (payment_scheme, signature path, dll.)
        // dan strict mode (preventAccessingMissingAttributes) akan melempar exception saat template membacanya.
        if (! array_key_exists('buyer_signature_path', $transaction->getAttributes())) {
            $transaction->refresh();
        }

        $transaction->loadMissing(['vehicle', 'buyer', 'seller.sellerProfile', 'payouts', 'documentSignatures']);

        // Tanggal di surat resmi ditulis dalam Bahasa Indonesia ("Jumat, 9 Oktober 2026")
        // tanpa mengubah locale aplikasi di luar proses render PDF.
        $previousLocale = Carbon::getLocale();
        Carbon::setLocale('id');

        try {
            $binary = Pdf::loadView($type->view(), $this->viewData($transaction, $type))->output();
        } finally {
            Carbon::setLocale($previousLocale);
        }

        return $this->store($transaction, $type, $binary);
    }

    /**
     * Dokumen yang terbit saat invoice dibuat: tagihan, kontrak, rincian harga,
     * dan SPPA (kalau pembeli memilih asuransi).
     */
    public function generateCheckoutDocuments(Transaction $transaction): void
    {
        $this->generate($transaction, TransactionDocumentType::CommercialInvoice);
        $this->generate($transaction, TransactionDocumentType::SalesPurchaseAgreement);
        $this->generate($transaction, TransactionDocumentType::PriceBreakdown);

        if ($this->hasInsurance($transaction)) {
            $this->generate($transaction, TransactionDocumentType::InsuranceApplication);
        }
    }

    /**
     * Dokumen yang terbit setelah dana masuk escrow: bukti bayar & surat bank,
     * faktur pajak, polis asuransi, serta surat-surat serah terima yang perlu
     * ditandatangani buyer/seller. Nomor polis diterbitkan di sini karena polis
     * baru berlaku setelah premi dibayar.
     */
    public function generatePaidDocuments(Transaction $transaction): void
    {
        if ($this->hasInsurance($transaction) && $transaction->insurance_policy_number === null) {
            $transaction->update([
                'insurance_policy_number' => sprintf('POL/AURA/%s/%06d-%s', now()->format('Y'), $transaction->id, Str::upper(Str::random(4))),
            ]);
        }

        $this->generate($transaction, TransactionDocumentType::DepositReceipt);
        $this->generate($transaction, TransactionDocumentType::TaxInvoice);
        $this->generate($transaction, TransactionDocumentType::BankPaymentAdvice);
        $this->generate($transaction, TransactionDocumentType::EscrowFundConfirmation);

        if ($this->hasInsurance($transaction)) {
            $this->generate($transaction, TransactionDocumentType::InsurancePolicy);
        }

        $this->generate($transaction, TransactionDocumentType::SellerOwnershipStatement);
        $this->generate($transaction, TransactionDocumentType::OwnershipTransferPowerOfAttorney);
        $this->generate($transaction, TransactionDocumentType::HandoverMinutes);
        $this->generate($transaction, TransactionDocumentType::SaleReceipt);
    }

    public function generateCommercialInvoice(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::CommercialInvoice);
    }

    public function generateDepositReceipt(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::DepositReceipt);
    }

    public function generateBillOfSale(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::BillOfSale);
    }

    public function generateSalesPurchaseAgreement(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::SalesPurchaseAgreement);
    }

    public function generateEscrowDisbursementNote(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::EscrowDisbursementNote);
    }

    public function generateTaxInvoice(Transaction $transaction): TransactionDocument
    {
        return $this->generate($transaction, TransactionDocumentType::TaxInvoice);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(Transaction $transaction, TransactionDocumentType $type): array
    {
        return [
            't' => $transaction,
            'docType' => $type,
            'breakdown' => $this->pricing->forTransaction($transaction),
            'signatures' => $this->signatures($transaction, $type),
            'legal' => config('legal'),
            'docNumber' => $this->documentNumber($transaction, $type),
            'verificationCode' => Str::upper(substr(hash_hmac('sha256', $transaction->id.'|'.$type->value.'|'.$transaction->invoice_number, (string) config('app.key')), 0, 16)),
        ];
    }

    /**
     * Tanda tangan buyer & seller untuk dokumen ini, siap disisipkan ke PDF
     * sebagai data URI. SPA lama (sebelum tabel signature per dokumen) masih
     * dibaca dari kolom `*_signature_path` di tabel transactions.
     *
     * @return array<'buyer'|'seller', array{name: string, signed_at: mixed, image: string|null}>
     */
    private function signatures(Transaction $transaction, TransactionDocumentType $type): array
    {
        $result = [];

        foreach (['buyer', 'seller'] as $role) {
            $signature = $transaction->documentSignatures
                ->first(fn ($sig) => $sig->document_type === $type && $sig->role === $role);

            $path = $signature?->signature_path;
            $signedAt = $signature?->signed_at;

            if ($signature === null && $type === TransactionDocumentType::SalesPurchaseAgreement) {
                $path = $transaction->{$role.'_signature_path'};
                $signedAt = $transaction->{$role.'_signed_at'};
            }

            $image = $path !== null && Storage::disk('local')->exists($path)
                ? 'data:image/png;base64,'.base64_encode(Storage::disk('local')->get($path))
                : null;

            $result[$role] = [
                'name' => (string) ($transaction->{$role}?->ktp_name ?: $transaction->{$role}?->name ?: '-'),
                'signed_at' => $signedAt,
                'image' => $image,
            ];
        }

        return $result;
    }

    private function documentNumber(Transaction $transaction, TransactionDocumentType $type): string
    {
        if ($type === TransactionDocumentType::InsurancePolicy && $transaction->insurance_policy_number) {
            return $transaction->insurance_policy_number;
        }

        $base = $transaction->invoice_number ?? 'TRX-'.$transaction->id;
        $prefix = self::NUMBER_PREFIX[$type->value] ?? null;

        if ($prefix === null) {
            return $base;
        }

        return str_starts_with($base, 'INV') ? $prefix.substr($base, 3) : $prefix.'/'.$base;
    }

    private function hasInsurance(Transaction $transaction): bool
    {
        return $transaction->insurance_type !== null && $transaction->insurance_type !== InsuranceType::None;
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
