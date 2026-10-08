<?php

namespace App\Enums;

enum ShipmentDocumentType: string
{
    case ExportDeclaration = 'export_declaration';
    case BillOfLading = 'bill_of_lading';
    case AirWaybill = 'air_waybill';
    case ImportDeclaration = 'import_declaration';
    case CustomsDutyReceipt = 'customs_duty_receipt';
    case CertificateOfConformity = 'certificate_of_conformity';
    case ProofOfDelivery = 'proof_of_delivery';
    case LetterOfAcceptance = 'letter_of_acceptance';

    public function label(): string
    {
        return match ($this) {
            self::ExportDeclaration => 'Export Declaration',
            self::BillOfLading => 'Bill of Lading',
            self::AirWaybill => 'Air Waybill',
            self::ImportDeclaration => 'Import Declaration',
            self::CustomsDutyReceipt => 'Customs Duty Receipt',
            self::CertificateOfConformity => 'Certificate of Conformity',
            self::ProofOfDelivery => 'Proof of Delivery',
            self::LetterOfAcceptance => 'Letter of Acceptance',
        };
    }

    /**
     * Kategori dokumen untuk grouping di UI (folder virtual).
     */
    public function category(): string
    {
        return match ($this) {
            self::ExportDeclaration, self::BillOfLading, self::AirWaybill => 'Ekspor & Pengiriman',
            self::ImportDeclaration, self::CustomsDutyReceipt, self::CertificateOfConformity => 'Bea Cukai',
            self::ProofOfDelivery, self::LetterOfAcceptance => 'Penerimaan',
        };
    }

    /**
     * Siapa yang boleh upload dokumen tipe ini (owner = seller / buyer / admin).
     */
    public function uploaderRole(): string
    {
        return match ($this) {
            self::ExportDeclaration, self::BillOfLading, self::AirWaybill => 'seller',
            self::ImportDeclaration, self::CustomsDutyReceipt, self::CertificateOfConformity => 'admin',
            self::ProofOfDelivery, self::LetterOfAcceptance => 'buyer',
        };
    }
}
