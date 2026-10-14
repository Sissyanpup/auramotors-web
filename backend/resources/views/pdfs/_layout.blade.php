<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('title')</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; }
        .wrap { padding: 32px 40px; }
        h1 { font-size: 20px; margin: 0 0 4px 0; letter-spacing: 0.02em; }
        h2 { font-size: 14px; margin: 20px 0 8px 0; padding-bottom: 4px; border-bottom: 1px solid #d4af37; }
        .meta { color: #666; font-size: 10px; }
        .header { display: table; width: 100%; margin-bottom: 24px; }
        .header .brand { display: table-cell; vertical-align: top; }
        .header .doc { display: table-cell; vertical-align: top; text-align: right; }
        .brand .logo { font-size: 22px; font-weight: bold; color: #b8860b; letter-spacing: 0.05em; }
        .brand .tag { font-size: 9px; color: #666; text-transform: uppercase; letter-spacing: 0.1em; margin-top: 2px; }
        .doc .type { font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.1em; }
        .doc .no { font-size: 11px; color: #333; margin-top: 4px; }
        .doc .date { font-size: 10px; color: #666; margin-top: 2px; }
        table.info { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.info td { padding: 4px 6px; vertical-align: top; font-size: 11px; }
        table.info td.label { color: #666; width: 30%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.items th { background: #f5f5f5; padding: 8px 10px; text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 2px solid #d4af37; }
        table.items td { padding: 10px; border-bottom: 1px solid #eee; vertical-align: top; }
        table.items td.num { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.totals { width: 40%; margin-left: auto; margin-top: 8px; border-collapse: collapse; }
        table.totals td { padding: 4px 8px; font-size: 11px; }
        table.totals td.label { color: #666; text-align: right; }
        table.totals td.value { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        table.totals tr.grand td { border-top: 2px solid #d4af37; padding-top: 8px; font-weight: bold; font-size: 12px; color: #b8860b; }
        .parties { display: table; width: 100%; margin-top: 8px; }
        .parties .party { display: table-cell; width: 50%; padding-right: 12px; vertical-align: top; }
        .parties .party:last-child { padding-right: 0; padding-left: 12px; }
        .parties .party h3 { margin: 0 0 4px 0; font-size: 10px; text-transform: uppercase; letter-spacing: 0.1em; color: #666; }
        .parties .party p { margin: 2px 0; font-size: 11px; }
        .parties .party strong { color: #1a1a1a; }
        .signature { display: table; width: 100%; margin-top: 40px; }
        .signature .sig { display: table-cell; width: 50%; text-align: center; vertical-align: top; padding: 0 20px; }
        .signature .sig .line { border-top: 1px solid #333; margin-top: 60px; padding-top: 4px; font-size: 10px; color: #666; }
        .signature .sig .label { font-size: 10px; color: #666; text-transform: uppercase; letter-spacing: 0.05em; }
        .footer { margin-top: 32px; padding-top: 12px; border-top: 1px solid #ddd; font-size: 9px; color: #999; text-align: center; }
        .notice { background: #fef8e0; border-left: 3px solid #d4af37; padding: 8px 12px; font-size: 10px; color: #665022; margin-top: 12px; }
        .status-paid { color: #0a7a3b; font-weight: bold; }
        .status-pending { color: #b8860b; font-weight: bold; }
        p.body { font-size: 11px; line-height: 1.6; margin: 6px 0; text-align: justify; }
        ol.body, ul.body { font-size: 10.5px; line-height: 1.6; padding-left: 16px; margin: 4px 0; }
        table.grid { width: 100%; border-collapse: collapse; margin-top: 6px; }
        table.grid th { background: #f5f5f5; padding: 6px 8px; text-align: left; font-size: 9.5px; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #d4af37; }
        table.grid td { padding: 6px 8px; border-bottom: 1px solid #eee; vertical-align: top; font-size: 10.5px; }
        table.grid td.num, table.grid th.num { text-align: right; white-space: nowrap; }
        table.grid td.basis { color: #666; font-size: 9.5px; }
        table.grid tr.subtotal td { font-weight: bold; border-top: 1px solid #d4af37; background: #fcfaf2; }
        .sigrow { display: table; width: 100%; margin-top: 28px; page-break-inside: avoid; }
        .sigrow .sigcell { display: table-cell; text-align: center; vertical-align: top; padding: 0 8px; }
        .sigcell .role { font-size: 9.5px; color: #666; text-transform: uppercase; letter-spacing: 0.06em; }
        .sigcell .sigbox { height: 74px; position: relative; margin-top: 4px; }
        .sigcell .sigbox img.ink { height: 62px; margin-top: 6px; }
        .sigcell .name { border-top: 1px solid #333; padding-top: 4px; font-size: 10.5px; font-weight: bold; }
        .sigcell .caption { font-size: 8.5px; color: #888; margin-top: 2px; }
        .sigcell .pending { font-size: 9px; color: #b8860b; padding-top: 30px; font-style: italic; }
        .seal { width: 74px; height: 74px; border: 3px double #1f4fa3; border-radius: 37px; color: #1f4fa3; font-size: 6.5px; font-weight: bold; text-align: center; line-height: 1.25; position: absolute; left: 50%; margin-left: -10px; top: 0; opacity: 0.85; }
        .seal span { display: block; padding-top: 18px; }
        .meterai { display: inline-block; border: 1px solid #c0392b; color: #c0392b; font-size: 7px; text-align: center; padding: 3px 5px; line-height: 1.3; background: #fff5f3; position: absolute; left: 8px; top: 8px; }
        .meterai strong { font-size: 9px; display: block; }
        .legal-box { border: 1px solid #e3d7a8; background: #fcfaf2; padding: 8px 12px; margin-top: 8px; font-size: 10px; line-height: 1.5; }
        .disclaimer { font-size: 8px; color: #b0b0b0; margin-top: 4px; }
        @yield('extra_styles')
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div class="brand">
            <div class="logo" style="@yield('brand_style')">@yield('brand_name', 'AURA MOTORS')</div>
            <div class="tag">@yield('brand_tag', 'Luxury Vehicle Marketplace')</div>
        </div>
        <div class="doc">
            <div class="type">@yield('doc_type')</div>
            <div class="no">No. @yield('doc_number')</div>
            <div class="date">{{ now()->translatedFormat('d M Y, H:i') }} WIB</div>
        </div>
    </div>

    @yield('content')

    <div class="footer">
        Dokumen digital resmi &mdash; dihasilkan otomatis oleh sistem escrow AuraMotors.
        Verifikasi keaslian melalui panel transaksi platform.
        @isset($verificationCode)
            <br>Kode verifikasi dokumen: <strong>{{ $verificationCode }}</strong>
        @endisset
        <div class="disclaimer">
            SIMULASI AKADEMIK &mdash; seluruh identitas badan hukum, nomor izin, tarif pajak/asuransi, dan tanda tangan
            elektronik pada dokumen ini fiktif untuk keperluan tugas Pemrograman Web 2.
        </div>
    </div>
</div>
</body>
</html>
