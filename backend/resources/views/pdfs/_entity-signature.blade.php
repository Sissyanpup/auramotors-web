{{--
    Blok tanda tangan badan hukum (platform / bank / asuradur): tanda tangan
    pejabat berwenang + cap perusahaan. Ditandatangani otomatis oleh sistem.
    Param: $label, $entity (array dari config/legal.php), $width (opsional).
--}}
@php
    $shortName = mb_strtoupper(trim(preg_replace('/^PT\s+|\s*\(Simulasi\)|\s+Tbk/i', '', $entity['name'])));
    $seed = crc32($entity['name']) % 20;
    $ink = 'data:image/svg+xml;base64,'.base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="180" height="60" viewBox="0 0 180 60">'
        .'<path d="M8 '.(38 + $seed % 5).' C 22 '.(4 + $seed).', 34 58, 48 30 S 70 '.(8 + $seed).', 84 36 S 108 52, 118 '.(18 + $seed % 7).' S 146 46, 172 14" '
        .'fill="none" stroke="#1a3a7a" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>'
        .'<path d="M40 48 L 150 44" fill="none" stroke="#1a3a7a" stroke-width="1.4" stroke-linecap="round"/>'
        .'</svg>'
    );
@endphp
<div class="sigcell" style="width: {{ $width ?? '33%' }};">
    <div class="role">{{ $label }}</div>
    <div class="sigbox">
        <img class="ink" src="{{ $ink }}" alt="Tanda tangan {{ $entity['signatory_name'] }}">
        <div class="seal"><span>{{ $shortName }}<br>&#9733; JAKARTA &#9733;</span></div>
    </div>
    <div class="name">{{ $entity['signatory_name'] }}</div>
    <div class="caption">{{ $entity['signatory_title'] }} &mdash; {{ $entity['name'] }}</div>
</div>
