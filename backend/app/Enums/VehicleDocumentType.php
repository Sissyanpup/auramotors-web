<?php

namespace App\Enums;

enum VehicleDocumentType: string
{
    case Stnk = 'stnk';
    case Bpkb = 'bpkb';
    case ServiceHistory = 'service_history';
    case InspectionReport = 'inspection_report';
    case CertificateOfAuthenticity = 'certificate_of_authenticity';

    public function label(): string
    {
        return match ($this) {
            self::Stnk => 'STNK',
            self::Bpkb => 'BPKB',
            self::ServiceHistory => 'Service History Log',
            self::InspectionReport => 'Third-Party Inspection Report',
            self::CertificateOfAuthenticity => 'Certificate of Authenticity',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Stnk => 'Surat Tanda Nomor Kendaraan (bukti registrasi tahunan).',
            self::Bpkb => 'Buku Pemilik Kendaraan Bermotor (bukti kepemilikan).',
            self::ServiceHistory => 'Buku servis resmi / log perawatan dari diler.',
            self::InspectionReport => 'Laporan inspeksi pra-jual dari inspektor pihak ketiga (mis. SGS, DEKRA).',
            self::CertificateOfAuthenticity => 'Sertifikat keaslian dari manufaktur (untuk kendaraan langka / limited edition).',
        };
    }
}
