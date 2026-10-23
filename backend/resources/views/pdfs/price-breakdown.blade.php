@extends('pdfs._layout')

@section('title', 'Rincian Harga - ' . ($t->invoice_number ?? $t->id))
@section('doc_type', 'Rincian Harga & Biaya')
@section('doc_number', $docNumber)

@php
    $rp = fn ($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',') . '%';
    $isFull = $breakdown['payment']['scheme'] === 'full';
    $ins = $breakdown['insurance'];
    $seller = $breakdown['seller_settlement'];
    $credit = $breakdown['credit_simulation'];
@endphp

@section('content')
    <p class="body" style="margin-top:0;">
        Lampiran Perjanjian Jual Beli untuk <strong>{{ $t->vehicle->brand }} {{ $t->vehicle->model }} ({{ $t->vehicle->year }})</strong>,
        invoice <strong>{{ $t->invoice_number }}</strong>. Dokumen ini menjelaskan asal setiap angka &mdash; dari harga kendaraan,
        pajak yang terkandung, asuransi, biaya balik nama, hingga dana bersih yang diterima penjual &mdash; supaya Pembeli dan
        Penjual memahami dasar perhitungannya sebelum menandatangani perjanjian.
    </p>

    <div class="parties">
        <div class="party">
            <h3>Penjual</h3>
            <p><strong>{{ $t->seller->ktp_name ?: $t->seller->name }}</strong></p>
            <p>{{ $t->seller->email }}</p>
        </div>
        <div class="party">
            <h3>Pembeli</h3>
            <p><strong>{{ $t->buyer->ktp_name ?: $t->buyer->name }}</strong></p>
            <p>{{ $t->buyer->email }}</p>
        </div>
    </div>

    <h2>A. Ringkasan</h2>
    <table class="grid">
        <tr><th>Keterangan</th><th class="num">Jumlah</th></tr>
        <tr><td>Harga kendaraan (sudah termasuk PPN &amp; PPnBM)</td><td class="num">{{ $rp($breakdown['vehicle_price']) }}</td></tr>
        <tr><td>Skema pembayaran</td><td class="num">{{ $isFull ? 'Bayar penuh' : 'DP ' . $pct($breakdown['payment']['dp_percent']) }}</td></tr>
        <tr class="subtotal"><td>Dibayar sekarang ke rekening escrow</td><td class="num">{{ $rp($breakdown['due_now']['total']) }}</td></tr>
        @unless($isFull)
            <tr><td>Sisa pelunasan {{ ($breakdown['seller_settlement']['remaining_payer'] ?? 'buyer') === 'lender' ? 'dilunasi leasing (' . $breakdown['credit']['lender'] . ') kepada Penjual setelah kredit disetujui' : 'langsung ke Penjual saat serah terima' }}</td><td class="num">{{ $rp($breakdown['payment']['remaining_amount']) }}</td></tr>
        @endunless
        <tr><td>Estimasi biaya balik nama ({{ ($breakdown['registration']['in_escrow'] ?? false) ? 'sudah termasuk tagihan escrow di atas' : 'dibayar Pembeli ke Samsat' }})</td><td class="num">{{ $rp($breakdown['registration']['total']) }}</td></tr>
        <tr class="subtotal"><td>Total biaya kepemilikan tahun pertama (Pembeli)</td><td class="num">{{ $rp($breakdown['total_cost_of_ownership']['total']) }}</td></tr>
        <tr class="subtotal"><td>Dana bersih yang diterima Penjual</td><td class="num">{{ $rp($seller['total_net']) }}</td></tr>
    </table>

    <h2>B. Komposisi Pajak dalam Harga Kendaraan</h2>
    <table class="grid">
        <tr><th>Komponen</th><th>Dasar Perhitungan</th><th class="num">Jumlah</th></tr>
        @foreach($breakdown['price_composition']['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="2">Harga kendaraan</td><td class="num">{{ $rp($breakdown['price_composition']['total']) }}</td></tr>
    </table>
    <p class="disclaimer" style="color:#666; font-size:9px;">{{ $breakdown['price_composition']['note'] }}</p>

    <h2>C. Tagihan yang Dibayar Sekarang</h2>
    <table class="grid">
        <tr><th>Komponen</th><th>Dasar Perhitungan</th><th class="num">Jumlah</th></tr>
        @foreach($breakdown['due_now']['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="2">Total tagihan escrow</td><td class="num">{{ $rp($breakdown['due_now']['total']) }}</td></tr>
    </table>

    <h2>D. Asuransi Kendaraan</h2>
    @if($ins['type'] === 'none')
        <p class="body">Pembeli memilih tanpa asuransi &mdash; tidak ada premi. Risiko kerusakan/kehilangan sepenuhnya ditanggung Pembeli setelah serah terima.</p>
    @else
        <p class="body" style="margin-top:0;">
            Jaminan <strong>{{ $ins['label'] }}</strong> &middot; Penanggung: {{ $ins['insurer'] }} &middot;
            Harga pertanggungan (TSI): {{ $rp($ins['sum_insured']) }}
        </p>
        <table class="grid">
            <tr><th>Komponen Premi</th><th>Dasar Perhitungan</th><th class="num">Jumlah</th></tr>
            @foreach($ins['lines'] as $line)
                <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
            @endforeach
            <tr class="subtotal"><td colspan="2">Total premi &amp; biaya polis tahun pertama</td><td class="num">{{ $rp($ins['total']) }}</td></tr>
        </table>
        <ul class="body">
            @if($ins['deductible_per_claim'])
                <li>Risiko sendiri (deductible) {{ $rp($ins['deductible_per_claim']) }} per kejadian untuk klaim kerusakan sebagian.</li>
            @endif
            <li>Kerusakan &ge; {{ $ins['total_loss_threshold_percent'] }}% dari harga kendaraan dianggap kerugian total (total loss).</li>
            <li>Klaim kehilangan (pencurian) diganti {{ $ins['theft_settlement_percent'] }}% dari harga pertanggungan.</li>
            <li>Jaminan gugur bila kendaraan dipakai sebagai taksi online/rental atau dikemudikan tanpa SIM yang sah.</li>
        </ul>
    @endif

    <h2>E. Estimasi Biaya Balik Nama &amp; Registrasi (Dibayar Pembeli ke Samsat)</h2>
    <p class="body" style="margin-top:0;">NJKB simulasi: {{ $rp($breakdown['registration']['njkb']) }} ({{ $breakdown['registration']['njkb_basis'] }}).</p>
    <table class="grid">
        <tr><th>Komponen</th><th>Dasar Perhitungan</th><th>Dibayar ke</th><th class="num">Jumlah</th></tr>
        @foreach($breakdown['registration']['lines'] as $line)
            <tr><td>{{ $line['label'] }}</td><td class="basis">{{ $line['basis'] }}</td><td class="basis">{{ $line['payee'] }}</td><td class="num">{{ $rp($line['amount']) }}</td></tr>
        @endforeach
        <tr class="subtotal"><td colspan="3">Total estimasi</td><td class="num">{{ $rp($breakdown['registration']['total']) }}</td></tr>
    </table>
    <p class="disclaimer" style="color:#666; font-size:9px;">{{ $breakdown['registration']['note'] }}</p>

    <h2>F. Hak Bersih Penjual</h2>
    <table class="grid">
        <tr><th>Komponen</th><th>Dasar Perhitungan</th><th class="num">Jumlah</th></tr>
        <tr><td>Porsi kendaraan yang ditahan di escrow</td><td class="basis">{{ $isFull ? 'Pelunasan penuh' : 'DP ' . $pct($breakdown['payment']['dp_percent']) }}</td><td class="num">{{ $rp($seller['escrow_vehicle_portion']) }}</td></tr>
        <tr><td>Dikurangi biaya layanan escrow</td><td class="basis">{{ $seller['commission_basis'] }}</td><td class="num">&minus; {{ $rp($seller['commission']) }}</td></tr>
        <tr class="subtotal"><td colspan="2">Dicairkan dari escrow ke rekening Penjual</td><td class="num">{{ $rp($seller['net_from_escrow']) }}</td></tr>
        @unless($isFull)
            <tr><td colspan="2">Ditambah sisa pelunasan {{ ($seller['remaining_payer'] ?? 'buyer') === 'lender' ? 'dari leasing (dibayar sekaligus setelah kredit disetujui)' : 'langsung dari Pembeli' }}</td><td class="num">{{ $rp($seller['direct_remaining']) }}</td></tr>
        @endunless
        <tr class="subtotal"><td colspan="2">Total diterima Penjual</td><td class="num">{{ $rp($seller['total_net']) }}</td></tr>
    </table>
    <p class="disclaimer" style="color:#666; font-size:9px;">{{ $seller['note'] }}</p>

    @if(! empty($breakdown['credit']))
        @php($plan = $breakdown['credit'])
        <h2>G. Rencana Kredit yang Dipilih</h2>
        <table class="grid">
            <tr><td>Pemberi pembiayaan (leasing)</td><td class="num">{{ $plan['lender'] }}</td></tr>
            <tr><td>Sisa harga kendaraan dibiayai</td><td class="num">{{ $rp($plan['vehicle_financed']) }}</td></tr>
            <tr><td>Premi asuransi tahun ke-2 dst. ikut dibiayai</td><td class="num">{{ $rp($plan['insurance_financed']) }}</td></tr>
            <tr class="subtotal"><td>Pokok pinjaman</td><td class="num">{{ $rp($plan['principal']) }}</td></tr>
            <tr><td>Bunga ({{ $plan['rate_basis'] }})</td><td class="num">{{ $pct($plan['flat_rate'] * 100) }} flat &middot; efektif {{ $pct($plan['effective_rate'] * 100) }}/thn</td></tr>
            <tr><td>Total bunga {{ $plan['tenor_months'] }} bulan</td><td class="num">{{ $rp($plan['interest_total']) }}</td></tr>
            <tr class="subtotal"><td>Angsuran per bulan &times; {{ $plan['tenor_months'] }}</td><td class="num">{{ $rp($plan['monthly_installment']) }}</td></tr>
            <tr><td>Total dibayar ke leasing</td><td class="num">{{ $rp($plan['total_payable']) }}</td></tr>
        </table>
        <p class="disclaimer" style="color:#666; font-size:9px;">{{ $plan['note'] }}</p>
    @endif

    <h2>{{ empty($breakdown['credit']) ? 'G' : 'H' }}. Simulasi Kredit Sisa Pelunasan (Ilustrasi)</h2>
    @if(! $credit['applicable'])
        <p class="body">{{ $credit['note'] }}</p>
    @else
        <p class="body" style="margin-top:0;">
            Pemberi pembiayaan: {{ $credit['lender'] }} &middot; Pokok pinjaman: {{ $rp($credit['principal']) }} &middot;
            Provisi {{ $pct($credit['provision_rate'] * 100) }} ({{ $rp($credit['provision_amount']) }}) + biaya administrasi {{ $rp($credit['admin_fee']) }} dibayar di muka.
        </p>
        <table class="grid">
            <tr><th>Tenor</th><th class="num">Bunga flat/thn</th><th class="num">Efektif/thn</th><th class="num">Total bunga</th><th class="num">Angsuran/bln</th><th class="num">Angsuran + asuransi</th><th class="num">Total bayar</th></tr>
            @foreach($credit['rows'] as $row)
                <tr>
                    <td style="white-space:nowrap;">{{ $row['tenor_months'] }} bulan</td>
                    <td class="num">{{ $pct($row['flat_rate'] * 100) }}</td>
                    <td class="num">{{ isset($row['effective_rate']) ? $pct($row['effective_rate'] * 100) : '-' }}</td>
                    <td class="num">{{ $rp($row['interest_total']) }}</td>
                    <td class="num">{{ $rp($row['monthly_installment']) }}</td>
                    <td class="num">{{ $rp($row['monthly_installment_with_insurance']) }}</td>
                    <td class="num">{{ $rp($row['total_payment']) }}</td>
                </tr>
            @endforeach
        </table>
        <p class="disclaimer" style="color:#666; font-size:9px;">
            Angsuran = (pokok + pokok &times; bunga flat &times; tenor tahun) &divide; tenor bulan. {{ $credit['note'] }}
        </p>
    @endif

    <div class="sigrow">
        @include('pdfs._entity-signature', ['label' => 'Diterbitkan oleh', 'entity' => $legal['platform'], 'width' => '50%'])
        <div class="sigcell" style="width:50%; text-align:left; font-size:9.5px; color:#555; line-height:1.5;">
            Rincian ini dihitung otomatis oleh sistem saat invoice diterbitkan dan dibekukan (snapshot) sehingga
            tidak berubah walaupun tarif platform diperbarui di kemudian hari.
        </div>
    </div>
@endsection
