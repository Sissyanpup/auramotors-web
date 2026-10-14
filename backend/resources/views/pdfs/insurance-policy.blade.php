@extends('pdfs._layout')

@section('title', 'Ikhtisar Polis - ' . $docNumber)
@section('doc_type', 'Ikhtisar Polis')
@section('doc_number', $docNumber)
@section('brand_name', $legal['insurer']['name'])
@section('brand_tag', 'Asuransi Kendaraan Bermotor')
@section('brand_style', 'color:#1f4fa3; font-size:15px;')

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $ins = $breakdown['insurance'];
    $start = $t->paid_at ?? now();
    $isAllRisk = $ins['type'] === 'all_risk';
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        IKHTISAR POLIS ASURANSI KENDARAAN BERMOTOR (POLICY SCHEDULE)
    </p>

    <table class="info">
        <tr><td class="label">Nomor Polis</td><td><strong>{{ $docNumber }}</strong></td></tr>
        <tr><td class="label">Tertanggung</td><td>{{ $t->buyer->ktp_name ?: $t->buyer->name }} &middot; NIK {{ $t->buyer->ktp_number ?? '-' }}</td></tr>
        <tr><td class="label">Alamat Tertanggung</td><td>{{ $t->buyer_address ?? '-' }}</td></tr>
        <tr><td class="label">Objek Pertanggungan</td><td>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} ({{ $t->vehicle->year }}) &middot; VIN {{ $t->vehicle->vin ?: '-' }}</td></tr>
        <tr><td class="label">Periode Asuransi</td><td>{{ $start->format('d F Y') }} pukul 12.00 WIB s.d. {{ $start->copy()->addYear()->format('d F Y') }} pukul 12.00 WIB</td></tr>
        <tr><td class="label">Jenis Jaminan</td><td><strong>{{ $ins['label'] }}</strong></td></tr>
        <tr><td class="label">Harga Pertanggungan</td><td>{{ $rp($ins['sum_insured']) }}</td></tr>
        <tr><td class="label">Penerima Manfaat</td><td>Tertanggung (Pembeli) &mdash; terhitung sejak serah terima kendaraan</td></tr>
    </table>

    <h2>Rincian Premi</h2>
    <table class="grid">
        <tr><th>Jaminan</th><th>Dasar Perhitungan</th><th class="num">Premi</th></tr>
        @foreach($ins['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="2">Total premi &mdash; LUNAS via escrow ({{ $t->invoice_number }})</td><td class="num">{{ $rp($ins['total']) }}</td></tr>
    </table>

    <h2>Ringkasan Jaminan</h2>
    <table class="grid">
        <tr><th style="width:38%;">Risiko</th><th>Ketentuan</th></tr>
        <tr>
            <td>Kerugian total (total loss)</td>
            <td>Kerusakan &ge; {{ $ins['total_loss_threshold_percent'] }}% dari harga kendaraan sesaat sebelum kejadian; diganti sesuai harga pasar maksimal harga pertanggungan.</td>
        </tr>
        <tr>
            <td>Kehilangan / pencurian</td>
            <td>Diganti <strong>{{ $ins['theft_settlement_percent'] }}%</strong> dari harga pertanggungan setelah laporan polisi &amp; masa tunggu 60 hari.</td>
        </tr>
        <tr>
            <td>Kerusakan sebagian</td>
            <td>
                @if($isAllRisk)
                    Dijamin. Perbaikan di bengkel rekanan, dikenakan risiko sendiri {{ $rp($ins['deductible_per_claim']) }} per kejadian.
                @else
                    Tidak dijamin (polis TLO hanya menjamin kerugian total &amp; kehilangan).
                @endif
            </td>
        </tr>
        @foreach($ins['addons'] as $code)
            @php $addon = config("pricing.insurance.addons.{$code}"); @endphp
            @if($addon)
                <tr><td>{{ $addon['label'] }}</td><td>{{ $addon['description'] }}</td></tr>
            @endif
        @endforeach
    </table>

    <h2>Prosedur Klaim</h2>
    <ol class="body">
        <li>Laporkan kejadian ke hotline {{ $legal['insurer']['hotline'] }} paling lambat 5 hari kerja (kehilangan: 2&times;24 jam disertai laporan polisi).</li>
        <li>@if(in_array('towing_replacement', $ins['addons'], true))
                Gunakan layanan derek 24 jam; kendaraan dibawa ke bengkel rekanan dan mobil pengganti disediakan selama perbaikan (maks. 7 hari).
            @else
                Bawa kendaraan ke bengkel rekanan untuk survei kerusakan.
            @endif
        </li>
        <li>Penanggung melakukan survei, verifikasi data GPS, SIM pengemudi, dan riwayat klaim tertanggung.</li>
        <li>Persetujuan klaim diterbitkan maksimal 14 hari kerja setelah dokumen lengkap.</li>
    </ol>

    <h2>Pengecualian Utama</h2>
    <ul class="body">
        <li>Kendaraan digunakan sebagai taksi online, transportasi daring berpenumpang, disewakan (rental), atau mengangkut penumpang berbayar.</li>
        <li>Kendaraan dikemudikan orang yang tidak memiliki SIM sah sesuai golongan, atau di bawah pengaruh alkohol/narkotika.</li>
        <li>Keterangan riwayat kendaraan/tertanggung yang tidak benar pada SPPA.</li>
        <li>Keausan, kerusakan mekanis/elektris biasa, dan kerugian akibat risiko yang tidak tercantum sebagai perluasan jaminan.</li>
    </ul>

    <div class="sigrow">
        @include('pdfs._entity-signature', ['label' => 'Penanggung', 'entity' => $legal['insurer'], 'width' => '50%'])
        <div class="sigcell" style="width:50%; text-align:left; font-size:9.5px; color:#555; line-height:1.5;">
            Polis ini diterbitkan berdasarkan SPPA yang ditandatangani tertanggung dan tunduk pada Polis Standar Asuransi
            Kendaraan Bermotor Indonesia (PSAKBI) beserta klausula perluasan yang tercantum. {{ $legal['insurer']['license'] }}.
        </div>
    </div>
@endsection
