@extends('pdfs._layout')

@section('title', 'Bukti Pembayaran - ' . $docNumber)
@section('doc_type', 'Payment Advice')
@section('doc_number', $docNumber)
@section('brand_name', $legal['bank']['name'])
@section('brand_tag', 'Transaction Banking · SWIFT ' . $legal['bank']['swift'])
@section('brand_style', 'color:#0b5c8a; font-size:15px;')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $methodLabel = match ($t->payment_method) {
        'card' => 'Kartu Kredit / Debit (3-D Secure)',
        'paypal' => 'PayPal (dompet digital)',
        'virtual_account' => 'Virtual Account / Internet Banking',
        'xendit' => 'Payment Gateway Xendit',
        default => $t->payment_gateway?->value === 'xendit' ? 'Payment Gateway Xendit' : 'Simulasi pembayaran langsung',
    };
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        BUKTI PEMBAYARAN / PAYMENT ADVICE
    </p>
    <p class="body" style="text-align:center; margin-top:0;">
        <span class="status-paid" style="font-size:14px;">&#10003; TRANSAKSI BERHASIL</span>
    </p>

    <table class="grid">
        <tr><th style="width:40%;">Keterangan</th><th>Detail</th></tr>
        <tr><td>Tanggal &amp; waktu transaksi</td><td>{{ $t->paid_at?->format('d F Y, H:i:s') ?? '-' }} WIB</td></tr>
        <tr><td>Nomor referensi bank</td><td>{{ strtoupper(substr((string) $t->gateway_reference, 0, 18)) }}</td></tr>
        <tr><td>Kode otorisasi (approval code)</td><td><strong>{{ $t->payment_approval_code ?? '-' }}</strong></td></tr>
        <tr><td>Metode pembayaran</td><td>{{ $methodLabel }}</td></tr>
        <tr><td>Kanal / jaringan</td><td>{{ $t->payment_channel ?? '-' }}</td></tr>
        <tr><td>Rekening / kartu sumber</td><td>{{ $t->payment_account_masked ?? '-' }}</td></tr>
        <tr><td>Nama pembayar</td><td>{{ $t->payment_payer_name ?? ($t->buyer->ktp_name ?: $t->buyer->name) }}</td></tr>
        <tr><td>Rekening tujuan (escrow)</td><td>{{ $t->bank_transfer_bank }} {{ $t->bank_transfer_account_number }} a.n. {{ $t->bank_transfer_account_holder }}</td></tr>
        <tr><td>Berita / referensi</td><td>{{ $t->invoice_number }} &mdash; {{ $t->vehicle->brand }} {{ $t->vehicle->model }}</td></tr>
        <tr class="subtotal"><td>Jumlah didebet</td><td>{{ $rp($t->amount) }}</td></tr>
        <tr><td>Terbilang</td><td><em>{{ ucfirst(\App\Support\Terbilang::rupiah($t->amount)) }}</em></td></tr>
        <tr><td>Biaya transaksi</td><td>Rp 0 (ditanggung platform)</td></tr>
    </table>

    <h2>Rincian Dana</h2>
    <table class="grid">
        <tr><th>Komponen</th><th class="num">Jumlah</th></tr>
        @foreach($breakdown['due_now']['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td>Total</td><td class="num">{{ $rp($breakdown['due_now']['total']) }}</td></tr>
    </table>

    <div class="notice">
        Simpan bukti ini sebagai tanda pembayaran yang sah. Dana Anda <strong>tidak</strong> diteruskan langsung ke penjual,
        melainkan ditahan pada rekening escrow sampai serah terima kendaraan terkonfirmasi. Bank tidak pernah meminta
        PIN, OTP, atau CVV melalui telepon/email.
    </div>

    <div class="sigrow">
        @include('pdfs._entity-signature', ['label' => 'Bank Penerima', 'entity' => $legal['bank'], 'width' => '50%'])
        <div class="sigcell" style="width:50%; text-align:left; font-size:9.5px; color:#555; line-height:1.5;">
            {{ $legal['bank']['division'] }}<br>
            {{ $legal['bank']['address'] }}<br>
            {{ $legal['bank']['license'] }}<br>
            Dokumen ini dicetak oleh sistem dan sah tanpa tanda tangan basah.
        </div>
    </div>
@endsection
