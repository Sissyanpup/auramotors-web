@extends('pdfs._layout')

@section('title', 'Escrow Disbursement Note - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Escrow Disbursement Note')
@section('doc_number', 'EDN-' . ($t->invoice_number ?? 'TRX-' . $t->id))

@php
    $payout = $t->payouts->firstWhere('status.value', 'paid') ?? $t->payouts->first();
@endphp

@section('content')
    <div class="notice">
        <strong>Konfirmasi Pelepasan Dana Escrow.</strong> Nota ini mengonfirmasi bahwa dana yang
        sebelumnya ditahan platform (escrow) telah dilepaskan dan disalurkan kepada Penjual sesuai
        rincian di bawah. Simpan sebagai bukti pencairan resmi.
    </div>

    <div class="parties" style="margin-top:16px;">
        <div class="party">
            <h3>Dana Dilepaskan Kepada</h3>
            <p><strong>{{ $t->seller->name ?? '-' }}</strong></p>
            <p>{{ $t->seller->email ?? '' }}</p>
            @if($t->seller && $t->seller->sellerProfile)
                <p>Bank: <strong>{{ $t->seller->sellerProfile->bank_name ?? '-' }}</strong></p>
                <p>No. Rek: <span style="font-family: DejaVu Sans Mono, monospace;">{{ $t->seller->sellerProfile->bank_account_number ?? '-' }}</span></p>
                <p>a.n. {{ $t->seller->sellerProfile->bank_account_holder_name ?? '-' }}</p>
            @endif
        </div>
        <div class="party">
            <h3>Terkait Transaksi</h3>
            <p>No. Faktur: <strong>{{ $t->invoice_number ?? 'TRX-' . $t->id }}</strong></p>
            <p>Kendaraan: {{ $t->vehicle->brand ?? '' }} {{ $t->vehicle->model ?? '' }} ({{ $t->vehicle->year ?? '' }})</p>
            <p>Pembeli: {{ $t->buyer->name ?? '-' }}</p>
        </div>
    </div>

    <h2>Rincian Pencairan</h2>
    <table class="items">
        <thead>
            <tr>
                <th>Komponen</th>
                <th style="text-align:right;">Nilai</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Nilai transaksi (dana masuk escrow)</td>
                <td class="num">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
            </tr>
            @if($payout)
                <tr>
                    <td>
                        Komisi platform
                        <span style="color:#666; font-size:10px;">
                            ({{ rtrim(rtrim((string) $payout->commission_rate, '0'), '.') }}%)
                        </span>
                    </td>
                    <td class="num">&minus; Rp {{ number_format((float) $payout->commission_amount, 0, ',', '.') }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <table class="totals">
        @if($payout)
            <tr>
                <td class="label">Metode Payout</td>
                <td class="value">{{ strtoupper((string) ($payout->method?->value ?? '-')) }}</td>
            </tr>
            <tr>
                <td class="label">Referensi</td>
                <td class="value">{{ $payout->reference ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">Waktu Pencairan</td>
                <td class="value">{{ $payout->paid_at?->format('d M Y H:i') ?? $payout->created_at?->format('d M Y H:i') }} WIB</td>
            </tr>
            <tr class="grand">
                <td class="label">Dana Diterima Seller</td>
                <td class="value">Rp {{ number_format((float) $payout->payout_amount, 0, ',', '.') }}</td>
            </tr>
        @else
            <tr class="grand">
                <td class="label">Dana Diterima Seller (estimasi)</td>
                <td class="value">Rp {{ number_format((float) $t->amount, 0, ',', '.') }}</td>
            </tr>
        @endif
    </table>

    <div class="signature">
        <div class="sig">
            <div class="label">Diterbitkan oleh</div>
            <div class="line">Escrow Operator AuraMotors</div>
        </div>
        <div class="sig">
            <div class="label">Diakui Diterima oleh</div>
            <div class="line">{{ $t->seller->name ?? '-' }}</div>
        </div>
    </div>
@endsection
