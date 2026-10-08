@extends('pdfs._layout')

@section('title', 'Sales Purchase Agreement - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Sales Purchase Agreement')
@section('doc_number', 'SPA-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@section('content')
    <p style="font-size:11px; line-height:1.6; margin-top:4px;">
        Perjanjian Jual Beli Kendaraan Bermotor ini (&ldquo;<strong>Perjanjian</strong>&rdquo;) dibuat pada
        {{ now()->translatedFormat('l, d F Y') }} antara para pihak yang identitasnya diuraikan di bawah,
        atas kendaraan bermotor yang objek transaksinya dijelaskan pada Pasal 1. Perjanjian ini merupakan
        dokumen hukum yang mengatur hak dan kewajiban Penjual dan Pembeli sejak tanggal ditandatangani.
    </p>

    <h2>Para Pihak</h2>
    <div class="parties">
        <div class="party">
            <h3>Penjual (Pihak Pertama)</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong></p>
            <p>{{ $t->seller->email ?? '' }}</p>
            @if($t->seller && $t->seller->ktp_number)
                <p>NIK: {{ $t->seller->ktp_number }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Pembeli (Pihak Kedua)</h3>
            <p><strong>{{ $t->buyer->name ?? '-' }}</strong></p>
            <p>{{ $t->buyer->email ?? '' }}</p>
            @if($t->buyer && $t->buyer->ktp_number)
                <p>NIK: {{ $t->buyer->ktp_number }}</p>
            @endif
            @if($t->buyer_address)
                <p>{{ $t->buyer_address }}</p>
            @endif
        </div>
    </div>

    <h2>Pasal 1 &mdash; Objek Transaksi</h2>
    <table class="info">
        <tr>
            <td class="label">Merek / Model / Tahun</td>
            <td><strong>{{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} &middot; {{ $t->vehicle->year ?? '' }}</strong></td>
        </tr>
        <tr>
            <td class="label">Kilometer</td>
            <td>{{ number_format((int) ($t->vehicle->mileage ?? 0), 0, ',', '.') }} km</td>
        </tr>
        <tr>
            <td class="label">Lokasi Kendaraan</td>
            <td>{{ $t->vehicle->location ?? '-' }}</td>
        </tr>
    </table>

    <h2>Pasal 2 &mdash; Harga &amp; Pembayaran</h2>
    <table class="totals">
        <tr>
            <td class="label">Harga Kendaraan</td>
            <td class="value">Rp {{ number_format((float) $t->vehicle_price, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Premi Asuransi</td>
            <td class="value">Rp {{ number_format((float) $t->insurance_premium, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Skema</td>
            <td class="value">{{ $t->payment_scheme?->value === 'full' ? 'Bayar Penuh' : ('DP ' . rtrim(rtrim((string) $t->dp_percent, '0'), '.') . '%') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Total Nilai Perjanjian</td>
            <td class="value">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
        </tr>
    </table>
    <p style="font-size:10px; line-height:1.5; margin-top:6px;">
        Dana ditransfer Pembeli ke rekening penampung (<em>escrow</em>) platform dan ditahan sampai
        proses serah-terima kendaraan dikonfirmasi kedua pihak &amp; disetujui admin.
    </p>

    <h2>Pasal 3 &mdash; Ketentuan Umum</h2>
    <ol style="font-size:10.5px; line-height:1.6; padding-left:16px; margin-top:4px;">
        <li>Kendaraan diserahkan <em>as-is</em> berdasarkan hasil inspeksi bersama pada saat serah-terima.</li>
        <li>Penjual menjamin bahwa kendaraan bebas dari sengketa hukum, sitaan, atau jaminan pihak ketiga
            per tanggal Perjanjian ini.</li>
        <li>Pembeli bertanggung jawab atas biaya balik nama dan pajak kepemilikan setelah serah-terima.</li>
        <li>Sengketa yang timbul diselesaikan secara musyawarah melalui fasilitas <em>dispute resolution</em>
            di platform escrow. Bila tidak tercapai kesepakatan, sengketa diselesaikan menurut hukum
            Republik Indonesia.</li>
        <li>Pembatalan Perjanjian oleh Pembeli setelah pembayaran DP diproses sesuai kebijakan platform
            dan dapat dikenakan potongan biaya administrasi.</li>
    </ol>

    <h2>Pasal 4 &mdash; Berlaku Efektif</h2>
    <p style="font-size:11px; line-height:1.6;">
        Perjanjian ini berlaku efektif sejak tanggal Pembeli menyelesaikan pembayaran ke rekening escrow
        dan dinyatakan berakhir setelah serah-terima kendaraan tuntas ditandai selesai oleh admin platform.
    </p>

    <div class="signature">
        <div class="sig">
            <div class="label">Penjual</div>
            @if(! empty($t->seller_signature_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($t->seller_signature_path))
                <img
                    src="{{ 'data:image/png;base64,' . base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($t->seller_signature_path)) }}"
                    alt="Tanda tangan penjual"
                    style="height:60px; margin-top:8px;"
                >
            @endif
            <div class="line">{{ $t->seller->name ?? '-' }}</div>
        </div>
        <div class="sig">
            <div class="label">Pembeli</div>
            @if(! empty($t->buyer_signature_path) && \Illuminate\Support\Facades\Storage::disk('local')->exists($t->buyer_signature_path))
                <img
                    src="{{ 'data:image/png;base64,' . base64_encode(\Illuminate\Support\Facades\Storage::disk('local')->get($t->buyer_signature_path)) }}"
                    alt="Tanda tangan pembeli"
                    style="height:60px; margin-top:8px;"
                >
            @endif
            <div class="line">{{ $t->buyer->name ?? '-' }}</div>
        </div>
    </div>
@endsection
