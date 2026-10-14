{{--
    Blok tanda tangan buyer/seller.
    Param: $label (judul pihak), $sig (['name','signed_at','image']), $width (opsional), $meterai (bool, opsional).
--}}
<div class="sigcell" style="width: {{ $width ?? '33%' }};">
    <div class="role">{{ $label }}</div>
    <div class="sigbox">
        @if(! empty($meterai))
            <div class="meterai"><strong>e-METERAI</strong>Rp10.000<br>SIMULASI</div>
        @endif
        @if(! empty($sig['image']))
            <img class="ink" src="{{ $sig['image'] }}" alt="Tanda tangan {{ $sig['name'] }}">
        @else
            <div class="pending">Menunggu tanda tangan elektronik</div>
        @endif
    </div>
    <div class="name">{{ $sig['name'] }}</div>
    <div class="caption">
        @if(! empty($sig['signed_at']))
            Ditandatangani elektronik {{ \Illuminate\Support\Carbon::parse($sig['signed_at'])->format('d/m/Y H:i') }} WIB
        @else
            &nbsp;
        @endif
    </div>
</div>
