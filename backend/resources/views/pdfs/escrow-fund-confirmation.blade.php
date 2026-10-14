@extends('pdfs._layout')

@section('title', 'Konfirmasi Dana Escrow - ' . $docNumber)
@section('doc_type', 'Surat Konfirmasi Escrow')
@section('doc_number', $docNumber)
@section('brand_name', $legal['bank']['name'])
@section('brand_tag', $legal['bank']['division'])
@section('brand_style', 'color:#0b5c8a; font-size:15px;')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $isFull = $breakdown['payment']['scheme'] === 'full';
    $seller = $breakdown['seller_settlement'];
@endphp

@section('content')
    <p class="body" style="margin-top:0;">
        Jakarta, {{ ($t->paid_at ?? now())->translatedFormat('d F Y') }}<br>
        Perihal: <strong>Konfirmasi Penempatan Dana pada Rekening Escrow</strong>
    </p>

    <p class="body">
        Kepada Yth.<br>
        1. {{ $t->buyer->ktp_name ?: $t->buyer->name }} (Pembeli)<br>
        2. {{ $t->seller->ktp_name ?: $t->seller->name }} (Penjual)<br>
        3. {{ $legal['platform']['name'] }} (Agen Escrow)
    </p>

    <p class="body">
        Dengan hormat, bersama surat ini kami selaku bank kustodian menerangkan bahwa pada tanggal
        {{ $t->paid_at?->format('d F Y') ?? '-' }} pukul {{ $t->paid_at?->format('H:i') ?? '-' }} WIB telah diterima dana sebesar
        <strong>{{ $rp($t->amount) }}</strong> (<em>{{ \App\Support\Terbilang::rupiah($t->amount) }}</em>) dari
        {{ $t->buyer->ktp_name ?: $t->buyer->name }} ke rekening escrow berikut:
    </p>

    <table class="info">
        <tr><td class="label">Nama rekening</td><td>{{ $t->bank_transfer_account_holder }}</td></tr>
        <tr><td class="label">Nomor rekening</td><td>{{ $t->bank_transfer_account_number }} ({{ $t->bank_transfer_bank }})</td></tr>
        <tr><td class="label">Sub-rekening transaksi</td><td>{{ $t->invoice_number }}</td></tr>
        <tr><td class="label">Objek transaksi</td><td>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} ({{ $t->vehicle->year }})</td></tr>
        <tr><td class="label">Kode otorisasi</td><td>{{ $t->payment_approval_code ?? '-' }}</td></tr>
    </table>

    <h2>Alokasi Dana yang Ditahan</h2>
    <table class="grid">
        <tr><th>Peruntukan</th><th>Penerima Akhir</th><th class="num">Jumlah</th></tr>
        <tr><td>Porsi kendaraan ({{ $isFull ? 'pelunasan penuh' : 'uang muka' }})</td><td>Penjual, setelah dipotong biaya layanan {{ $rp($seller['commission']) }}</td><td class="num">{{ $rp($seller['escrow_vehicle_portion']) }}</td></tr>
        @if($breakdown['insurance']['total'] > 0)
            <tr><td>Premi asuransi kendaraan</td><td>{{ $legal['insurer']['name'] }}</td><td class="num">{{ $rp($breakdown['insurance']['total']) }}</td></tr>
        @endif
        <tr class="subtotal"><td colspan="2">Total dana ditahan</td><td class="num">{{ $rp($t->amount) }}</td></tr>
    </table>

    <h2>Syarat Pencairan</h2>
    <ol class="body">
        <li>Berita Acara Serah Terima ditandatangani elektronik oleh Penjual dan Pembeli.</li>
        <li>Penjual dan Pembeli masing-masing mengonfirmasi serah terima pada platform.</li>
        <li>Agen Escrow memberikan instruksi tertulis pelepasan dana kepada Bank.</li>
        <li>Dana dicairkan ke rekening Penjual yang terdaftar dan telah diverifikasi (KYC) oleh Agen Escrow.</li>
    </ol>
    <p class="body">
        Apabila terjadi sengketa, dana tetap ditahan sampai Agen Escrow menerbitkan putusan; dalam hal pengembalian dana
        (refund), dana dikembalikan ke rekening/kartu sumber milik Pembeli. Dana di rekening escrow tidak dapat
        dipergunakan, dijaminkan, maupun disita untuk kepentingan pihak lain.
    </p>

    <p class="body">Demikian surat konfirmasi ini kami sampaikan untuk dipergunakan sebagaimana mestinya.</p>

    <div class="sigrow">
        @include('pdfs._entity-signature', ['label' => 'Hormat kami, Bank Kustodian', 'entity' => $legal['bank'], 'width' => '50%'])
        @include('pdfs._entity-signature', ['label' => 'Mengetahui, Agen Escrow', 'entity' => $legal['platform'], 'width' => '50%'])
    </div>
@endsection
