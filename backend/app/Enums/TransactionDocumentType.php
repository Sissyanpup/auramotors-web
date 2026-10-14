<?php

namespace App\Enums;

enum TransactionDocumentType: string
{
    case CommercialInvoice = 'commercial_invoice';
    case DepositReceipt = 'deposit_receipt';
    case BillOfSale = 'bill_of_sale';
    case SalesPurchaseAgreement = 'sales_purchase_agreement';
    case EscrowDisbursementNote = 'escrow_disbursement_note';
    case TaxInvoice = 'tax_invoice';
    case PriceBreakdown = 'price_breakdown';
    case InsuranceApplication = 'insurance_application';
    case InsurancePolicy = 'insurance_policy';
    case BankPaymentAdvice = 'bank_payment_advice';
    case EscrowFundConfirmation = 'escrow_fund_confirmation';
    case SellerOwnershipStatement = 'seller_ownership_statement';
    case OwnershipTransferPowerOfAttorney = 'ownership_transfer_poa';
    case HandoverMinutes = 'handover_minutes';
    case SaleReceipt = 'sale_receipt';

    public function label(): string
    {
        return match ($this) {
            self::CommercialInvoice => 'Commercial Invoice',
            self::DepositReceipt => 'Deposit Receipt',
            self::BillOfSale => 'Bill of Sale',
            self::SalesPurchaseAgreement => 'Perjanjian Jual Beli (SPA)',
            self::EscrowDisbursementNote => 'Escrow Disbursement Note',
            self::TaxInvoice => 'Tax Invoice (PPnBM)',
            self::PriceBreakdown => 'Rincian Harga, Pajak & Biaya',
            self::InsuranceApplication => 'Surat Permohonan Penutupan Asuransi (SPPA)',
            self::InsurancePolicy => 'Ikhtisar Polis Asuransi Kendaraan',
            self::BankPaymentAdvice => 'Bukti Pembayaran Bank (Payment Advice)',
            self::EscrowFundConfirmation => 'Surat Konfirmasi Penempatan Dana Escrow',
            self::SellerOwnershipStatement => 'Surat Pernyataan Keabsahan Kendaraan',
            self::OwnershipTransferPowerOfAttorney => 'Surat Kuasa Balik Nama',
            self::HandoverMinutes => 'Berita Acara Serah Terima (BAST)',
            self::SaleReceipt => 'Kuitansi Jual Beli Bermeterai',
        };
    }

    public function folder(): string
    {
        return match ($this) {
            self::CommercialInvoice,
            self::DepositReceipt,
            self::EscrowDisbursementNote,
            self::TaxInvoice,
            self::PriceBreakdown,
            self::SaleReceipt => 'Financial',

            self::BillOfSale,
            self::SalesPurchaseAgreement,
            self::SellerOwnershipStatement,
            self::OwnershipTransferPowerOfAttorney,
            self::HandoverMinutes => 'Legal',

            self::InsuranceApplication,
            self::InsurancePolicy => 'Insurance',

            self::BankPaymentAdvice,
            self::EscrowFundConfirmation => 'Bank',
        };
    }

    /**
     * Pihak transaksi yang wajib menandatangani dokumen ini secara elektronik.
     * Dokumen tanpa penanda tangan (array kosong) diterbitkan & ditandatangani
     * otomatis oleh badan hukum penerbitnya (platform, bank, atau asuradur).
     *
     * @return array<int, 'buyer'|'seller'>
     */
    public function requiredSigners(): array
    {
        return match ($this) {
            self::SalesPurchaseAgreement,
            self::OwnershipTransferPowerOfAttorney,
            self::HandoverMinutes => ['seller', 'buyer'],

            self::InsuranceApplication => ['buyer'],

            self::SellerOwnershipStatement,
            self::SaleReceipt => ['seller'],

            default => [],
        };
    }

    public function isSignable(): bool
    {
        return $this->requiredSigners() !== [];
    }

    /**
     * Template Blade di resources/views/pdfs/ untuk tipe dokumen ini.
     */
    public function view(): string
    {
        return 'pdfs.'.str_replace('_', '-', $this->value);
    }
}
