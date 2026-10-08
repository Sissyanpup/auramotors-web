@extends('pdfs._layout')

@section('title', 'Bill of Sale - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Bill of Sale')
@section('doc_number', 'BOS-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@section('content')
    <p style="font-size:11px; line-height:1.6; margin-top:8px;">
        Pada hari ini, {{ now()->translatedFormat('l, d F Y') }}, dilakukan pemindahan hak
        kepemilikan atas kendaraan bermotor yang dijelaskan di bawah, dari <strong>Penjual</strong>
        kepada <strong>Pembeli</strong>, dengan syarat dan ketentuan sebagai berikut.
    </p>

    <h2>Para Pihak</h2>
    <div class="parties">
        <div class="party">
            <h3>Penjual</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong></p>
            <p>{{ $t->seller->email ?? '' }}</p>
            @if($t->seller && $t->seller->ktp_number)
                <p>NIK: {{ $t->seller->ktp_number }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Pembeli</h3>
            <p><strong>{{ $t->buyer->name ?? '-' }}</strong></p>
            <p>{{ $t->buyer->email ?? '' }}</p>
            @if($t->buyer && $t->buyer->ktp_number)
                <p>NIK: {{ $t->buyer->ktp_number }}</p>
            @endif
            @if($t->buyer_address)
                <p>Alamat: {{ $t->buyer_address }}</p>
            @endif
        </div>
    </div>

    <h2>Objek yang Dipindahtangankan</h2>
    <table class="info">
        <tr>
            <td class="label">Merek / Model / Tahun</td>
            <td><strong>{{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} &middot; {{ $t->vehicle->year ?? '' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Kilometer Tercatat</td>
            <td>{{ number_format((int) ($t->vehicle->mileage ?? 0), 0, ',', '.') }} km</td>
        </tr>
        <tr>
            <td class="label">Lokasi Serah Terima</td>
            <td>{{ $t->vehicle->location ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Nilai Transaksi</td>
            <td><strong>Rp {{ number_format((float) $t->vehicle_price, 0, ',', '.') }}</strong></td>
        </tr>
    </table>

    <h2>Konfirmasi Serah Terima</h2>
    <table class="info">
        <tr>
            <td class="label">Konfirmasi Pembeli</td>
            <td>{{ $t->buyer_confirmed_at?->format('d M Y H:i') ?? '-' }} WIB</td>
        </tr>
        <tr>
            <td class="label">Konfirmasi Penjual</td>
            <td>{{ $t->seller_confirmed_at?->format('d M Y H:i') ?? '-' }} WIB</td>
        </tr>
        <tr>
            <td class="label">Nomor Faktur Terkait</td>
            <td>{{ $t->invoice_number ?? ('TRX-' . $t->id) }}</td>
        </tr>
    </table>

    <div class="notice">
        <strong>Ketentuan Pengalihan.</strong>
        Kendaraan diserahkan <em>as-is</em> berdasarkan kondisi hasil inspeksi bersama pada saat
        serah-terima. Kedua pihak menyatakan telah memeriksa fisik &amp; kelengkapan surat kendaraan
        (BPKB, STNK) dan menerima kondisi yang ada. Balik nama menjadi tanggung jawab Pembeli.
    </div>

    <div class="signature">
        <div class="sig">
            <div class="label">Penjual</div>
            <div class="line">{{ $t->seller->name ?? '-' }}</div>
        </div>
        <div class="sig">
            <div class="label">Pembeli</div>
            <div class="line">{{ $t->buyer->name ?? '-' }}</div>
        </div>
    </div>
@endsection
