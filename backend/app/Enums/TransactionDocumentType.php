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

    public function label(): string
    {
        return match ($this) {
            self::CommercialInvoice => 'Commercial Invoice',
            self::DepositReceipt => 'Deposit Receipt',
            self::BillOfSale => 'Bill of Sale',
            self::SalesPurchaseAgreement => 'Sales Purchase Agreement',
            self::EscrowDisbursementNote => 'Escrow Disbursement Note',
            self::TaxInvoice => 'Tax Invoice (PPnBM)',
        };
    }

    public function folder(): string
    {
        return match ($this) {
            self::CommercialInvoice,
            self::DepositReceipt,
            self::EscrowDisbursementNote,
            self::TaxInvoice => 'Financial',

            self::BillOfSale,
            self::SalesPurchaseAgreement => 'Legal',
        };
    }
}
