@extends('pdfs._layout')

@section('title', 'Kuitansi - ' . $docNumber)
@section('doc_type', 'Kuitansi')
@section('doc_number', $docNumber)

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $price = $breakdown['vehicle_price'];
    $isFull = $breakdown['payment']['scheme'] === 'full';
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:14px; letter-spacing:0.2em; margin-top:0;">KUITANSI</p>

    <table class="info" style="font-size:12px;">
        <tr><td class="label">Telah terima dari</td><td><strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong> (NIK {{ $t->buyer->ktp_number ?? '-' }})</td></tr>
        <tr><td class="label">Uang sejumlah</td><td style="background:#fcfaf2; border:1px solid #e3d7a8; font-style:italic; text-transform:capitalize;">{{ \App\Support\Terbilang::rupiah($price) }}</td></tr>
        <tr>
            <td class="label">Untuk pembayaran</td>
            <td>
                Pembelian 1 (satu) unit kendaraan {{ $t->vehicle->brand }} {{ $t->vehicle->model }} tahun {{ $t->vehicle->year }},
                nomor rangka {{ $t->vehicle->vin ?: '(sesuai STNK/BPKB)' }}, berdasarkan Perjanjian Jual Beli {{ $t->invoice_number }}.
            </td>
        </tr>
    </table>

    <h2>Rincian Penerimaan</h2>
    <table class="grid">
        <tr><th>Cara Pembayaran</th><th class="num">Jumlah</th></tr>
        <tr><td>{{ $isFull ? 'Pelunasan penuh' : 'Uang muka' }} melalui rekening escrow {{ $legal['platform']['name'] }} ({{ $t->payment_approval_code ?? '-' }})</td><td class="num">{{ $rp($breakdown['payment']['dp_amount']) }}</td></tr>
        @unless($isFull)
            <tr><td>Sisa pelunasan dibayar langsung kepada Penjual saat serah terima</td><td class="num">{{ $rp($breakdown['payment']['remaining_amount']) }}</td></tr>
        @endunless
        <tr class="subtotal"><td>Total harga kendaraan</td><td class="num">{{ $rp($price) }}</td></tr>
    </table>
    <p class="disclaimer" style="color:#666; font-size:9px;">
        Premi asuransi dan biaya balik nama tidak termasuk dalam kuitansi ini karena bukan merupakan pembayaran kepada Penjual.
    </p>

    <div class="sigrow">
        <div class="sigcell" style="width:50%; text-align:left; vertical-align:middle;">
            <div style="border:2px solid #d4af37; padding:10px 14px; display:inline-block; font-size:15px; font-weight:bold;">
                {{ $rp($price) }}
            </div>
        </div>
        @include('pdfs._party-signature', ['label' => 'Yang Menerima (Penjual)', 'sig' => $signatures['seller'], 'width' => '50%', 'meterai' => true])
    </div>
@endsection
