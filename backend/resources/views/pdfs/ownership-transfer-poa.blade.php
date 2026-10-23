@extends('pdfs._layout')

@section('title', 'Surat Kuasa Balik Nama - ' . $docNumber)
@section('doc_type', 'Surat Kuasa')
@section('doc_number', $docNumber)

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        SURAT KUASA BALIK NAMA KENDARAAN BERMOTOR
    </p>

    <p class="body">Yang bertanda tangan di bawah ini:</p>
    <table class="info">
        <tr><td class="label">Nama</td><td><strong>{{ $t->seller->ktp_name ?: $t->seller->name }}</strong></td></tr>
        <tr><td class="label">NIK</td><td>{{ $t->seller->ktp_number ?? '-' }}</td></tr>
        <tr><td class="label">Selanjutnya disebut</td><td><strong>PEMBERI KUASA</strong> (pemilik lama / Penjual)</td></tr>
    </table>

    <p class="body">dengan ini memberikan kuasa kepada:</p>
    <table class="info">
        <tr><td class="label">Nama</td><td><strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong></td></tr>
        <tr><td class="label">NIK</td><td>{{ $t->buyer->ktp_number ?? '-' }}</td></tr>
        <tr><td class="label">Alamat</td><td>{{ $t->buyer_address ?? '-' }}</td></tr>
        <tr><td class="label">Selanjutnya disebut</td><td><strong>PENERIMA KUASA</strong> (pemilik baru / Pembeli)</td></tr>
    </table>

    <p class="body" style="text-align:center; font-weight:bold;">&mdash; KHUSUS &mdash;</p>

    <p class="body">
        Untuk dan atas nama Pemberi Kuasa mengurus proses <strong>balik nama</strong> kendaraan
        {{ $t->vehicle->brand }} {{ $t->vehicle->model }} tahun {{ $t->vehicle->year }}, nomor rangka
        {{ $t->vehicle->vin ?: '(sesuai STNK/BPKB)' }}, pada Kantor Bersama Samsat dan Kepolisian Daerah setempat, termasuk namun
        tidak terbatas pada:
    </p>
    <ol class="body">
        <li>Menghadap pejabat Samsat/Ditlantas Polda, mengajukan permohonan mutasi dan perubahan identitas pemilik.</li>
        <li>Melakukan cek fisik kendaraan dan menandatangani formulir yang diperlukan.</li>
        <li>Membayar BBN-KB, PKB, SWDKLLJ, dan PNBP penerbitan STNK, TNKB, serta BPKB atas nama Penerima Kuasa
            (estimasi {{ $rp($breakdown['registration']['total']) }}, ditanggung Penerima Kuasa{{ ($breakdown['registration']['in_escrow'] ?? false) ? ' dan telah dibayar bersama uang muka melalui Rekening Escrow' : '' }}).</li>
        <li>Menerima STNK, TNKB, dan BPKB baru atas nama Penerima Kuasa.</li>
    </ol>

    <p class="body">
        Kuasa ini diberikan dengan hak substitusi, tidak dapat ditarik kembali, dan tidak berakhir karena sebab-sebab yang
        tercantum dalam Pasal 1813 KUHPerdata, karena merupakan bagian tidak terpisahkan dari Perjanjian Jual Beli
        {{ $t->invoice_number }} yang telah dibayar melalui rekening escrow.
    </p>

    <div class="sigrow">
        @include('pdfs._party-signature', ['label' => 'Penerima Kuasa (Pembeli)', 'sig' => $signatures['buyer'], 'width' => '50%'])
        @include('pdfs._party-signature', ['label' => 'Pemberi Kuasa (Penjual)', 'sig' => $signatures['seller'], 'width' => '50%', 'meterai' => true])
    </div>
@endsection
