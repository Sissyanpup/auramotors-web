@extends('pdfs._layout')

@section('title', 'SPPA - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Permohonan Asuransi')
@section('doc_number', $docNumber)
@section('brand_name', $legal['insurer']['name'])
@section('brand_tag', 'Asuransi Kendaraan Bermotor')
@section('brand_style', 'color:#1f4fa3; font-size:15px;')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $ins = $breakdown['insurance'];
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        SURAT PERMOHONAN PENUTUPAN ASURANSI (SPPA) KENDARAAN BERMOTOR
    </p>

    <h2>1. Data Calon Tertanggung</h2>
    <table class="info">
        <tr><td class="label">Nama sesuai KTP</td><td><strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong></td></tr>
        <tr><td class="label">NIK</td><td>{{ $t->buyer->ktp_number ?? '-' }}</td></tr>
        <tr><td class="label">Alamat</td><td>{{ $t->buyer_address ?? '-' }}</td></tr>
        <tr><td class="label">Telepon / Email</td><td>{{ $t->buyer_phone ?? '-' }} &middot; {{ $t->buyer->email }}</td></tr>
    </table>

    <h2>2. Data Kendaraan</h2>
    <table class="info">
        <tr><td class="label">Merek / Model / Tahun</td><td>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} &middot; {{ $t->vehicle->year }}</td></tr>
        <tr><td class="label">Nomor Rangka (VIN)</td><td>{{ $t->vehicle->vin ?: '-' }}</td></tr>
        <tr><td class="label">Jarak Tempuh</td><td>{{ number_format((int) $t->vehicle->mileage, 0, ',', '.') }} km</td></tr>
        <tr><td class="label">Penggunaan</td><td>Pribadi / dinas (bukan angkutan umum, taksi online, atau rental)</td></tr>
    </table>

    <h2>3. Jaminan yang Dimohonkan</h2>
    <table class="grid">
        <tr><th>Jaminan</th><th>Dasar Perhitungan</th><th class="num">Premi</th></tr>
        @foreach($ins['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="2">Total premi tahun pertama (TSI {{ $rp($ins['sum_insured']) }})</td><td class="num">{{ $rp($ins['total']) }}</td></tr>
    </table>

    <h2>4. Pernyataan Calon Tertanggung</h2>
    <p class="body">Dengan menandatangani SPPA ini, saya menyatakan dengan sebenarnya bahwa:</p>
    <ol class="body">
        <li>Kendaraan digunakan untuk keperluan pribadi dan <strong>tidak</strong> akan dioperasikan sebagai taksi online,
            ojek/transportasi daring, kendaraan sewaan (rental), maupun angkutan berbayar lainnya.</li>
        <li>Pada saat terjadi kerugian, kendaraan dikemudikan oleh pemilik atau pengemudi yang memiliki SIM sah dan masih berlaku
            sesuai golongan kendaraan.</li>
        <li>Saya bersedia data perjalanan/GPS kendaraan digunakan penanggung untuk verifikasi klaim.</li>
        <li>Riwayat klaim asuransi, kecelakaan, dan kepemilikan kendaraan yang saya sampaikan adalah benar dan lengkap; keterangan
            yang tidak benar dapat membatalkan polis dan klaim.</li>
        <li>Saya telah membaca dan memahami ringkasan ketentuan: risiko sendiri, ambang kerugian total {{ $ins['total_loss_threshold_percent'] }}%,
            dan penggantian kehilangan sebesar {{ $ins['theft_settlement_percent'] }}% dari harga pertanggungan.</li>
    </ol>

    <div class="legal-box">
        Pertanggungan baru mulai berlaku setelah premi diterima penuh melalui rekening escrow {{ $legal['platform']['name'] }}
        dan polis diterbitkan oleh {{ $legal['insurer']['name'] }} ({{ $legal['insurer']['license'] }}).
    </div>

    <div class="sigrow">
        @include('pdfs._party-signature', ['label' => 'Calon Tertanggung', 'sig' => $signatures['buyer'], 'width' => '50%'])
        @include('pdfs._entity-signature', ['label' => 'Diterima oleh Penanggung', 'entity' => $legal['insurer'], 'width' => '50%'])
    </div>
@endsection
