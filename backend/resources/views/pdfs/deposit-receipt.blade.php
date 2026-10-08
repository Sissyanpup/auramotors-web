@extends('pdfs._layout')

@section('title', 'Deposit Receipt - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Deposit Receipt')
@section('doc_number', 'DEP-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@section('content')
    <div class="notice">
        <strong>Fund Locked in Escrow.</strong> Dana pembayaran/DP dari buyer telah diterima platform dan
        ditahan (escrow hold) sampai serah-terima kendaraan tuntas. Nota ini adalah bukti resmi penerimaan
        dana dari buyer, bukan bukti pembayaran ke seller.
    </div>

    <div class="parties" style="margin-top:16px;">
        <div class="party">
            <h3>Dana Diterima Dari</h3>
            <p><strong>{{ $t->buyer->name ?? '-' }}</strong></p>
            <p>{{ $t->buyer->email ?? '' }}</p>
            @if($t->buyer && $t->buyer->ktp_number)
                <p>NIK: {{ $t->buyer->ktp_number }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Ditahan Untuk</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong> <em>(seller)</em></p>
            <p>Pencairan setelah serah-terima disetujui admin.</p>
        </div>
    </div>

    <h2>Rincian Dana</h2>
    <table class="info">
        <tr>
            <td class="label">Nomor Transaksi</td>
            <td><strong>{{ $t->invoice_number ?? ('TRX-' . $t->id) }}</strong></td>
        </tr>
        <tr>
            <td class="label">Kendaraan</td>
            <td>{{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} ({{ $t->vehicle->year ?? '' }})</td>
        </tr>
        <tr>
            <td class="label">Skema</td>
            <td>{{ $t->payment_scheme?->value === 'full' ? 'Bayar Penuh' : ('DP ' . rtrim(rtrim((string) $t->dp_percent, '0'), '.') . '%') }}</td>
        </tr>
        <tr>
            <td class="label">Metode</td>
            <td>{{ strtoupper((string) ($t->payment_gateway?->value ?? '')) }} &middot; ref {{ $t->gateway_reference ?? '-' }}</td>
        </tr>
        <tr>
            <td class="label">Diterima Pada</td>
            <td>{{ $t->paid_at?->format('d M Y H:i') ?? '-' }} WIB</td>
        </tr>
    </table>

    <table class="totals">
        <tr>
            <td class="label">Nilai Kendaraan</td>
            <td class="value">Rp {{ number_format((float) $t->vehicle_price, 0, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="label">Premi Asuransi</td>
            <td class="value">Rp {{ number_format((float) $t->insurance_premium, 0, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td class="label">Dana Ditahan (Escrow)</td>
            <td class="value">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
        </tr>
    </table>

    <div class="signature">
        <div class="sig">
            <div class="label">Diterbitkan oleh</div>
            <div class="line">Sistem Escrow AuraMotors</div>
        </div>
        <div class="sig">
            <div class="label">Diakui oleh</div>
            <div class="line">{{ $t->buyer->name ?? 'Buyer' }}</div>
        </div>
    </div>
@endsection
