@extends('pdfs._layout')

@section('title', 'Tax Invoice PPnBM - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Tax Invoice (PPnBM)')
@section('doc_number', 'TAX-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@php
    // Simulasi tarif PPnBM & PPN untuk kendaraan bermotor (bukan tarif real; dokumen mock).
    $ppnRate = 0.11;          // 11%
    $ppnbmRate = 0.20;        // 20% (kelas kendaraan mewah, dummy)
    $vehiclePrice = (float) $t->vehicle_price;
    // Basis dianggap harga sudah termasuk pajak (breakdown terbalik) — untuk demo saja.
    $ppn = round($vehiclePrice * $ppnRate / (1 + $ppnRate + $ppnbmRate), 2);
    $ppnbm = round($vehiclePrice * $ppnbmRate / (1 + $ppnRate + $ppnbmRate), 2);
    $dpp = $vehiclePrice - $ppn - $ppnbm;
@endphp

@section('content')
    <div class="notice">
        <strong>Faktur Pajak Simulasi.</strong> Dokumen ini adalah simulasi Faktur Pajak untuk keperluan
        demo platform, bukan Faktur Pajak resmi dari DJP. Untuk transaksi produksi, faktur pajak asli
        diterbitkan oleh sistem e-Faktur DJP setelah pelaporan oleh Penjual PKP.
    </div>

    <div class="parties" style="margin-top:16px;">
        <div class="party">
            <h3>Pengusaha Kena Pajak (Penjual)</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong></p>
            <p>{{ $t->seller->email ?? '' }}</p>
            @if($t->seller && $t->seller->ktp_number)
                <p>NIK/NPWP: {{ $t->seller->ktp_number }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Pembeli Barang Kena Pajak</h3>
            <p><strong>{{ $t->buyer->name ?? '-' }}</strong></p>
            <p>{{ $t->buyer->email ?? '' }}</p>
            @if($t->buyer && $t->buyer->ktp_number)
                <p>NIK/NPWP: {{ $t->buyer->ktp_number }}</p>
            @endif
            @if($t->buyer_address)
                <p>{{ $t->buyer_address }}</p>
            @endif
        </div>
    </div>

    <h2>Barang Kena Pajak</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th style="text-align:right;">Harga Jual</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} ({{ $t->vehicle->year ?? '' }})</strong><br>
                    <span style="color:#666; font-size:10px;">
                        Kendaraan bermotor bekas &middot; Klasifikasi PPnBM &sim;20%
                    </span>
                </td>
                <td class="num">Rp {{ number_format($vehiclePrice, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Perhitungan Pajak</h2>
    <table class="totals">
        <tr>
            <td class="label">Dasar Pengenaan Pajak (DPP)</td>
            <td class="value">Rp {{ number_format($dpp, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">PPN ({{ number_format($ppnRate * 100, 0) }}%)</td>
            <td class="value">Rp {{ number_format($ppn, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">PPnBM ({{ number_format($ppnbmRate * 100, 0) }}%)</td>
            <td class="value">Rp {{ number_format($ppnbm, 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Total Harga Jual (termasuk pajak)</td>
            <td class="value">Rp {{ number_format($vehiclePrice, 0, ',', '.') }}</td>
        </tr>
    </table>

    <p style="font-size:10px; line-height:1.5; margin-top:12px; color:#666;">
        Kode transaksi: 01 (penyerahan BKP). Faktur pajak diterbitkan pada saat penyerahan Barang Kena
        Pajak berupa kendaraan bermotor kepada Pembeli, sesuai UU HPP dan turunannya.
    </p>

    <div class="signature">
        <div class="sig">
            <div class="label">Penerbit</div>
            <div class="line">{{ $t->seller->name ?? '-' }}</div>
        </div>
        <div class="sig">
            <div class="label">Tanggal Faktur</div>
            <div class="line">{{ $t->paid_at?->format('d F Y') ?? now()->format('d F Y') }}</div>
        </div>
    </div>
@endsection
