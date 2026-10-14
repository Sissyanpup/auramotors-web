@extends('pdfs._layout')

@section('title', 'Surat Pernyataan Keabsahan - ' . $docNumber)
@section('doc_type', 'Surat Pernyataan')
@section('doc_number', $docNumber)

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        SURAT PERNYATAAN KEABSAHAN &amp; BEBAS SENGKETA KENDARAAN
    </p>

    <p class="body">Yang bertanda tangan di bawah ini:</p>
    <table class="info">
        <tr><td class="label">Nama</td><td><strong>{{ $t->seller->ktp_name ?: $t->seller->name }}</strong></td></tr>
        <tr><td class="label">NIK</td><td>{{ $t->seller->ktp_number ?? '-' }}</td></tr>
        <tr><td class="label">Email</td><td>{{ $t->seller->email }}</td></tr>
        <tr><td class="label">Kedudukan</td><td>Penjual pada transaksi {{ $t->invoice_number }}</td></tr>
    </table>

    <p class="body">dengan ini menyatakan dengan sesungguhnya bahwa kendaraan bermotor:</p>
    <table class="info">
        <tr><td class="label">Merek / Model / Tahun</td><td>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} &middot; {{ $t->vehicle->year }}</td></tr>
        <tr><td class="label">Nomor Rangka (VIN)</td><td>{{ $t->vehicle->vin ?: '— (sesuai STNK/BPKB)' }}</td></tr>
        <tr><td class="label">Jarak Tempuh</td><td>{{ number_format((int) $t->vehicle->mileage, 0, ',', '.') }} km</td></tr>
    </table>

    <ol class="body">
        <li>Adalah benar milik saya yang sah dan diperoleh secara sah, bukan hasil tindak pidana.</li>
        <li>Tidak sedang dijaminkan (fidusia/leasing/gadai), tidak dalam sengketa, sitaan, atau blokir instansi mana pun.</li>
        <li>Dokumen STNK, BPKB, dan faktur yang diserahkan adalah asli, sah, dan sesuai dengan fisik kendaraan
            (nomor rangka &amp; nomor mesin).</li>
        <li>Pajak Kendaraan Bermotor sampai tanggal serah terima telah/ akan saya lunasi, termasuk denda bila ada.</li>
        <li>Kondisi, jarak tempuh, dan riwayat kendaraan (termasuk banjir/kecelakaan berat) telah saya ungkapkan secara jujur
            pada listing dan laporan inspeksi.</li>
        <li>Apabila di kemudian hari pernyataan ini terbukti tidak benar, saya bersedia mengembalikan seluruh dana yang
            diterima, menanggung kerugian Pembeli, dan dituntut sesuai hukum yang berlaku (Pasal 263 &amp; 378 KUHP).</li>
    </ol>

    <p class="body">Demikian surat pernyataan ini saya buat dalam keadaan sadar dan tanpa paksaan dari pihak mana pun.</p>

    <div class="sigrow">
        <div class="sigcell" style="width:50%;"></div>
        @include('pdfs._party-signature', ['label' => 'Yang Menyatakan (Penjual)', 'sig' => $signatures['seller'], 'width' => '50%', 'meterai' => true])
    </div>
@endsection
