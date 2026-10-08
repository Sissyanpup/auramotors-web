@extends('pdfs._layout')

@section('title', 'Commercial Invoice - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Commercial Invoice')
@section('doc_number', $t->invoice_number ?? ('TRX-' . $t->id))

@section('content')
    <div class="parties">
        <div class="party">
            <h3>Seller (Penjual)</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong></p>
            <p>{{ $t->seller->email ?? '' }}</p>
            @if($t->seller && $t->seller->ktp_number)
                <p>NIK: {{ $t->seller->ktp_number }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Buyer (Pembeli)</h3>
            <p><strong>{{ $t->buyer->name ?? '-' }}</strong></p>
            <p>{{ $t->buyer->email ?? '' }}</p>
            @if($t->buyer && $t->buyer->ktp_number)
                <p>NIK: {{ $t->buyer->ktp_number }}</p>
            @endif
            @if($t->buyer_address)
                <p>{{ $t->buyer_address }}</p>
            @endif
            @if($t->buyer_phone)
                <p>Telp: {{ $t->buyer_phone }}</p>
            @endif
        </div>
    </div>

    <h2>Detail Kendaraan</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th style="text-align:right;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} ({{ $t->vehicle->year ?? '' }})</strong><br>
                    <span style="color:#666; font-size:10px;">
                        Lokasi: {{ $t->vehicle->location ?? '-' }} &middot;
                        Mileage: {{ number_format((int) ($t->vehicle->mileage ?? 0), 0, ',', '.') }} km
                    </span>
                </td>
                <td class="num">Rp {{ number_format((float) $t->vehicle_price, 0, ',', '.') }}</td>
            </tr>
            @if((float) $t->insurance_premium > 0)
                <tr>
                    <td>
                        Asuransi &middot; <em>{{ strtoupper((string) ($t->insurance_type?->value ?? '')) }}</em>
                    </td>
                    <td class="num">Rp {{ number_format((float) $t->insurance_premium, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

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
            <td class="label">Skema Pembayaran</td>
            <td class="value">{{ $t->payment_scheme?->value === 'full' ? 'Lunas' : ('DP ' . rtrim(rtrim((string) $t->dp_percent, '0'), '.') . '%') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Total Ditagihkan</td>
            <td class="value">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <h2>Pembayaran &amp; Escrow</h2>
    <table class="info">
        <tr>
            <td class="label">Status Pembayaran</td>
            <td>
                @if($t->payment_status?->value === 'paid')
                    <span class="status-paid">LUNAS</span> &middot; {{ $t->paid_at?->format('d M Y H:i') ?? '' }} WIB
                @else
                    <span class="status-pending">{{ strtoupper((string) ($t->payment_status?->value ?? 'PENDING')) }}</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="label">Metode Escrow</td>
            <td>Transfer Bank ke Rekening Escrow Platform</td>
        </tr>
        @if($t->bank_transfer_bank)
            <tr>
                <td class="label">Bank Escrow</td>
                <td>{{ $t->bank_transfer_bank }} &middot; {{ $t->bank_transfer_account_number }} &middot; a.n. {{ $t->bank_transfer_account_holder }}</td>
            </tr>
        @endif
        @if($t->expires_at)
            <tr>
                <td class="label">Jatuh Tempo</td>
                <td>{{ $t->expires_at->format('d M Y H:i') }} WIB</td>
            </tr>
        @endif
    </table>

    <div class="notice">
        <strong>Ketentuan:</strong> Dana yang telah masuk ke rekening escrow akan ditahan platform sampai
        proses serah-terima kendaraan dikonfirmasi oleh kedua pihak dan disetujui admin. Faktur ini adalah
        bukti tagihan resmi &mdash; simpan untuk keperluan akuntansi dan pelaporan pajak.
    </div>
@endsection
