@extends('pdfs._layout')

@section('title', 'Berita Acara Serah Terima - ' . $docNumber)
@section('doc_type', 'Berita Acara Serah Terima')
@section('doc_number', $docNumber)

@php
    $items = [
        ['Unit kendaraan ' . $t->vehicle->brand . ' ' . $t->vehicle->model . ' (' . $t->vehicle->year . ')', '1 unit'],
        ['STNK asli (Surat Tanda Nomor Kendaraan)', '1 lembar'],
        ['BPKB asli (Buku Pemilik Kendaraan Bermotor)', '1 buku'],
        ['Faktur pembelian / kuitansi kepemilikan sebelumnya', '1 set'],
        ['Kunci utama & kunci cadangan / smart key', '2 buah'],
        ['Buku manual & buku servis', '1 set'],
        ['Perlengkapan standar (dongkrak, kunci roda, ban serep, segitiga pengaman)', '1 set'],
    ];
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        BERITA ACARA SERAH TERIMA KENDARAAN BERMOTOR
    </p>

    <p class="body">
        Pada hari ini telah dilakukan serah terima kendaraan bermotor berdasarkan Perjanjian Jual Beli
        <strong>{{ $t->invoice_number }}</strong> antara:
    </p>
    <div class="parties">
        <div class="party">
            <h3>Pihak yang Menyerahkan (Penjual)</h3>
            <p><strong>{{ $t->seller->ktp_name ?: $t->seller->name }}</strong></p>
            <p>NIK {{ $t->seller->ktp_number ?? '-' }}</p>
        </div>
        <div class="party">
            <h3>Pihak yang Menerima (Pembeli)</h3>
            <p><strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong></p>
            <p>NIK {{ $t->buyer->ktp_number ?? '-' }}</p>
            <p>{{ $t->buyer_address ?? '' }}</p>
        </div>
    </div>

    <h2>Data Kendaraan</h2>
    <table class="info">
        <tr><td class="label">Merek / Model / Tahun</td><td>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} &middot; {{ $t->vehicle->year }}</td></tr>
        <tr><td class="label">Nomor Rangka (VIN)</td><td>{{ $t->vehicle->vin ?: '-' }}</td></tr>
        <tr><td class="label">Jarak tempuh saat serah terima</td><td>{{ number_format((int) $t->vehicle->mileage, 0, ',', '.') }} km</td></tr>
        <tr><td class="label">Lokasi serah terima</td><td>{{ $t->buyer_address ?? $t->vehicle->location }}</td></tr>
    </table>

    <h2>Daftar Barang &amp; Dokumen yang Diserahkan</h2>
    <table class="grid">
        <tr><th style="width:6%;">No</th><th>Barang / Dokumen</th><th style="width:14%;">Jumlah</th><th style="width:16%;">Kondisi</th></tr>
        @foreach($items as $i => [$label, $qty])
            <tr><td>{{ $i + 1 }}</td><td>{{ $label }}</td><td>{{ $qty }}</td><td>&#9744; Lengkap / Baik</td></tr>
        @endforeach
    </table>

    <h2>Pernyataan</h2>
    <ol class="body">
        <li>Pembeli telah memeriksa kondisi fisik, mesin, dan kelengkapan kendaraan serta menyatakan sesuai dengan deskripsi
            listing dan laporan inspeksi.</li>
        <li>Sejak Berita Acara ini ditandatangani, tanggung jawab dan risiko atas kendaraan beralih dari Penjual kepada Pembeli
            @if(($breakdown['insurance']['type'] ?? 'none') !== 'none')
                , dengan perlindungan polis asuransi {{ $breakdown['insurance']['label'] }} No. {{ $t->insurance_policy_number ?? '(terbit setelah pembayaran)' }}
            @endif.
        </li>
        <li>Penandatanganan Berita Acara ini oleh kedua pihak merupakan salah satu syarat pencairan dana escrow kepada Penjual.</li>
        <li>Pelanggaran lalu lintas (tilang elektronik/ETLE) setelah tanggal serah terima menjadi tanggung jawab Pembeli.</li>
    </ol>

    <div class="sigrow">
        @include('pdfs._party-signature', ['label' => 'Yang Menyerahkan (Penjual)', 'sig' => $signatures['seller']])
        @include('pdfs._party-signature', ['label' => 'Yang Menerima (Pembeli)', 'sig' => $signatures['buyer']])
        @include('pdfs._entity-signature', ['label' => 'Saksi — Agen Escrow', 'entity' => $legal['platform']])
    </div>
@endsection
