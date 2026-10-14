@extends('pdfs._layout')

@section('title', 'Perjanjian Jual Beli - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Perjanjian Jual Beli')
@section('doc_number', 'SPA-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $platform = $legal['platform'];
    $isFull = ($breakdown['payment']['scheme'] ?? null) === 'full';
    $ins = $breakdown['insurance'];
    $sellerProfile = $t->seller?->sellerProfile;
    $isCompanySeller = $sellerProfile && $sellerProfile->entity_type?->value === 'perusahaan';
@endphp

@section('content')
    <p class="body" style="text-align:center; font-weight:bold; font-size:12px; margin-top:0;">
        PERJANJIAN JUAL BELI KENDARAAN BERMOTOR DENGAN REKENING BERSAMA (ESCROW)
    </p>
    <p class="body">
        Pada hari ini, {{ $t->created_at?->translatedFormat('l, d F Y') ?? now()->translatedFormat('l, d F Y') }},
        yang bertanda tangan di bawah ini:
    </p>

    <table class="info">
        <tr>
            <td class="label">I. Penjual (Pihak Pertama)</td>
            <td>
                <strong>{{ $t->seller->ktp_name ?: $t->seller->name }}</strong>
                @if($t->seller->ktp_number) &middot; NIK {{ $t->seller->ktp_number }} @endif
                <br>{{ $t->seller->email }}
                @if($isCompanySeller)
                    <br>Bertindak untuk dan atas nama badan usaha penjual (KYC perusahaan terverifikasi).
                @else
                    <br>Bertindak untuk dan atas nama diri sendiri selaku pemilik sah kendaraan.
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">II. Pembeli (Pihak Kedua)</td>
            <td>
                <strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong>
                @if($t->buyer->ktp_number) &middot; NIK {{ $t->buyer->ktp_number }} @endif
                <br>{{ $t->buyer->email }} @if($t->buyer_phone) &middot; {{ $t->buyer_phone }} @endif
                @if($t->buyer_address)<br>{{ $t->buyer_address }}@endif
            </td>
        </tr>
        <tr>
            <td class="label">III. Agen Escrow (Pihak Ketiga)</td>
            <td>
                <strong>{{ $platform['name'] }}</strong>, suatu {{ $platform['legal_form'] }} yang didirikan menurut
                hukum Republik Indonesia, berkedudukan di {{ $platform['address'] }}; disahkan Kemenkumham
                No. {{ $platform['ahu_number'] }}; NIB {{ $platform['nib'] }}; NPWP {{ $platform['npwp'] }};
                dalam hal ini diwakili oleh <strong>{{ $platform['signatory_name'] }}</strong> selaku
                {{ $platform['signatory_title'] }} berdasarkan {{ $platform['notary'] }}.
            </td>
        </tr>
    </table>

    <p class="body">
        Para Pihak sepakat mengikatkan diri dalam Perjanjian Jual Beli ini (&ldquo;<strong>Perjanjian</strong>&rdquo;)
        dengan ketentuan sebagai berikut:
    </p>

    <h2>Pasal 1 &mdash; Objek Jual Beli</h2>
    <table class="info">
        <tr><td class="label">Merek / Model / Tahun</td><td><strong>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} &middot; {{ $t->vehicle->year }}</strong></td></tr>
        <tr><td class="label">Nomor Rangka (VIN)</td><td>{{ $t->vehicle->vin ?: '— (sesuai STNK/BPKB)' }}</td></tr>
        <tr><td class="label">Jarak Tempuh</td><td>{{ number_format((int) $t->vehicle->mileage, 0, ',', '.') }} km</td></tr>
        <tr><td class="label">Lokasi Kendaraan</td><td>{{ $t->vehicle->location }}</td></tr>
    </table>

    <h2>Pasal 2 &mdash; Harga Jual</h2>
    <p class="body">
        Harga jual Kendaraan disepakati sebesar <strong>{{ $rp($breakdown['vehicle_price']) }}</strong>
        (<em>{{ \App\Support\Terbilang::rupiah($breakdown['vehicle_price']) }}</em>), sudah termasuk PPN dan PPnBM
        yang terkandung di dalamnya. Rincian komposisi harga, pajak, asuransi, dan seluruh biaya lain dijelaskan
        dalam <strong>Lampiran Rincian Harga, Pajak &amp; Biaya</strong> yang merupakan bagian tidak terpisahkan
        dari Perjanjian ini.
    </p>

    <h2>Pasal 3 &mdash; Cara Pembayaran</h2>
    <table class="grid">
        <tr><th>Komponen</th><th>Dasar Perhitungan</th><th class="num">Jumlah</th></tr>
        @foreach($breakdown['due_now']['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="2">Dibayar Pembeli ke Rekening Escrow</td><td class="num">{{ $rp($breakdown['due_now']['total']) }}</td></tr>
        @unless($isFull)
            <tr><td colspan="2">Sisa pelunasan dibayar langsung kepada Penjual paling lambat saat serah terima</td><td class="num">{{ $rp($breakdown['payment']['remaining_amount']) }}</td></tr>
        @endunless
    </table>
    <p class="body">
        Pembayaran ke rekening escrow dilakukan ke <strong>{{ $t->bank_transfer_bank }}</strong>
        No. {{ $t->bank_transfer_account_number }} a.n. {{ $t->bank_transfer_account_holder }}
        dengan referensi invoice <strong>{{ $t->invoice_number }}</strong>.
    </p>

    <h2>Pasal 4 &mdash; Rekening Bersama (Escrow)</h2>
    <ol class="body">
        <li>Pihak Ketiga menerima dan menahan dana Pembeli pada rekening kustodian di {{ $legal['bank']['name'] }}
            dan tidak akan menyerahkannya kepada siapa pun sebelum syarat pencairan terpenuhi.</li>
        <li>Dana porsi kendaraan dicairkan kepada Penjual setelah: (a) Berita Acara Serah Terima ditandatangani
            Penjual dan Pembeli; (b) kedua pihak mengonfirmasi serah terima di platform; dan (c) Pihak Ketiga
            menyetujui pelepasan dana.</li>
        <li>Penjual menanggung biaya layanan escrow sebesar {{ rtrim(rtrim(number_format($breakdown['seller_settlement']['commission_rate'] * 100, 2, ',', '.'), '0'), ',') }}%
            dari porsi kendaraan yang ditahan ({{ $rp($breakdown['seller_settlement']['commission']) }}), dipotong saat pencairan.</li>
        <li>Premi asuransi yang ikut dibayarkan diteruskan Pihak Ketiga kepada perusahaan asuransi, bukan kepada Penjual.</li>
    </ol>

    <h2>Pasal 5 &mdash; Asuransi Kendaraan</h2>
    @if($ins['type'] === 'none')
        <p class="body">Pembeli memilih <strong>tanpa asuransi</strong>. Sejak serah terima, seluruh risiko kehilangan dan
            kerusakan Kendaraan beralih kepada Pembeli.</p>
    @else
        <p class="body">
            Kendaraan diasuransikan dengan jaminan <strong>{{ $ins['label'] }}</strong> pada {{ $ins['insurer'] }}
            dengan harga pertanggungan {{ $rp($ins['sum_insured']) }} dan total premi tahun pertama {{ $rp($ins['total']) }}.
            Polis berlaku sejak pembayaran diterima di rekening escrow. Ketentuan, perluasan jaminan, dan pengecualian
            tunduk pada Ikhtisar Polis yang diterbitkan penanggung.
        </p>
    @endif

    <h2>Pasal 6 &mdash; Pajak &amp; Biaya</h2>
    <ol class="body">
        <li>Pajak Kendaraan Bermotor (PKB) dan denda yang terutang sebelum tanggal serah terima menjadi tanggungan Penjual.</li>
        <li>Biaya balik nama dan registrasi (BBN-KB, PKB tahun berjalan, SWDKLLJ, PNBP STNK/TNKB/BPKB), diestimasi sebesar
            {{ $rp($breakdown['registration']['total']) }}, menjadi tanggungan Pembeli dan dibayar langsung ke Samsat.</li>
        <li>Bea meterai atas dokumen yang ditandatangani masing-masing pihak ditanggung oleh pihak yang menandatangani.</li>
    </ol>

    <h2>Pasal 7 &mdash; Pernyataan &amp; Jaminan Penjual</h2>
    <p class="body">
        Penjual menjamin bahwa Kendaraan adalah milik sahnya, tidak sedang dijaminkan, tidak dalam sengketa atau sitaan,
        dan dokumen STNK/BPKB asli serta sah, sebagaimana dituangkan dalam Surat Pernyataan Keabsahan Kendaraan.
        Penjual membebaskan Pembeli dan Pihak Ketiga dari segala tuntutan pihak lain atas Kendaraan.
    </p>

    <h2>Pasal 8 &mdash; Serah Terima &amp; Balik Nama</h2>
    <p class="body">
        Serah terima fisik Kendaraan beserta STNK, BPKB, faktur, kunci, dan buku servis dituangkan dalam Berita Acara
        Serah Terima. Untuk keperluan balik nama, Penjual memberikan Surat Kuasa Balik Nama kepada Pembeli.
        Risiko atas Kendaraan beralih kepada Pembeli sejak Berita Acara Serah Terima ditandatangani.
    </p>

    <h2>Pasal 9 &mdash; Wanprestasi &amp; Pembatalan</h2>
    <ol class="body">
        <li>Apabila Kendaraan tidak sesuai deskripsi, Pembeli berhak mengajukan sengketa melalui platform sebelum
            mengonfirmasi penerimaan; dana tetap ditahan sampai sengketa diputus Pihak Ketiga.</li>
        <li>Apabila Penjual gagal menyerahkan Kendaraan, dana di escrow dikembalikan penuh kepada Pembeli.</li>
        <li>Apabila Pembeli membatalkan secara sepihak setelah pembayaran, Pihak Ketiga berhak memotong biaya administrasi
            sesuai kebijakan platform.</li>
    </ol>

    <h2>Pasal 10 &mdash; Penyelesaian Sengketa</h2>
    <p class="body">
        Perselisihan diselesaikan secara musyawarah melalui fasilitas resolusi sengketa platform. Apabila dalam 30 hari
        tidak tercapai mufakat, Para Pihak memilih domisili hukum di Kepaniteraan Pengadilan Negeri Jakarta Selatan
        dan Perjanjian ini tunduk pada hukum Republik Indonesia.
    </p>

    <h2>Pasal 11 &mdash; Tanda Tangan Elektronik</h2>
    <p class="body">
        Perjanjian ini ditandatangani secara elektronik dan memiliki kekuatan hukum yang sama dengan tanda tangan basah
        sebagaimana diatur dalam UU No. 11 Tahun 2008 tentang ITE beserta perubahannya dan PP No. 71 Tahun 2019.
        Jejak waktu dan alamat IP penandatangan tersimpan dalam sistem sebagai alat bukti.
    </p>

    <div class="sigrow">
        @include('pdfs._party-signature', ['label' => 'Pihak Pertama — Penjual', 'sig' => $signatures['seller'], 'meterai' => true])
        @include('pdfs._party-signature', ['label' => 'Pihak Kedua — Pembeli', 'sig' => $signatures['buyer'], 'meterai' => true])
        @include('pdfs._entity-signature', ['label' => 'Pihak Ketiga — Agen Escrow', 'entity' => $platform])
    </div>
@endsection
